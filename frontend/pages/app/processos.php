<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
if (!in_array($type, ['cliente', 'advogado', 'estagiario'], true)) {
    header('Location: ' . dashboard_url($type));
    exit;
}

$user = fetch_one(
    $pdo,
    'SELECT id, nome, tipo, cpf, oab, oab_uf, oab_verificado, oab_parametro FROM users WHERE id = ?',
    [current_user_id()]
) ?? [];

function process_page_is_open(array $process): bool
{
    $status = strtolower(trim((string) (($process['status_normalizado'] ?? '') ?: ($process['status_inferido'] ?? ''))));
    if ($status === '') {
        return true;
    }

    foreach (['arquivado', 'baixado', 'extinto', 'encerrado', 'finalizado', 'cancelado', 'transitado', 'julgado'] as $closedStatus) {
        if (str_contains($status, $closedStatus)) {
            return false;
        }
    }

    return true;
}

function process_page_status(array $process): string
{
    $status = trim((string) (($process['status_normalizado'] ?? '') ?: ($process['status_inferido'] ?? '')));
    return $status !== '' ? $status : 'Sem status';
}

function process_page_badge(array $process): string
{
    if (process_page_status($process) === 'Sem status') {
        return 'badge-info';
    }

    return process_page_is_open($process) ? 'badge-success' : 'badge-warning';
}

function process_page_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '-';
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

function process_page_subject(array $process): string
{
    foreach (['classe_processual', 'assunto', 'tipo_processo'] as $field) {
        $value = trim((string) ($process[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return 'Processo importado';
}

function process_page_source(array $process): string
{
    $source = strtolower(trim((string) ($process['source'] ?? 'jusbrasil')));
    return str_contains($source, 'demo') ? 'Demo' : 'Jusbrasil';
}

function process_page_env_ready(string $key): bool
{
    $value = getenv($key);
    if ($value === false) {
        $env = database_env_values(PROJECT_ROOT_PATH . '/backend/.env');
        $value = $env[$key] ?? '';
    }

    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    $upper = strtoupper($value);
    return !str_contains($upper, 'COLE_') && !str_contains($upper, 'SUA_CHAVE') && !str_contains($upper, 'SEU_TOKEN');
}

function process_page_mask_cpf(string $digits): string
{
    if (strlen($digits) !== 11) {
        return $digits !== '' ? $digits : 'Nao informado';
    }

    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?: $digits;
}

function process_page_scope_label(string $scope): string
{
    return match ($scope) {
        'abertos' => 'Em aberto',
        'encerrados' => 'Encerrados',
        default => 'Todos',
    };
}

$processes = [];
$tableReady = true;
$queryType = $type === 'cliente' ? 'cpf' : 'oab';
$ownerType = $type === 'cliente' ? 'cliente' : $type;

try {
    $processes = fetch_all(
        $pdo,
        'SELECT *
         FROM external_processes
         WHERE user_id = ? AND owner_type = ? AND query_type = ?
         ORDER BY COALESCE(data_andamento_mais_recente, data_ultima_atualizacao, DATE(last_synced_at), DATE(created_at)) DESC, id DESC',
        [current_user_id(), $ownerType, $queryType]
    );
} catch (Throwable $exception) {
    $tableReady = false;
}

$openProcesses = array_values(array_filter($processes, 'process_page_is_open'));
$closedProcesses = array_values(array_filter($processes, static fn (array $process): bool => !process_page_is_open($process)));
$scope = (string) ($_GET['status'] ?? ($type === 'cliente' ? 'abertos' : 'todos'));
if (!in_array($scope, ['abertos', 'encerrados', 'todos'], true)) {
    $scope = $type === 'cliente' ? 'abertos' : 'todos';
}

$visibleProcesses = match ($scope) {
    'abertos' => $openProcesses,
    'encerrados' => $closedProcesses,
    default => $processes,
};

$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') {
    $needle = strtolower($search);
    $visibleProcesses = array_values(array_filter($visibleProcesses, static function (array $process) use ($needle): bool {
        $haystack = strtolower(implode(' ', [
            $process['process_number'] ?? '',
            $process['tribunal'] ?? '',
            $process['uf'] ?? '',
            $process['comarca'] ?? '',
            $process['classe_processual'] ?? '',
            $process['assunto'] ?? '',
            $process['status_normalizado'] ?? '',
            $process['status_inferido'] ?? '',
        ]));

        return str_contains($haystack, $needle);
    }));
}

$lastSync = '';
$demoCount = 0;
foreach ($processes as $process) {
    $lastSyncValue = (string) ($process['last_synced_at'] ?? '');
    if ($lastSyncValue !== '' && ($lastSync === '' || strtotime($lastSyncValue) > strtotime($lastSync))) {
        $lastSync = $lastSyncValue;
    }

    if (process_page_source($process) === 'Demo') {
        $demoCount++;
    }
}

$cpfDigits = preg_replace('/\D+/', '', (string) ($user['cpf'] ?? '')) ?? '';
$oabDigits = preg_replace('/\D+/', '', (string) ($user['oab'] ?? '')) ?? '';
$oabUf = strtoupper(trim((string) ($user['oab_uf'] ?? '')));
$oabVerified = (int) ($user['oab_verificado'] ?? 0) === 1;
$identityReady = $type === 'cliente'
    ? strlen($cpfDigits) === 11
    : ($oabDigits !== '' && $oabUf !== '' && $oabVerified);
$integrationReady = $type === 'cliente'
    ? process_page_env_ready('JUSBRASIL_API_KEY')
    : process_page_env_ready('JUSBRASIL_OAB_TOKEN');

$identityLabel = $type === 'cliente'
    ? 'CPF ' . process_page_mask_cpf($cpfDigits)
    : 'OAB ' . trim($oabUf . ' ' . ($user['oab'] ?? ''));
$integrationLabel = $integrationReady ? 'Integracao configurada' : 'Modo demo seguro';
$topbarTitle = $type === 'cliente' ? 'Meus processos' : 'Processos por OAB';
$topbarSubtitle = $type === 'cliente'
    ? 'Listagem armazenada de processos encontrados pelo CPF cadastrado.'
    : 'Listagem armazenada de processos vinculados a sua OAB validada.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Processos | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-3">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'processos.php'); ?>

    <main class="app-main">
      <?php render_topbar($topbarTitle, $topbarSubtitle, current_user_name()); ?>

      <section class="client-command process-command">
        <article class="command-card command-card-primary">
          <span class="badge <?= $integrationReady ? 'badge-success' : 'badge-info' ?>"><?= e($integrationLabel) ?></span>
          <h2><?= $type === 'cliente' ? 'Consulta por CPF' : 'Consulta por OAB' ?></h2>
          <p><strong><?= e($identityLabel) ?></strong></p>

          <?php if (!$tableReady): ?>
            <div class="alert is-visible alert-error">A tabela de processos ainda nao existe neste banco. Importe um dos SQLs consolidados em database/.</div>
          <?php elseif (!$identityReady && $type === 'cliente'): ?>
            <div class="form-actions">
              <a class="btn btn-primary" href="perfil.php"><?= icon_svg('user') ?> Cadastrar CPF</a>
            </div>
          <?php elseif (!$identityReady): ?>
            <div class="alert is-visible alert-info">Sua OAB precisa estar validada antes de qualquer sincronizacao externa.</div>
          <?php elseif (!$integrationReady): ?>
            <div class="alert is-visible alert-info">A credencial externa nao esta configurada neste ambiente. A tela continua funcionando com processos ja importados e dados demo.</div>
          <?php else: ?>
            <form class="form-actions" action="<?= e(app_url('/backend/public/index.php?rota=/processes/sync')) ?>" method="post">
              <?= csrf_input() ?>
              <button class="btn btn-primary" type="submit"><?= icon_svg('download') ?> Sincronizar Jusbrasil</button>
            </form>
          <?php endif; ?>
        </article>

        <article class="command-card">
          <span>Listagem armazenada</span>
          <strong><?= e((string) count($processes)) ?></strong>
          <p><?= e((string) count($openProcesses)) ?> em aberto, <?= e((string) count($closedProcesses)) ?> encerrado(s).</p>
        </article>

        <article class="command-card">
          <span>Ultima sincronizacao</span>
          <strong><?= e($lastSync ? process_page_date($lastSync, true) : '-') ?></strong>
          <p><?= $demoCount > 0 ? e((string) $demoCount) . ' registro(s) de demo disponiveis.' : 'Sem dados demo nesta consulta.' ?></p>
        </article>
      </section>

      <section class="grid grid-4">
        <?= stat_card('Importados', count($processes), 'file') ?>
        <?= stat_card('Em aberto', count($openProcesses), 'case') ?>
        <?= stat_card('Encerrados', count($closedProcesses), 'check') ?>
        <?= stat_card('Consulta', strtoupper($queryType), 'chart') ?>
      </section>

      <form class="card admin-filter process-filter" method="get">
        <div>
          <label for="process-search">Buscar</label>
          <input id="process-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Numero, tribunal, assunto">
        </div>
        <div>
          <label for="process-status">Status</label>
          <select id="process-status" name="status">
            <option value="abertos" <?= $scope === 'abertos' ? 'selected' : '' ?>>Em aberto</option>
            <option value="todos" <?= $scope === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="encerrados" <?= $scope === 'encerrados' ? 'selected' : '' ?>>Encerrados</option>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon_svg('file') ?> Filtrar</button>
          <a class="btn btn-outline" href="processos.php">Limpar</a>
        </div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title">
          <div>
            <h2><?= e(process_page_scope_label($scope)) ?></h2>
            <p class="text-muted"><?= e((string) count($visibleProcesses)) ?> registro(s) exibidos</p>
          </div>
          <?php if ($demoCount > 0): ?>
            <span class="badge badge-info">Modo demo</span>
          <?php endif; ?>
        </div>

        <?php if (!$tableReady): ?>
          <?= empty_state('Execute database/justraduz_completo_sem_demo.sql ou database/justraduz_completo_com_demo.sql para habilitar esta tela.') ?>
        <?php elseif (!$processes): ?>
          <?= empty_state('Nenhum processo armazenado ainda. Importe o SQL com demo ou configure a integracao externa para sincronizar.') ?>
        <?php elseif (!$visibleProcesses): ?>
          <?= empty_state('Nenhum processo encontrado para os filtros atuais.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Processo</th>
                  <th>Status</th>
                  <th>Tribunal</th>
                  <th>Fonte</th>
                  <th>Ultima atualizacao</th>
                  <th>Acao</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($visibleProcesses as $process): ?>
                  <tr>
                    <td>
                      <strong><?= e($process['process_number'] ?? '') ?></strong>
                      <span class="table-subtext"><?= e(process_page_subject($process)) ?></span>
                    </td>
                    <td><span class="badge <?= e(process_page_badge($process)) ?>"><?= e(process_page_status($process)) ?></span></td>
                    <td>
                      <?= e((string) (($process['tribunal'] ?? '') ?: '-')) ?>
                      <span class="table-subtext"><?= e(trim((string) (($process['uf'] ?? '') . ' ' . ($process['comarca'] ?? ''))) ?: '-') ?></span>
                    </td>
                    <td><span class="badge <?= process_page_source($process) === 'Demo' ? 'badge-info' : 'badge-success' ?>"><?= e(process_page_source($process)) ?></span></td>
                    <td>
                      <?= e(process_page_date($process['data_andamento_mais_recente'] ?? null)) ?>
                      <span class="table-subtext">Sync: <?= e(process_page_date($process['last_synced_at'] ?? null, true)) ?></span>
                    </td>
                    <td>
                      <?php if (!empty($process['link'])): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e($process['link']) ?>" target="_blank" rel="noopener">Abrir</a>
                      <?php else: ?>
                        <span class="text-muted">Sem link</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
