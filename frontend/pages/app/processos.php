<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
if (!in_array($type, ['cliente', 'advogado'], true)) {
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
        $value = process_page_clean_text($process[$field] ?? '');
        if ($value !== '') {
            return $value;
        }
    }

    return 'Processo importado';
}

function process_page_source(array $process): string
{
    $source = strtolower(trim((string) ($process['source'] ?? 'datajud')));
    if (str_contains($source, 'demo')) {
        return 'Demo';
    }

    return $source === 'datajud' ? 'DataJud/CNJ' : strtoupper($source);
}

function process_page_payload(array $process): array
{
    $payload = json_decode((string) ($process['payload_json'] ?? ''), true);
    return is_array($payload) ? $payload : [];
}

function process_page_movements(array $process): array
{
    $payload = process_page_payload($process);
    $movements = $payload['justraduz']['ultimas_movimentacoes'] ?? [];
    return is_array($movements) ? array_slice($movements, 0, 3) : [];
}

function process_page_all_movements(array $process): array
{
    $payload = process_page_payload($process);
    $rawMovements = is_array($payload['movimentos'] ?? null)
        ? $payload['movimentos']
        : ($payload['justraduz']['ultimas_movimentacoes'] ?? []);

    if (!is_array($rawMovements)) {
        return [];
    }

    usort($rawMovements, static fn (array $a, array $b): int => strcmp((string) ($b['dataHora'] ?? ''), (string) ($a['dataHora'] ?? '')));

    $movements = [];
    foreach ($rawMovements as $movement) {
        if (!is_array($movement)) {
            continue;
        }

        $description = trim((string) ($movement['descricao'] ?? ($movement['nome'] ?? '')));
        if ($description === '' && is_array($movement['movimentoNacional'] ?? null)) {
            $description = process_page_named_payload_value($movement['movimentoNacional']);
        }

        $complements = [];
        foreach ((array) ($movement['complementosTabelados'] ?? []) as $complement) {
            $value = process_page_named_payload_value($complement);
            if ($value !== '') {
                $complements[] = $value;
            }
        }

        if ($complements) {
            $description .= ($description !== '' ? ' - ' : '') . implode('; ', $complements);
        }

        $movements[] = [
            'date' => $movement['dataHora'] ?? null,
            'description' => $description !== '' ? $description : 'Movimentacao registrada',
        ];
    }

    return $movements;
}

function process_page_simple_summary(array $process): string
{
    $payload = process_page_payload($process);
    return trim((string) ($payload['justraduz']['resumo_linguagem_simples'] ?? ''));
}

function process_page_named_payload_value($value): string
{
    if (is_array($value)) {
        return process_page_clean_text($value['nome'] ?? $value['descricao'] ?? $value['código'] ?? '');
    }

    return process_page_clean_text($value);
}

function process_page_clean_text($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function process_page_payload_subjects(array $process): string
{
    $payload = process_page_payload($process);
    $subjects = [];
    foreach ((array) ($payload['assuntos'] ?? []) as $subject) {
        $value = process_page_named_payload_value($subject);
        if ($value !== '') {
            $subjects[] = $value;
        }
    }

    return $subjects ? implode('; ', $subjects) : (string) (($process['assunto'] ?? '') ?: '-');
}

function process_page_mask_cpf(string $digits): string
{
    if (strlen($digits) !== 11) {
        return $digits !== '' ? $digits : 'Não informado';
    }

    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?: $digits;
}

function process_page_scope_label(string $scope): string
{
    if ($scope === 'abertos') {
        return 'Em aberto';
    }

    if ($scope === 'encerrados') {
        return 'Encerrados';
    }

    return 'Todos';
}

$processes = [];
$tableReady = true;
$queryType = $type === 'cliente' ? 'cnj' : 'oab';
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

if ($scope === 'abertos') {
    $visibleProcesses = $openProcesses;
} elseif ($scope === 'encerrados') {
    $visibleProcesses = $closedProcesses;
} else {
    $visibleProcesses = $processes;
}

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

$identityLabel = $type === 'cliente'
    ? 'CPF ' . process_page_mask_cpf($cpfDigits)
    : 'OAB ' . trim($oabUf . ' ' . ($user['oab'] ?? ''));
$integrationLabel = $type === 'cliente' ? 'DataJud/CNJ' : 'Consulta por OAB desativada';
$topbarTitle = $type === 'cliente' ? 'Meus processos' : 'Processos armazenados';
$topbarSubtitle = $type === 'cliente'
    ? 'Consulte pelo numero CNJ. O CPF fica apenas no cadastro e identificacao.'
    : 'A versao inicial usa DataJud por numero CNJ para clientes.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Processos | JusTraduz</title>
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-vlibras-panel-1">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'processos.php'); ?>

    <main class="app-main">
      <?php render_topbar($topbarTitle, $topbarSubtitle, current_user_name()); ?>

      <section class="client-command process-command">
        <article class="command-card command-card-primary">
          <span class="badge badge-success"><?= e($integrationLabel) ?></span>
          <h2><?= $type === 'cliente' ? 'Consulta por numero CNJ' : 'Consulta externa indisponível' ?></h2>
          <?php if ($type !== 'cliente'): ?>
            <p><strong><?= e($identityLabel) ?></strong></p>
          <?php endif; ?>

          <?php if (!$tableReady): ?>
            <div class="alert is-visible alert-error">A tabela de processos ainda não existe neste banco. Importe um dos SQLs consolidados em database/.</div>
          <?php elseif (!$identityReady && $type === 'cliente'): ?>
            <div class="form-actions">
              <a class="btn btn-primary" href="perfil.php"><?= icon_svg('user') ?> Cadastrar CPF</a>
            </div>
          <?php elseif ($type === 'cliente'): ?>
            <form class="process-cnj-form" action="<?= e(app_url('/backend/public/index.php?rota=/processes/sync')) ?>" method="post">
              <?= csrf_input() ?>
              <div class="field">
                <label for="process-number">Numero do processo</label>
                <input class="input" id="process-number" name="process_number" type="text" inputmode="numeric" maxlength="25" placeholder="0000000-00.0000.0.00.0000" required>
              </div>
              <label class="lgpd-consent">
                <input type="checkbox" name="lgpd_consent" value="1" required>
                <span>Autorizo o JusTraduz a consultar dados processuais publicos relacionados a este atendimento, usando o numero de processo informado por mim, exclusivamente para organizar e explicar as informacoes processuais dentro da plataforma.</span>
              </label>
              <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= icon_svg('download') ?> Consultar processo</button>
              </div>
            </form>
          <?php else: ?>
            <div class="alert is-visible alert-info">Consulta por CPF, OAB ou API jurídica paga ficou no roadmap futuro. Nesta versão, o fluxo novo é DataJud por número CNJ para clientes.</div>
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
          <p><?= $demoCount > 0 ? e((string) $demoCount) . ' registro(s) de demo disponíveis.' : 'Sem dados demo nesta consulta.' ?></p>
        </article>
      </section>

      <section class="grid grid-4">
        <?= stat_card('Importados', count($processes), 'file') ?>
        <?= stat_card('Em aberto', count($openProcesses), 'case') ?>
        <?= stat_card('Encerrados', count($closedProcesses), 'check') ?>
        <?= stat_card('Consulta', $type === 'cliente' ? 'CNJ' : strtoupper($queryType), 'chart') ?>
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
          <?= empty_state('Execute database/justraduz_completo_sem_demo.sql ou database/justraduz_completo_com_demo.sql para habilitar está tela.') ?>
        <?php elseif (!$processes): ?>
          <?= empty_state($type === 'cliente' ? 'Nenhum processo armazenado ainda. Informe o numero CNJ e aceite o termo LGPD para consultar no DataJud.' : 'Nenhum processo armazenado para este perfil.') ?>
        <?php elseif (!$visibleProcesses): ?>
          <?= empty_state('Nenhum processo encontrado para os filtros atuais.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Processo</th>
                  <th>Tribunal</th>
                  <th>Dados processuais</th>
                  <th>Ultima atualizacao</th>
                  <th>Resumo</th>
                  <th>Detalhes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($visibleProcesses as $process): ?>
                  <tr>
                    <td>
                      <strong><?= e($process['process_number'] ?? '') ?></strong>
                      <span class="table-subtext"><span class="badge <?= e(process_page_badge($process)) ?>"><?= e(process_page_status($process)) ?></span> <span class="badge <?= process_page_source($process) === 'Demo' ? 'badge-info' : 'badge-success' ?>"><?= e(process_page_source($process)) ?></span></span>
                    </td>
                    <td>
                      <?= e((string) (($process['tribunal'] ?? '') ?: '-')) ?>
                      <span class="table-subtext"><?= e(trim((string) (($process['uf'] ?? '') . ' ' . ($process['comarca'] ?? ''))) ?: '-') ?></span>
                    </td>
                    <td>
                      <strong><?= e((string) (($process['classe_processual'] ?? '') ?: '-')) ?></strong>
                      <span class="table-subtext"><?= e(process_page_clean_text(($process['assunto'] ?? '') ?: process_page_subject($process))) ?></span>
                      <span class="table-subtext">Orgao julgador: <?= e((string) (($process['comarca'] ?? '') ?: '-')) ?></span>
                    </td>
                    <td>
                      <?= e(process_page_date($process['data_andamento_mais_recente'] ?? null)) ?>
                      <span class="table-subtext">Sync: <?= e(process_page_date($process['last_synced_at'] ?? null, true)) ?></span>
                      <span class="table-subtext">Ajuizamento: <?= e(process_page_date(process_page_payload($process)['dataAjuizamento'] ?? null)) ?></span>
                    </td>
                    <td>
                      <p class="process-summary"><?= e(process_page_simple_summary($process) ?: 'Resumo ainda não disponível para este registro.') ?></p>
                      <?php $movements = process_page_movements($process); ?>
                      <?php if ($movements): ?>
                        <ul class="process-movements">
                          <?php foreach ($movements as $movement): ?>
                            <li><?= e(process_page_date($movement['dataHora'] ?? null)) ?> - <?= e((string) ($movement['descricao'] ?? 'Movimentacao registrada')) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a class="btn btn-outline btn-sm" href="processo-detalhes.php?id=<?= (int) ($process['id'] ?? 0) ?>">
                        <?= icon_svg('file') ?> Ver detalhes
                      </a>
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
  <?php render_vlibras(); ?>
</body>
</html>
