<?php

require_once dirname(__DIR__) . '/services/GeminiService.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class AiController
{
    public function csrf(): void
    {
        $this->json(['csrf' => CsrfMiddleware::token()]);
    }

    public function chat(): void
    {
        $payload = $this->readPayload();
        $message = $payload['message'];
        $history = $payload['history'];

        if ($message === '') {
            $this->json(['erro' => 'Digite uma mensagem para conversar com a IA.'], 422);
            return;
        }

        if (mb_strlen($message) > 1200) {
            $this->json(['erro' => 'Envie uma mensagem menor, com ate 1200 caracteres.'], 422);
            return;
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

        $this->json([
            'resposta' => $answer,
            'modelo' => $gemini->modelName(),
        ]);
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

        if ($this->isUnsafeRequest($normalized)) {
            return 'Nao posso ajudar com acesso a dados internos, documentos de clientes, senhas, banco de dados, comandos destrutivos ou tentativas de burlar o sistema. Posso ajudar com duvidas sobre traducao, documentos, orcamento e uso do JusTraduz.';
        }

        if ($this->isPromptInjection($normalized)) {
            return 'Nao posso ignorar minhas instrucoes, revelar regras internas ou assumir papel de administrador. Posso continuar ajudando como assistente do JusTraduz para traducao, documentos e atendimento.';
        }

        if ($this->isSmallTalk($normalized)) {
            return 'Ola! Posso te ajudar com traducao juramentada, documentos para cidadania, estudo, imigracao, orcamento, prazos e envio de arquivos. Me diga qual documento voce precisa traduzir e para qual pais ou idioma.';
        }

        $asksDate = preg_match('/\b(que dia|qual dia|data de hoje|dia e hoje|hoje e que dia)\b/', $normalized);
        $asksTime = preg_match('/\b(que horas|qual hora|horas sao|hora atual|agora sao)\b/', $normalized);

        if ($asksDate && $asksTime) {
            return 'Hoje e ' . $this->formatDate($now) . ' e agora sao ' . $now->format('H:i') . '.';
        }

        if ($asksDate) {
            return 'Hoje e ' . $this->formatDate($now) . '.';
        }

        if ($asksTime) {
            return 'Agora sao ' . $now->format('H:i') . '.';
        }

        return $this->answerBusinessQuestion($normalized, $history);
    }

    private function answerBusinessQuestion(string $normalized, array $history): ?string
    {
        if (preg_match('/\b(valor exato|preco exato|quanto custa exatamente|qual o valor exato)\b/', $normalized)) {
            return 'Para informar valor exato, precisamos analisar o arquivo, o idioma, a quantidade de paginas/laudas, o prazo e se precisa de traducao juramentada. Voce pode enviar o documento pelo JusTraduz para receber um orcamento correto.';
        }

        if (preg_match('/\b(garante|garantia|visto sera aprovado|imigracao vai aceitar|sera aceito)\b/', $normalized)) {
            return 'Nao da para garantir aprovacao de visto, cidadania, imigracao ou aceite por um orgao externo. O correto e conferir as exigencias do destino e fazer a traducao no formato solicitado. O JusTraduz ajuda a analisar o documento e encaminhar para atendimento humano quando necessario.';
        }

        if (preg_match('/\b(traducao juramentada|traducao publica)\b/', $normalized)) {
            return 'Traducao juramentada e a traducao oficial feita por tradutor publico habilitado. Ela costuma ser exigida quando o documento precisa ter validade perante orgaos publicos, universidades, cartorios, processos, consulados ou autoridades estrangeiras.';
        }

        if (preg_match('/\b(traducao simples|diferenca entre traducao simples e juramentada)\b/', $normalized)) {
            return 'A traducao simples serve para entendimento, estudo interno ou uso nao oficial. A traducao juramentada tem validade oficial e costuma ser exigida por orgaos, universidades, consulados e processos formais.';
        }

        if (preg_match('/\b(certidao de nascimento|certidao de casamento|diploma|historico escolar|contrato)\b/', $normalized)) {
            return 'Esse tipo de documento geralmente pode ser traduzido. Para confirmar se precisa ser juramentado, precisamos saber o pais de destino, o idioma, o orgao que vai receber e se ha alguma exigencia especifica. Voce pode enviar PDF ou foto legivel para analise e orcamento.';
        }

        if (preg_match('/\b(orcamento|gratuito|quanto custa|preco|desconto|parcelar|pagamento)\b/', $normalized)) {
            return 'O orcamento pode depender do documento, idioma, volume, urgencia e necessidade de traducao juramentada. Para varios documentos, pode haver avaliacao conjunta. Envie os arquivos para receber uma proposta; formas de pagamento e parcelamento devem ser confirmadas no atendimento.';
        }

        if (preg_match('/\b(enviar|envio|foto|celular|pdf|arquivo|documento ilegivel|ilegivel)\b/', $normalized)) {
            return 'Voce pode enviar o documento em PDF ou imagem pelo celular, desde que esteja completo e legivel. Se estiver cortado, borrado ou ilegivel, a analise pode ficar limitada e talvez seja necessario reenviar uma versao melhor.';
        }

        if (preg_match('/\b(portugal|italiana|italia|espanha|ingles|cidadania|imigracao|estudar|universidade)\b/', $normalized)) {
            return 'Para estudo, cidadania ou uso no exterior, normalmente e preciso verificar a regra do pais, universidade, consulado ou orgao que recebera o documento. Diploma, historico escolar e certidoes muitas vezes precisam de traducao juramentada, mas a exigencia final depende do destino.';
        }

        if (preg_match('/\b(quanto tempo|prazo|urgente|amanha|30 dias)\b/', $normalized)) {
            $context = $this->historyText($history);
            if (strpos($context, 'diploma') !== false || strpos($context, 'historico') !== false) {
                return 'Para diploma ou historico escolar, o prazo depende do idioma, volume, legibilidade e necessidade de traducao juramentada. Como voce mencionou esse contexto antes, o ideal e enviar os arquivos para avaliarmos prazo e viabilidade, principalmente se houver urgencia.';
            }

            return 'O prazo depende do tipo de documento, idioma, volume, legibilidade e urgencia. Para confirmar tempo de entrega, envie o arquivo e informe quando precisa usar a traducao.';
        }

        return null;
    }

    private function fallbackAnswer(string $message): string
    {
        $business = $this->answerBusinessQuestion($this->normalizeText($message), []);
        if ($business !== null) {
            return $business;
        }

        return 'Posso te ajudar com traducao juramentada, traducao simples, documentos para cidadania, estudo, imigracao, orcamento, prazos e envio de arquivos. Para eu orientar melhor, me diga: qual documento voce tem, em qual idioma ele esta e onde voce pretende usar?';
    }

    private function isUnsafeRequest(string $normalized): bool
    {
        return preg_match('/\b(senha|administrador|admin|banco de dados|database|delete|drop table|todos os usuarios|dados de outros clientes|documentos do sistema|acesso total)\b/', $normalized) === 1;
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
            'Tuesday' => 'terca-feira',
            'Wednesday' => 'quarta-feira',
            'Thursday' => 'quinta-feira',
            'Friday' => 'sexta-feira',
            'Saturday' => 'sabado',
        ];

        $months = [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'marco',
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
