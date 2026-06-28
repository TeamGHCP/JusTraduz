<?php
$pathPrefix = '../';
$activePage = 'blog';
require_once __DIR__ . '/' . $pathPrefix . 'includes/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
    renderSeo([
      'title' => 'Os termos jurídicos mais comuns explicados em português simples | Blog JusTraduz',
      'description' => 'Glossário de termos jurídicos comuns traduzidos de forma didática. Entenda o que significam palavras difíceis de contratos e processos.',
      'canonical' => 'https://justraduz.com.br/blog/termos-juridicos-mais-comuns',
      'robots' => 'index, follow'
    ]);
  ?>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css?v=site-polish-20260625">
  <script src="../assets/js/cookie-consent.js?v=2026.06.25-1"></script>
</head>
<body class="blog-post-page terms-page-enhanced">
  <?php require __DIR__ . '/' . $pathPrefix . 'includes/header.php'; ?>

  <main>
    <article class="container" style="max-width: 800px; padding: 48px 16px 96px;">
      <header style="margin-bottom: 32px; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 24px;">
        <span style="font-size: 13px; text-transform: uppercase; color: var(--primary); font-weight: 700; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Terminologia</span>
        <h1 style="font-size: clamp(28px, 5vw, 42px); line-height: 1.15; color: #172033; margin: 0 0 16px;">Os termos jurídicos mais comuns explicados em português simples</h1>
        <div style="display: flex; gap: 16px; font-size: 14px; color: rgba(0,0,0,0.56); flex-wrap: wrap;">
          <span>Por: Time JusTraduz</span>
          <span>•</span>
          <span>Publicado em: 28 de Junho de 2026</span>
        </div>
      </header>

      <div class="blog-post-content" style="font-size: 16px; line-height: 1.75; color: rgba(0,0,0,0.85); display: grid; gap: 24px;">
        <p>
          Muitas pessoas desistem de ler contratos ou andamentos processuais ao encontrar termos incompreensíveis. Para ajudar a derrubar essa barreira, preparamos uma tradução simples das palavras jurídicas mais frequentes no dia a dia.
        </p>

        <h2 style="font-size: 24px; color: #172033; margin-top: 16px; margin-bottom: 8px;">1. Outorgante e Outorgado</h2>
        <p>
          Comuns em procurações e contratos de prestação de serviços:
        </p>
        <ul style="padding-left: 20px; display: grid; gap: 8px;">
          <li><strong>Outorgante:</strong> Aquele que concede o direito, poder ou autorização a outra pessoa (quem assina dando o poder).</li>
          <li><strong>Outorgado:</strong> Aquele que recebe a autorização ou o direito (quem passa a poder agir em nome do outro).</li>
        </ul>

        <h2 style="font-size: 24px; color: #172033; margin-top: 16px; margin-bottom: 8px;">2. Honorários de Sucumbência</h2>
        <p>
          Nos processos judiciais, a <strong>sucumbência</strong> representa a perda da ação. O juiz determina que a parte perdedora pague uma porcentagem ao advogado da parte vencedora. Essa taxa é chamada de honorários de sucumbência. Ela é fixada por lei e não deve ser confundida com os honorários contratuais combinados previamente com o seu advogado.
        </p>

        <h2 style="font-size: 24px; color: #172033; margin-top: 16px; margin-bottom: 8px;">3. Trânsito em Julgado</h2>
        <p>
          Significa que uma decisão judicial (sentença ou acórdão) tornou-se definitiva. <strong>Trânsito em julgado</strong> ocorre quando não cabem mais recursos contra aquela decisão, seja porque os prazos se esgotaram ou porque o caso já passou por todas as instâncias judiciais possíveis (como o STJ ou STF).
        </p>

        <h2 style="font-size: 24px; color: #172033; margin-top: 16px; margin-bottom: 8px;">4. Peça Exordial ou Petição Inicial</h2>
        <p>
          É o documento que dá início a um processo na Justiça. Nela, o advogado da parte autora relata os fatos ao juiz, aponta os fundamentos jurídicos do pedido e especifica o que está solicitando (como uma indenização ou obrigação de fazer).
        </p>

        <h2 style="font-size: 24px; color: #172033; margin-top: 16px; margin-bottom: 8px;">5. Rescisão e Resilição</h2>
        <p>
          Ambos os termos se referem ao encerramento de um contrato, mas com motivos diferentes:
        </p>
        <ul style="padding-left: 20px; display: grid; gap: 8px;">
          <li><strong>Rescisão:</strong> Ocorre quando o contrato é quebrado por descumprimento de uma das partes (ex: não pagamento do aluguel).</li>
          <li><strong>Resilição:</strong> Ocorre pelo simples acordo mútuo ou pela desistência voluntária de uma das partes, sem que tenha havido quebra de regras (ex: notificar desinteresse em continuar o serviço antes do prazo de renovação).</li>
        </ul>

        <p style="margin-top: 32px; border-top: 1px solid rgba(0,0,0,0.08); padding-top: 32px;">
          Decifrar esses termos no meio de parágrafos complexos é muito mais fácil usando a plataforma <a href="../index.php" style="color: var(--primary); font-weight: 600;">JusTraduz</a>. O explicador de cláusulas analisa os termos e exibe traduções didáticas lado a lado com o original de forma imediata.
        </p>

        <div class="feedback-card" style="border-left: 4px solid var(--primary); background: rgba(0,143,128,0.03); padding: 24px; margin-top: 24px;">
          <strong style="color: var(--primary); display: block; margin-bottom: 8px;">Aviso Informativo:</strong>
          <p style="font-size: 14px; margin: 0; line-height: 1.6;">
            As definições fornecidas neste glossário têm finalidade puramente educativa. Em caso de dúvidas sobre termos aplicados em notificações ou processos reais, consulte sempre a assessoria direta de um profissional de direito qualificado.
          </p>
        </div>

        <div style="margin-top: 32px; display: flex; gap: 16px; flex-wrap: wrap;">
          <a class="btn btn-primary" href="../login.html?cadastro">Enviar meu documento para IA</a>
          <a class="btn btn-outline" href="index.php">Voltar ao Blog</a>
        </div>
      </div>
    </article>
  </main>

  <?php require __DIR__ . '/' . $pathPrefix . 'includes/footer.php'; ?>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/accessibility.js?v=2026.06.14-06"></script>
  <script src="../assets/js/vlibras-init.js?v=2026.06.25-1" defer></script>
</body>
</html>
