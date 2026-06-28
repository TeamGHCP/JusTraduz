<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$q = trim((string) ($_GET['q'] ?? ''));
$uf = strtoupper(trim((string) ($_GET['uf'] ?? '')));
$onlyAvailable = (string) ($_GET['disponivel'] ?? '') === '1';

$ufs = fetch_all(
    $pdo,
    "SELECT DISTINCT oab_uf
     FROM users
     WHERE tipo = 'advogado' AND status = 'ativo' AND oab_verificado = TRUE AND oab_uf IS NOT NULL AND oab_uf <> ''
     ORDER BY oab_uf"
);

$where = ["u.tipo = 'advogado'", "u.status = 'ativo'", 'u.oab_verificado = TRUE'];
$params = [];

if ($q !== '') {
    $where[] = '(u.nome LIKE ? OR u.email LIKE ? OR u.oab LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if ($uf !== '') {
    $where[] = 'u.oab_uf = ?';
    $params[] = $uf;
}

if ($onlyAvailable) {
    $where[] = "EXISTS (
        SELECT 1
        FROM schedule_slots sx
        WHERE sx.professional_id = u.id
          AND sx.status = 'livre'
          AND sx.starts_at >= NOW()
    )";
}

$lawyers = fetch_all(
    $pdo,
    "SELECT u.id, u.nome, u.email, u.telefone, u.foto_perfil, u.google_picture, u.oab, u.oab_uf, u.oab_status,
            (SELECT COUNT(*) FROM cases c WHERE c.advogado_id = u.id AND c.status <> 'finalizado') AS active_cases,
            (SELECT COUNT(*) FROM schedule_slots s WHERE s.professional_id = u.id AND s.status = 'livre' AND s.starts_at >= NOW()) AS free_slots,
            (SELECT MIN(s.starts_at) FROM schedule_slots s WHERE s.professional_id = u.id AND s.status = 'livre' AND s.starts_at >= NOW()) AS next_free_at
     FROM users u
     WHERE " . implode(' AND ', $where) . "
     ORDER BY free_slots DESC, active_cases ASC, u.nome ASC",
    $params
);

function directory_lawyer_photo(array $lawyer): string
{
    $localPath = trim((string) ($lawyer['foto_perfil'] ?? ''));
    $googlePath = trim((string) ($lawyer['google_picture'] ?? ''));
    $path = ($localPath !== '' && (preg_match('#^https?://#i', $localPath) || is_file(PROJECT_ROOT_PATH . '/' . ltrim($localPath, '/'))))
        ? $localPath
        : $googlePath;
    if ($path === '') {
        return '';
    }

    return preg_match('#^https?://#i', $path) ? $path : '../' . ltrim($path, '/');
}

function directory_datetime(?string $value): string
{
    if (!$value) {
        return 'Sem horário livre';
    }

    return date('d/m/Y H:i', strtotime($value));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Advogados | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=global-responsive-20260628">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'lista-advogados.php'); ?>

    <main class="app-main">
      <?php render_topbar('Advogados verificados', 'Profissionais ativos, validados e prontos para receber solicitações.', current_user_name()); ?>

      <section class="lawyer-directory-hero">
        <div>
          <span class="badge badge-success">OAB validada</span>
          <h2><?= e((string) count($lawyers)) ?> profissionais encontrados</h2>
          <p>Use a lista para direcionar atendimento com contexto. Para cliente, o botão já abre a solicitação com o advogado escolhido.</p>
        </div>
        <?php if ($type === 'cliente'): ?>
          <a class="btn btn-primary" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Solicitar ajuda aberta</a>
        <?php else: ?>
          <a class="btn btn-outline" href="<?= e(dashboard_url($type)) ?>">Voltar ao dashboard</a>
        <?php endif; ?>
      </section>

      <form class="card admin-filter lawyer-directory-filter" method="get">
        <div class="field">
          <label for="q">Busca</label>
          <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Nome, email ou OAB">
        </div>
        <div class="field">
          <label for="uf">UF da OAB</label>
          <select class="select" id="uf" name="uf">
            <option value="">Todas</option>
            <?php foreach ($ufs as $item): ?>
              <?php $itemUf = strtoupper((string) ($item['oab_uf'] ?? '')); ?>
              <option value="<?= e($itemUf) ?>" <?= $uf === $itemUf ? 'selected' : '' ?>><?= e($itemUf) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="checkline directory-check">
          <input type="checkbox" name="disponivel" value="1" <?= $onlyAvailable ? 'checked' : '' ?>>
          <span>Com horário livre</span>
        </label>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="lista-advogados.php">Limpar</a>
        </div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Lista de profissionais</h2>
          <span class="badge badge-info"><?= e((string) count($lawyers)) ?> registros</span>
        </div>
        <?php if (!$lawyers): ?>
          <?= empty_state('Nenhum advogado encontrado para os filtros atuais.') ?>
        <?php else: ?>
          <div class="lawyer-directory-grid">
            <?php foreach ($lawyers as $lawyer): ?>
              <?php $photoUrl = directory_lawyer_photo($lawyer); ?>
              <article class="lawyer-directory-card">
                <div class="lawyer-directory-head">
                  <div class="lawyer-avatar lawyer-avatar-large">
                    <span class="avatar-initial"><?= e(strtoupper(substr((string) $lawyer['nome'], 0, 1))) ?></span>
                    <?php if ($photoUrl): ?>
                      <img src="<?= e($photoUrl) ?>" alt="<?= e($lawyer['nome']) ?>" referrerpolicy="no-referrer" onerror="this.remove()">
                    <?php endif; ?>
                  </div>
                  <div>
                    <span class="badge badge-success">Validado</span>
                    <h3><?= e($lawyer['nome']) ?></h3>
                    <p><?= $lawyer['oab'] ? 'OAB/' . e($lawyer['oab_uf']) . ' ' . e($lawyer['oab']) : 'OAB informada pelo cadastro' ?></p>
                  </div>
                </div>

                <div class="case-meta-grid lawyer-directory-meta">
                  <div><span>Casos ativos</span><strong><?= e((string) (int) $lawyer['active_cases']) ?></strong></div>
                  <div><span>Horários livres</span><strong><?= e((string) (int) $lawyer['free_slots']) ?></strong></div>
                  <div><span>Proximo livre</span><strong><?= e(directory_datetime($lawyer['next_free_at'] ?? null)) ?></strong></div>
                  <div><span>Contato</span><strong><?= e($lawyer['telefone'] ?: 'Não informado') ?></strong></div>
                </div>

                <p class="text-muted"><?= e($lawyer['oab_status'] ?: 'Profissional ativo e aprovado pela administracao.') ?></p>

                <div class="case-actions">
                  <?php if ($type === 'cliente'): ?>
                    <a class="btn btn-primary btn-sm" href="solicitar-ajuda.php?advogado_id=<?= (int) $lawyer['id'] ?>"><?= icon_svg('help') ?> Solicitar atendimento</a>
                    <?php if ((int) $lawyer['free_slots'] > 0): ?>
                      <a class="btn btn-outline btn-sm" href="agenda.php?professional_id=<?= (int) $lawyer['id'] ?>"><?= icon_svg('calendar') ?> Ver agenda</a>
                    <?php endif; ?>
                  <?php else: ?>
                    <a class="btn btn-outline btn-sm" href="agenda.php?professional_id=<?= (int) $lawyer['id'] ?>"><?= icon_svg('calendar') ?> Agenda</a>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
