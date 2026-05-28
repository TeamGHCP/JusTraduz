<?php
session_start();

require_once "../../backend/app/middlewares/AuthMiddleware.php";
AuthMiddleware::verificar('admin');

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Painel do Administrador — JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png" />
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body>
  <div class="dash">
    <aside class="sidebar">
      <div class="brand"><img src="../assets/img/logo.png" alt="JusTraduz" /></div>
      <h4>Administração</h4>
      <ul>
        <li><a href="../admin/dashboard-admin.php" class="active">📊 Visão geral</a></li>
        <li><a href="#">👥 Usuários (clientes)</a></li>
        <li><a href="#">👨‍⚖️ Advogados</a></li>
        <li><a href="#">🎓 Estagiários</a></li>
        <li><a href="#">📄 Documentos</a></li>
        <li><a href="#">🆘 Solicitações</a></li>
        <li><a href="#">🛡️ Permissões e níveis</a></li>
        <li><a href="#">📈 Métricas</a></li>
      </ul>
      <h4>Sistema</h4>
      <ul>
        <li><a href="#">⚙️ Configurações</a></li>
        <li><a href="#">📜 Logs</a></li>
        <li><a href="../../backend/public/index.php?rota=/auth/logout">🚪 Sair</a></li>
      </ul>
    </aside>
    <main class="dash-main">
      <div class="dash-header">
        <div>
          <h1>Olá, <?= htmlspecialchars($_SESSION['nome']); ?> 👨‍💻</h1>
          <p class="card-sub">Visão geral da plataforma JusTraduz</p>
        </div>
        <div class="user">
          <span>Admin</span>
          <div class="avatar" style="background:var(--navy)">
            <?= mb_substr(htmlspecialchars($_SESSION['nome']), 0, 1); ?>
          </div>
        </div>
      </div> <div class="grid grid-4" style="margin-bottom:24px">
        <div class="stat"><div class="label">Usuários ativos</div><div class="value">1.248</div></div>
        <div class="stat"><div class="label">Advogados</div><div class="value accent">37</div></div>
        <div class="stat"><div class="label">Documentos processados</div><div class="value">5.612</div></div>
        <div class="stat"><div class="label">Solicitações abertas</div><div class="value">42</div></div>
      </div>

      <div class="grid grid-2">
        <div class="card">
          <h3 class="card-title">Usuários recentes</h3>
          <p class="card-sub" style="margin-bottom:14px">Últimos cadastros na plataforma</p>
          <table class="table">
            <thead><tr><th>Nome</th><th>Tipo</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <tr><td>Maria Silva</td><td>Cliente</td><td><span class="badge badge-success">Ativo</span></td><td><a href="#" class="btn-sm">Editar</a></td></tr>
              <tr><td>Dr. João Ribeiro</td><td>Advogado</td><td><span class="badge badge-success">Ativo</span></td><td><a href="#" class="btn-sm">Editar</a></td></tr>
              <tr><td>Pedro Souza</td><td>Estagiário</td><td><span class="badge badge-warning">Pendente</span></td><td><a href="#" class="btn-sm">Editar</a></td></tr>
              <tr><td>Carla Mendes</td><td>Cliente</td><td><span class="badge badge-success">Ativo</span></td><td><a href="#" class="btn-sm">Editar</a></td></tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h3 class="card-title">Funcionários — gestão</h3>
          <p class="card-sub" style="margin-bottom:14px">Apenas administradores podem alterar permissões.</p>
          <table class="table">
            <thead><tr><th>Funcionário</th><th>Nível</th><th></th></tr></thead>
            <tbody>
              <tr><td>Dr. João Ribeiro</td><td><span class="badge badge-info">Advogado</span></td><td><a href="#" class="btn btn-outline btn-sm">Alterar nível</a></td></tr>
              <tr><td>Dra. Ana Martins</td><td><span class="badge badge-info">Advogado</span></td><td><a href="#" class="btn btn-outline btn-sm">Alterar nível</a></td></tr>
              <tr><td>Pedro Souza</td><td><span class="badge badge-warning">Estagiário</span></td><td><a href="#" class="btn btn-outline btn-sm">Alterar nível</a></td></tr>
              <tr><td>Admin Master</td><td><span class="badge badge-danger">Admin</span></td><td><a href="#" class="btn btn-outline btn-sm">Alterar nível</a></td></tr>
            </tbody>
          </table>
          <div style="margin-top:14px; text-align:right">
            <button class="btn btn-primary btn-sm">+ Novo funcionário</button>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:24px">
        <h3 class="card-title">📜 Logs recentes do sistema</h3>
        <table class="table">
          <thead><tr><th>Hora</th><th>Tipo</th><th>Mensagem</th></tr></thead>
          <tbody>
            <tr><td>22/05 10:32</td><td><span class="badge badge-success">INFO</span></td><td>Novo usuário cadastrado: maria@email.com</td></tr>
            <tr><td>22/05 10:28</td><td><span class="badge badge-warning">WARN</span></td><td>Tentativa de upload acima do limite (62 MB)</td></tr>
            <tr><td>22/05 09:55</td><td><span class="badge badge-danger">ERROR</span></td><td>Falha ao acessar API de IA (timeout)</td></tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>