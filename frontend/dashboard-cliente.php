<?php
session_start();

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.html");
    exit();
}

$nome_usuario = $_SESSION['nome'];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Painel do Cliente — JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>

  <div class="dash">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="brand"><img src="assets/img/logo.png" alt="JusTraduz" /></div>

      <h4>Menu</h4>
      <ul>
        <li><a href="#" class="active">🏠 Início</a></li>
        <li><a href="#">📤 Enviar PDF</a></li>
        <li><a href="#">📚 Meus documentos</a></li>
        <li><a href="#">🆘 Solicitar ajuda</a></li>
        <li><a href="#">💬 Conversas</a></li>
      </ul>

      <h4>Conta</h4>
      <ul>
        <li><a href="#">⚙️ Configurações</a></li>
        <li><a href="login.html">🚪 Sair</a></li>
      </ul>
    </aside>

    <!-- Main -->
    <main class="dash-main">
      <div class="dash-header">
  
        <h1>Olá, <?= htmlspecialchars($nome_usuario); ?> 👋</h1>

        <div class="user">
          <span>Cliente</span>
          <div class="avatar"><?= mb_substr(htmlspecialchars($nome_usuario), 0, 1); ?></div>
        </div>
      </div>
      <div class="alert alert-warn">
        ⚠️ Lembrete: a IA do JusTraduz <strong>não substitui</strong> a orientação de um advogado.
      </div> 

      <!-- Stats -->
      <div class="grid grid-3" style="margin-bottom:28px;">
        <div class="stat">
          <div class="label">PDFs enviados</div>
          <div class="value">12</div>
        </div>
        <div class="stat">
          <div class="label">Solicitações abertas</div>
          <div class="value accent">2</div>
        </div>
        <div class="stat">
          <div class="label">Casos em andamento</div>
          <div class="value">1</div>
        </div>
      </div>

      <!-- Upload -->
      <div class="card" style="margin-bottom:24px;">
        <div class="card-title">Enviar novo documento</div>
        <p class="card-sub" style="margin-bottom:16px;">Apenas arquivos PDF. Tamanho máximo: 10 MB.</p>
        <form action="#" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <input type="file" name="arquivo" accept="application/pdf" class="form-control" />
          </div>
          <button type="submit" class="btn btn-primary">Analisar com IA</button>
        </form>
      </div>

      <!-- Histórico -->
      <div class="card">
        <div class="card-title" style="margin-bottom:14px;">Meus documentos recentes</div>
        <table class="table">
          <thead>
            <tr>
              <th>Arquivo</th>
              <th>Data</th>
              <th>Confiança IA</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>intimacao_processo_2025.pdf</td>
              <td>22/05/2026</td>
              <td>92%</td>
              <td><span class="badge badge-success">Traduzido</span></td>
              <td><a href="#" class="btn btn-outline btn-sm">Ver</a></td>
            </tr>
            <tr>
              <td>contrato_locacao.pdf</td>
              <td>20/05/2026</td>
              <td>87%</td>
              <td><span class="badge badge-success">Traduzido</span></td>
              <td><a href="#" class="btn btn-outline btn-sm">Ver</a></td>
            </tr>
            <tr>
              <td>sentenca_trabalhista.pdf</td>
              <td>18/05/2026</td>
              <td>—</td>
              <td><span class="badge badge-warning">Processando</span></td>
              <td><a href="#" class="btn btn-ghost btn-sm">Aguardar</a></td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>

</body>
</html>
