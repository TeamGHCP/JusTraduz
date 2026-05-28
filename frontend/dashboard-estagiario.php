<?php
session_start();

require_once "../backend/app/middlewares/AuthMiddleware.php";
AuthMiddleware::verificar('estagiario');

$nome_usuario = $_SESSION['nome'];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Painel do Estagiário — JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <div class="dash">
    <aside class="sidebar">
      <div class="brand"><img src="assets/img/logo.png" alt="JusTraduz" /></div>
      <h4>Menu</h4>
      <ul>
        <li><a href="dashboard-estagiario.html" class="active">🏠 Início</a></li>
        <li><a href="#">❓ Dúvidas dos usuários</a></li>
        <li><a href="chat.html">💬 Conversas</a></li>
        <li><a href="#">📚 Materiais de apoio</a></li>
      </ul>
      <h4>Conta</h4>
      <ul>
        <li><a href="perfil.html">⚙️ Meu Perfil</a></li>
        <li><a href="login.html">🚪 Sair</a></li>
      </ul>
    </aside>

    <main class="dash-main">
      <div class="dash-header">
        <div>
          <h1>Olá, <?= htmlspecialchars($nome_usuario); ?> 👋</h1>
          <p class="card-sub">Estagiário · Suporte a dúvidas simples</p>
        </div>
        <div class="user"><span>Estagiário</span><div class="avatar"><?= mb_substr(htmlspecialchars($nome_usuario), 0, 1); ?></div></div>
      </div>

      <div class="alert alert-warn">
        ⚠️ Como estagiário, você pode auxiliar usuários em <strong>dúvidas simples e suporte básico</strong>. Você <strong>não pode</strong> encerrar casos ou alterar informações críticas do sistema.
      </div>

      <div class="grid grid-3" style="margin-bottom:24px">
        <div class="stat"><div class="label">Dúvidas atendidas hoje</div><div class="value accent">6</div></div>
        <div class="stat"><div class="label">Aguardando resposta</div><div class="value">4</div></div>
        <div class="stat"><div class="label">Total no mês</div><div class="value">87</div></div>
      </div>

      <div class="card" style="padding:0; overflow:hidden">
        <div style="padding:16px 22px; border-bottom:1px solid var(--border)">
          <h3 class="card-title">Dúvidas aguardando atendimento</h3>
        </div>
        <table class="table">
          <thead><tr><th>#</th><th>Usuário</th><th>Assunto</th><th>Recebida</th><th></th></tr></thead>
          <tbody>
            <tr>
              <td>#2210</td><td>Maria Silva</td>
              <td>Como interpretar uma notificação?</td>
              <td>há 5 min</td>
              <td><a href="chat.html" class="btn btn-primary btn-sm">Atender</a></td>
            </tr>
            <tr>
              <td>#2209</td><td>Carla Mendes</td>
              <td>Dúvida sobre prazo de contestação</td>
              <td>há 18 min</td>
              <td><a href="chat.html" class="btn btn-primary btn-sm">Atender</a></td>
            </tr>
            <tr>
              <td>#2208</td><td>Roberto Lima</td>
              <td>Onde encontro meus documentos?</td>
              <td>há 32 min</td>
              <td><a href="chat.html" class="btn btn-primary btn-sm">Atender</a></td>
            </tr>
            <tr>
              <td>#2207</td><td>Júlia Costa</td>
              <td>Erro ao enviar PDF</td>
              <td>há 1 h</td>
              <td><a href="chat.html" class="btn btn-primary btn-sm">Atender</a></td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
