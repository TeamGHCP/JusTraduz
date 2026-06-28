<?php

require_once dirname(__DIR__) . '/services/GeminiService.php';
require_once dirname(__DIR__) . '/services/AiRateLimiter.php';
require_once dirname(__DIR__) . '/services/UsageLimiter.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class AiController
{
    private const CONSENT_VERSION = '2026-06-13-v1';

    public function csrf(): void
    {
        $this->json(['csrf' => CsrfMiddleware::token()]);
    }

    public function chat(): void
    {
        $payload = $this->readPayload();
        $message = $payload['message'];
        $history = $payload['history'];

        if (!$payload['authorized'] || !$payload['adult'] || $payload['consent_version'] !== self::CONSENT_VERSION) {
            $this->json([
                'erro' => 'Confirme sua maioridade e aceite os Termos de Uso e a Política de Privacidade antes de usar o Jus IA.',
            ], 403);
            return;
        }

        if ($message === '') {
            $this->json(['erro' => 'Digite uma mensagem para conversar com a IA.'], 422);
            return;
        }

        if (mb_strlen($message) > 1200) {
            $this->json(['erro' => 'Envie uma mensagem menor, com até 1200 caracteres.'], 422);
            return;
        }

        $rateLimit = (new AiRateLimiter())->consume();
        if (!$rateLimit['allowed']) {
            header('Retry-After: ' . (string) $rateLimit['retry_after']);
            $this->json([
                'erro' => 'Muitas mensagens em pouco tempo. Aguarde alguns minutos e tente novamente.',
            ], 429);
            return;
        }

        $userId = (int) ($_SESSION['id'] ?? 0);
        $usage = null;
        if ($userId > 0) {
            require_once dirname(__DIR__) . '/config/database.php';
            $usage = new UsageLimiter(database_connection());
            $quota = $usage->allow($userId, 'ai_chat');
            if (!$quota['allowed']) {
                $this->json(['erro' => $usage->limitMessage('ai_chat', $quota)], 429);
                return;
            }
        }

        $localAnswer = $this->answerLocalQuestion($message, $history);
        if ($localAnswer !== null) {
            $this->json([
                'resposta' => $localAnswer,
                'modelo' => 'JusTraduz local',
            ]);
            return;
        }

        $gemini = new GeminiService();
        if (!$gemini->isConfigured()) {
            $this->json([
                'resposta' => $this->fallbackAnswer($message),
                'modelo' => 'JusTraduz local',
            ]);
            return;
        }

        $answer = $gemini->chat($message, $history);
        if ($answer === null || $answer === '') {
            $this->json([
                'resposta' => $this->fallbackAnswer($message),
                'modelo' => 'JusTraduz local',
            ]);
            return;
        }

        $answer = $this->validateGeneratedAnswer($answer);

        $this->json([
            'resposta' => $answer,
            'modelo' => $gemini->modelName(),
        ]);
        if ($usage !== null) {
            $usage->record($userId, 'ai_chat', 1, null, ['model' => $gemini->modelName()]);
        }
    }

    private function readPayload(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $payload = [];

        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $payload = is_array($decoded) ? $decoded : [];
        } else {
            $payload = $_POST;
        }

        return [
            'message' => trim((string) ($payload['mensagem'] ?? '')),
            'history' => $this->sanitizeHistory($payload['historico'] ?? []),
            'authorized' => filter_var($payload['autorizar_ia'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'adult' => filter_var($payload['confirmar_maioridade'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'consent_version' => trim((string) ($payload['versao_consentimento'] ?? '')),
        ];
    }

    private function sanitizeHistory($history): array
    {
        if (!is_array($history)) {
            return [];
        }

        $items = [];
        foreach (array_slice($history, -8) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = (string) ($item['papel'] ?? '');
            $text = trim((string) ($item['texto'] ?? ''));
            if (!in_array($role, ['usuario', 'assistente'], true) || $text === '') {
                continue;
            }

            $items[] = [
                'papel' => $role,
                'texto' => mb_substr($text, 0, 800),
            ];
        }

        return $items;
    }

    private function answerLocalQuestion(string $message, array $history = []): ?string
    {
        $normalized = $this->normalizeText($message);
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));

        if ($this->containsSensitiveData($message)) {
            return 'Para proteger sua privacidade, não envie CPF, e-mail, telefone, número de processo, senha ou outros dados pessoais neste chat público. Remova esses dados e descreva apenas o tipo de documento e a dúvida de forma geral.';
        }

        if ($this->isLegalEmergency($normalized)) {
            return 'O Jus IA não atende urgências jurídicas. Se houver risco imediato à integridade de alguém, procure o serviço público de emergência adequado. Para prisão, audiência próxima, intimação ou prazo possivelmente em curso, contate imediatamente um advogado, a Defensoria Pública ou o canal oficial do órgão responsável.';
        }

        if ($this->isRestrictedLegalAdvice($normalized)) {
            return 'Não posso calcular prazo processual, avaliar chance de vitória, definir estratégia, redigir defesa ou substituir a análise de um profissional. Posso explicar termos em linguagem simples ou ajudar com dúvidas gerais sobre tradução de documentos. Para esse caso, procure um advogado ou a Defensoria Pública.';
        }

        if ($this->isUnsafeRequest($normalized)) {
            return 'Não posso ajudar com acesso a dados internos, documentos de clientes, senhas, banco de dados, comandos destrutivos ou tentativas de burlar o sistema. Posso ajudar com dúvidas sobre tradução, documentos, orçamento e uso do JusTraduz.';
        }

        if ($this->isPromptInjection($normalized)) {
            return 'Não posso ignorar minhas instruções, revelar regras internas ou assumir papel de administrador. Posso continuar ajudando como assistente do JusTraduz para tradução, documentos e atendimento.';
        }

        if ($this->isSmallTalk($normalized)) {
            return 'Olá! Posso te ajudar com tradução juramentada, documentos para cidadania, estudo, imigração, orçamento, prazos e envio de arquivos. Me diga qual documento você precisa traduzir e para qual país ou idioma.';
        }

        $asksDate = preg_match('/\b(que dia|qual dia|data de hoje|dia e hoje|hoje e que dia)\b/', $normalized);
        $asksTime = preg_match('/\b(que horas|qual hora|horas sao|hora atual|agora sao)\b/', $normalized);

        if ($asksDate && $asksTime) {
            return 'Hoje é ' . $this->formatDate($now) . ' e agora são ' . $now->format('H:i') . '.';
        }

        if ($asksDate) {
            return 'Hoje é ' . $this->formatDate($now) . '.';
        }

        if ($asksTime) {
            return 'Agora são ' . $now->format('H:i') . '.';
        }

        return $this->answerBusinessQuestion($normalized, $history);
    }

    private function answerBusinessQuestion(string $normalized, array $history): ?string
    {
        if (preg_match('/\b(criar conta|fazer cadastro|me cadastrar|cadastro|abrir conta|registrar|registrar conta)\b/', $normalized)) {
            return 'Para criar uma conta, use o botão Criar conta ou Cadastrar no site. Preencha seus dados, escolha seu perfil e confirme o envio. Se você for profissional, a validação pode depender da análise dos dados da OAB antes de liberar recursos completos.';
        }

        if (preg_match('/\b(entrar|login|logar|acessar conta|minha conta|acesso)\b/', $normalized)) {
            return 'Para entrar, clique em Entrar e informe e-mail e senha. Se estiver usando cadastro pelo Google, use a opção correspondente na tela de login. Se não lembrar a senha, use Recuperar senha antes de tentar criar outra conta.';
        }

        if (preg_match('/\b(esqueci senha|esqueci minha senha|perdi senha|perdi minha senha|recuperar senha|trocar senha|mudar senha|resetar senha|senha)\b/', $normalized)) {
            return 'Para recuperar a senha, abra a tela de login e escolha Recuperar senha. Informe o e-mail cadastrado e siga as instruções. Se você ainda estiver logado, também pode procurar a área de perfil para alterar a senha com segurança.';
        }

        if (preg_match('/\b(enviar documento|mandar documento|subir documento|upload|anexar documento|analisar documento|documento para analise)\b/', $normalized)) {
            return 'Para enviar um documento, entre na sua conta e use a área de envio de documentos. Prefira PDF ou imagem nítida, completa e sem cortes. Antes de enviar, confirme se não há páginas faltando e autorize a análise por IA somente se estiver de acordo com os termos.';
        }

        if (preg_match('/\b(acompanhar|andamento|status|solicitacao|solicitacoes|pedido|meus pedidos|caso|casos)\b/', $normalized)) {
            return 'Depois de entrar na conta, acompanhe seus pedidos pela área de solicitações ou casos. Lá você pode ver status, mensagens, documentos relacionados e próximas ações. Se houver advogado responsável, use o chat do caso para continuar o atendimento.';
        }

        if (preg_match('/\b(contato|suporte|falar com alguem|atendimento|ajuda humana|email|telefone)\b/', $normalized)) {
            return 'Para falar com o time, use a página Contato do site. Descreva o assunto de forma objetiva e evite enviar CPF, senha, número de processo ou documentos sigilosos em mensagens abertas.';
        }

        if (preg_match('/\b(perfil cliente|perfil advogado|advogado|cliente|administrador|admin|oab)\b/', $normalized)) {
            return 'O JusTraduz organiza o acesso por perfil. Cliente envia documentos e acompanha solicitações. Advogado atende casos e interage com clientes quando validado. Administrador gerencia usuários, auditoria e validações.';
        }

        if (preg_match('/\b(como funciona|usar o site|usar a plataforma|tutorial|passo a passo|primeiros passos|onde comeco|começar)\b/', $normalized)) {
            return 'Para começar: crie sua conta, entre no painel, envie um documento legível, leia a explicação inicial e abra uma solicitação se precisar de atendimento. Depois, acompanhe tudo pela área de casos, mensagens e notificações.';
        }

        if (preg_match('/\b(ruim de entender|nao entendi|nao consigo entender|dificil de entender|confuso|linguagem dificil|juridiqu[eê]s|explicar documento|entender documento|resumir documento)\b/', $normalized)) {
            return 'Posso ajudar a transformar o documento em linguagem simples. Primeiro, envie o arquivo ou uma foto legível pelo JusTraduz. Se preferir descrever aqui, não envie CPF, nomes completos, número de processo ou dados sigilosos. Diga só o tipo de documento e qual parte está difícil: prazo, valor, obrigação, multa, assinatura ou próximos passos.';
        }

        if (preg_match('/\b(ajuda (?:para|com|no|em) (?:meu )?documento|me ajuda (?:com|no|em) (?:meu )?documento|preciso de ajuda (?:com|no|em) (?:meu )?documento|meu documento)\b/', $normalized)) {
            return 'Claro. Posso ajudar de três formas: explicar o documento em linguagem simples, orientar se ele pode precisar de tradução simples ou juramentada, ou indicar quais informações faltam para orçamento. Para começar, diga que tipo de documento é e qual é sua dúvida principal. Não envie CPF, número de processo, nomes completos ou dados sigilosos aqui.';
        }

        if (preg_match('/\b(me ajude a traduzir|quero traduzir|preciso traduzir|traduzir um documento|traduzir meu documento|fazer traducao|fazer uma traducao)\b/', $normalized)) {
            return 'Claro. Para orientar a tradução, preciso de quatro informações: qual é o documento, em qual idioma ele está, para qual idioma você precisa traduzir e onde ele será usado. Se for para consulado, universidade, cartório, processo ou órgão público, pode ser necessário avaliar tradução juramentada.';
        }

        if (preg_match('/\b(valor exato|preco exato|quanto custa exatamente|qual o valor exato)\b/', $normalized)) {
            return 'Para informar valor exato, precisamos analisar o arquivo, o idioma, a quantidade de páginas/laudas, o prazo e se precisa de tradução juramentada. Você pode enviar o documento pelo JusTraduz para receber um orçamento correto.';
        }

        if (preg_match('/\b(garante|garantia|visto sera aprovado|imigracao vai aceitar|sera aceito)\b/', $normalized)) {
            return 'Não dá para garantir aprovação de visto, cidadania, imigração ou aceite por um órgão externo. O correto é conferir as exigências do destino e fazer a tradução no formato solicitado. O JusTraduz ajuda a analisar o documento e encaminhar para atendimento humano quando necessário.';
        }

        if (preg_match('/\b(traducao simples|diferenca entre traducao simples e juramentada)\b/', $normalized)) {
            return 'Tradução simples é indicada quando você precisa entender o conteúdo ou usar o texto sem exigência oficial. Se o documento será entregue a consulado, universidade, cartório, órgão público ou processo, confirme antes se pedem tradução juramentada. Para orientar melhor, diga qual é o documento, o idioma atual, o idioma desejado e onde ele será usado.';
        }

        if (preg_match('/\b(quanto tempo|prazo|urgente|amanha|30 dias)\b/', $normalized)) {
            $context = $this->historyText($history);
            if (
                strpos($normalized, 'diploma') !== false ||
                strpos($normalized, 'historico') !== false ||
                strpos($context, 'diploma') !== false ||
                strpos($context, 'historico') !== false
            ) {
                return 'Para diploma ou histórico escolar, o prazo depende do idioma, volume, legibilidade e necessidade de tradução juramentada. Como você mencionou esse contexto, o ideal é enviar os arquivos para avaliarmos prazo e viabilidade, principalmente se houver urgência.';
            }

            return 'O prazo depende do tipo de documento, idioma, volume, legibilidade e urgência. Para confirmar tempo de entrega, envie o arquivo e informe quando precisa usar a tradução.';
        }

        if (preg_match('/\b(orcamento|gratuito|quanto custa|preco|desconto|parcelar|pagamento)\b/', $normalized)) {
            return 'O orçamento pode depender do documento, idioma, volume, urgência e necessidade de tradução juramentada. Para vários documentos, pode haver avaliação conjunta. Envie os arquivos para receber uma proposta; formas de pagamento e parcelamento devem ser confirmadas no atendimento.';
        }

        if (preg_match('/\b(traducao juramentada|traducao publica)\b/', $normalized)) {
            return 'Tradução juramentada é a tradução oficial feita por tradutor público habilitado. Ela costuma ser exigida quando o documento precisa ter validade perante órgãos públicos, universidades, cartórios, processos, consulados ou autoridades estrangeiras.';
        }

        if (preg_match('/\b(certidao de nascimento|certidao de casamento|diploma|historico escolar|contrato)\b/', $normalized)) {
            return 'Esse tipo de documento geralmente pode ser traduzido. Para confirmar se precisa ser juramentado, precisamos saber o país de destino, o idioma, o órgão que vai receber e se há alguma exigência específica. Você pode enviar PDF ou foto legível para análise e orçamento.';
        }

        if (preg_match('/\b(enviar|envio|foto|celular|pdf|arquivo|documento ilegivel|ilegivel|documento ruim|documento borrado|foto ruim|cortado|baixa qualidade)\b/', $normalized)) {
            return 'Você pode enviar o documento em PDF ou imagem pelo celular, desde que esteja completo e legível. Se estiver cortado, borrado ou ilegível, a análise pode ficar limitada e talvez seja necessário reenviar uma versão melhor.';
        }

        if (preg_match('/\b(portugal|italiana|italia|espanha|ingles|cidadania|imigracao|estudar|universidade)\b/', $normalized)) {
            return 'Para estudo, cidadania ou uso no exterior, normalmente é preciso verificar a regra do país, universidade, consulado ou órgão que receberá o documento. Diploma, histórico escolar e certidões muitas vezes precisam de tradução juramentada, mas a exigência final depende do destino.';
        }

        return null;
    }

    private function fallbackAnswer(string $message): string
    {
        $business = $this->answerBusinessQuestion($this->normalizeText($message), []);
        if ($business !== null) {
            return $business;
        }

        return 'Posso te ajudar com tradução juramentada, tradução simples, documentos para cidadania, estudo, imigração, orçamento, prazos e envio de arquivos. Para eu orientar melhor, me diga: qual documento você tem, em qual idioma ele está e onde você pretende usar?';
    }

    private function isUnsafeRequest(string $normalized): bool
    {
        return preg_match('/\b(senha (?:do|de) (?:admin|administrador|sistema|outro usuario)|senhas (?:do|de) (?:admin|administrador|sistema|usuarios)|administrador|admin|banco de dados|database|delete|drop table|todos os usuarios|dados de outros clientes|documentos do sistema|acesso total)\b/', $normalized) === 1;
    }

    private function containsSensitiveData(string $message): bool
    {
        $patterns = [
            '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
            '/\b\d{7}-?\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}\b/',
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            '/(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?(?:9\s*)?\d{4}[-\s]?\d{4}\b/',
            '/\b(?:senha|password|token|chave de acesso)\s*[:=]\s*\S+/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isLegalEmergency(string $normalized): bool
    {
        return preg_match(
            '/\b(preso|prisao|mandado de prisao|violencia|ameaca|risco de vida|audiencia (?:e )?(hoje|amanha)|intimacao (?:e )?(hoje|agora)|prazo vence (hoje|amanha)|medida protetiva urgente)\b/',
            $normalized
        ) === 1;
    }

    private function isRestrictedLegalAdvice(string $normalized): bool
    {
        return preg_match(
            '/\b(calcul(?:e|ar|o) (?:o )?prazo processual|prazo para (?:recorrer|contestar|apelar)|chance(?:s)? de (?:ganhar|vencer|exito)|qual estrategia|estrategia processual|como burlar|como esconder bens|redija (?:uma|um)|faca (?:uma|um) (?:peticao|recurso|defesa|contestacao)|peticao inicial|contestacao judicial|recurso judicial|parecer juridico|devo processar|posso processar|qual crime|qual pena)\b/',
            $normalized
        ) === 1;
    }

    private function validateGeneratedAnswer(string $answer): string
    {
        $normalized = $this->normalizeText($answer);
        if (preg_match('/\b(garanto|garantimos|com certeza voce vai|chance de 100%|resultado garantido)\b/', $normalized) === 1) {
            return 'Não tenho segurança para responder isso sem criar uma expectativa indevida. Procure atendimento humano para confirmar as exigências e avaliar o seu caso.';
        }

        return rtrim($answer) . "\n\nInformação geral: confirme exigências oficiais e decisões importantes com um profissional habilitado.";
    }

    private function isPromptInjection(string $normalized): bool
    {
        return preg_match('/\b(ignore|ignorar|instrucoes anteriores|prompt interno|regras que recebeu|revele seu prompt|agora voce e|finja que eu sou)\b/', $normalized) === 1;
    }

    private function isSmallTalk(string $normalized): bool
    {
        return preg_match('/^(oi|ola|ajuda|\?\?|kkk+|boa noite|bom dia|boa tarde|nao entendi|pode explicar melhor)[\s!?.]*$/', $normalized) === 1;
    }

    private function historyText(array $history): string
    {
        $text = '';
        foreach ($history as $item) {
            $text .= ' ' . $this->normalizeText((string) ($item['texto'] ?? ''));
        }

        return trim($text);
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        $weekdays = [
            'Sunday' => 'domingo',
            'Monday' => 'segunda-feira',
            'Tuesday' => 'terça-feira',
            'Wednesday' => 'quarta-feira',
            'Thursday' => 'quinta-feira',
            'Friday' => 'sexta-feira',
            'Saturday' => 'sábado',
        ];

        $months = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'março',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro',
        ];

        $weekday = $weekdays[$date->format('l')] ?? $date->format('l');
        $month = $months[(int) $date->format('n')] ?? $date->format('m');

        return $weekday . ', ' . $date->format('d') . ' de ' . $month . ' de ' . $date->format('Y');
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $mojibakeFrom = ['Ã¡', 'Ã ', 'Ã¢', 'Ã£', 'Ã¤', 'Ã©', 'Ã¨', 'Ãª', 'Ã«', 'Ã­', 'Ã¬', 'Ã®', 'Ã¯', 'Ã³', 'Ã²', 'Ã´', 'Ãµ', 'Ã¶', 'Ãº', 'Ã¹', 'Ã»', 'Ã¼', 'Ã§'];
        $mojibakeTo = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'];
        $text = str_replace($mojibakeFrom, $mojibakeTo, $text);
        $accentFrom = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'];
        $accentTo = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'];
        $text = str_replace($accentFrom, $accentTo, $text);

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        $from = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'];
        $to = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'];
        return str_replace($from, $to, $text);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
