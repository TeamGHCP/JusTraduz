<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
if (!in_array($type, ['cliente', 'advogado'], true)) {
    header('Location: ' . dashboard_url($type));
    exit;
}

function process_detail_clean_text($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function process_detail_date($date, bool $withTime = false): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime((string) $date);
    return $timestamp ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp) : '-';
}

function process_detail_payload(array $process): array
{
    $payload = json_decode((string) ($process['payload_json'] ?? ''), true);
    return is_array($payload) ? $payload : [];
}

function process_detail_named_value($value): string
{
    if (is_array($value)) {
        return process_detail_clean_text($value['nome'] ?? $value['descricao'] ?? $value['código'] ?? '');
    }

    return process_detail_clean_text($value);
}

function process_detail_subjects(array $process): string
{
    $payload = process_detail_payload($process);
    $subjects = [];

    foreach ((array) ($payload['assuntos'] ?? []) as $subject) {
        $value = process_detail_named_value($subject);
        if ($value !== '') {
            $subjects[] = $value;
        }
    }

    if ($subjects) {
        return implode('; ', $subjects);
    }

    $fallback = process_detail_clean_text($process['assunto'] ?? '');
    return $fallback !== '' ? $fallback : '-';
}

function process_detail_summary(array $process): string
{
    $payload = process_detail_payload($process);
    return process_detail_clean_text($payload['justraduz']['resumo_linguagem_simples'] ?? '');
}

function process_detail_status(array $process): string
{
    $status = process_detail_clean_text(($process['status_normalizado'] ?? '') ?: ($process['status_inferido'] ?? ''));
    return $status !== '' ? $status : 'Sem status';
}

function process_detail_badge(array $process): string
{
    $status = strtolower(process_detail_status($process));
    foreach (['arquivado', 'baixado', 'extinto', 'encerrado', 'finalizado', 'cancelado', 'transitado', 'julgado'] as $closedStatus) {
        if (str_contains($status, $closedStatus)) {
            return 'badge-warning';
        }
    }

    return $status === 'sem status' ? 'badge-info' : 'badge-success';
}

function process_detail_source(array $process): string
{
    $source = strtolower(trim((string) ($process['source'] ?? 'datajud')));
    if (str_contains($source, 'demo')) {
        return 'Demo';
    }

    return $source === 'datajud' ? 'DataJud/CNJ' : strtoupper($source);
}

function process_detail_uf(array $process, array $payload): string
{
    $uf = strtoupper(process_detail_clean_text($process['uf'] ?? ''));
    if (preg_match('/^[A-Z]{2}$/', $uf)) {
        return $uf;
    }

    foreach (['uf', 'UF', 'estado', 'siglaUf', 'siglaUF'] as $key) {
        $payloadUf = strtoupper(process_detail_clean_text($payload[$key] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $payloadUf)) {
            return $payloadUf;
        }
    }

    $tribunal = strtoupper(process_detail_clean_text(($process['tribunal'] ?? '') ?: ($payload['tribunal'] ?? '')));
    if (preg_match('/(?:TJ|TRE|TRT|TRF)([A-Z]{2})$/', $tribunal, $matches)) {
        return $matches[1];
    }

    if (preg_match('/^TRF([1-6])$/', $tribunal, $matches)) {
        return [
            '1' => 'DF',
            '2' => 'RJ',
            '3' => 'SP',
            '4' => 'RS',
            '5' => 'PE',
            '6' => 'MG',
        ][$matches[1]] ?? '-';
    }

    return '-';
}

function process_detail_movements(array $process): array
{
    $payload = process_detail_payload($process);
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

        $description = process_detail_clean_text($movement['descricao'] ?? '');
        if ($description === '' && is_array($movement['movimentoNacional'] ?? null)) {
            $description = process_detail_named_value($movement['movimentoNacional']);
        }

        $complements = [];
        foreach ((array) ($movement['complementosTabelados'] ?? []) as $complement) {
            $value = process_detail_named_value($complement);
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

$processId = (int) ($_GET['id'] ?? 0);
$ownerType = $type === 'cliente' ? 'cliente' : $type;
$queryType = $type === 'cliente' ? 'cnj' : 'oab';

$process = $processId > 0
    ? fetch_one(
        $pdo,
        'SELECT *
         FROM external_processes
         WHERE id = ? AND user_id = ? AND owner_type = ? AND query_type = ?
         LIMIT 1',
        [$processId, current_user_id(), $ownerType, $queryType]
    )
    : null;

if (!$process) {
    header('Location: processos.php?erro=' . urlencode('Processo não encontrado ou sem permissão de acesso.'));
    exit;
}

$payload = process_detail_payload($process);
$movements = process_detail_movements($process);
$lastMovement = $movements[0] ?? null;
$topbarTitle = 'Detalhes do processo';
$topbarSubtitle = (string) (($process['process_number'] ?? '') ?: 'Processo armazenado');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalhes do processo | JusTraduz</title>
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
  <link rel="stylesheet" href="assets/css/style.css?v=site-polish-20260625">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'processos.php'); ?>

    <main class="app-main">
      <?php render_topbar($topbarTitle, $topbarSubtitle, current_user_name()); ?>

      <section class="process-detail-hero">
        <div class="process-detail-hero-main">
          <div class="process-detail-badges">
            <span class="badge badge-info"><?= e(process_detail_source($process)) ?></span>
            <span class="badge <?= e(process_detail_badge($process)) ?>"><?= e(process_detail_status($process)) ?></span>
          </div>
          <h2><?= e((string) ($process['process_number'] ?? 'Processo')) ?></h2>
          <p><?= e(process_detail_clean_text($process['classe_processual'] ?? '') ?: 'Processo consultado pelo numero CNJ') ?></p>
          <div class="process-detail-meta">
            <span><?= e((string) (($process['tribunal'] ?? '') ?: '-')) ?></span>
            <span><?= e(process_detail_clean_text(($process['comarca'] ?? '') ?: process_detail_named_value($payload['orgaoJulgador'] ?? null)) ?: 'Orgao julgador não informado') ?></span>
            <span>Ajuizamento: <?= e(process_detail_date($payload['dataAjuizamento'] ?? null)) ?></span>
          </div>
        </div>
        <a class="btn btn-outline" href="processos.php"><?= icon_svg('file') ?> Voltar aos processos</a>
      </section>

      <section class="process-detail-layout">
        <div class="process-detail-main">
          <article class="process-detail-summary">
            <div class="process-detail-section-title">
              <span>Explicacao</span>
              <h3>Resumo em linguagem simples</h3>
            </div>
            <p><?= e(process_detail_summary($process) ?: 'Resumo ainda não disponível para este registro.') ?></p>
          </article>

          <?php if ($lastMovement): ?>
            <article class="process-detail-latest">
              <span>Ultimo andamento</span>
              <time><?= e(process_detail_date($lastMovement['date'] ?? null, true)) ?></time>
              <p><?= e((string) ($lastMovement['description'] ?? 'Movimentacao registrada')) ?></p>
            </article>
          <?php endif; ?>

          <article class="process-detail-card process-detail-history-card">
            <div class="process-detail-section-title">
              <span>Histórico</span>
              <h3>Movimentacoes</h3>
            </div>
            <?php if ($movements): ?>
              <ol class="process-detail-timeline">
                <?php foreach ($movements as $movement): ?>
                  <li>
                    <time><?= e(process_detail_date($movement['date'] ?? null, true)) ?></time>
                    <p><?= e((string) ($movement['description'] ?? 'Movimentacao registrada')) ?></p>
                  </li>
                <?php endforeach; ?>
              </ol>
            <?php else: ?>
              <p class="text-muted">Nenhuma movimentacao armazenada neste processo.</p>
            <?php endif; ?>
          </article>
        </div>

        <aside class="process-detail-side">
          <article class="process-detail-card">
            <div class="process-detail-section-title">
              <span>Dados principais</span>
              <h3>Processo</h3>
            </div>
            <dl class="process-detail-list">
              <div><dt>Numero</dt><dd><?= e((string) (($process['process_number'] ?? '') ?: '-')) ?></dd></div>
              <div><dt>Tribunal</dt><dd><?= e((string) (($process['tribunal'] ?? '') ?: '-')) ?></dd></div>
              <div><dt>UF</dt><dd><?= e(process_detail_uf($process, $payload)) ?></dd></div>
              <div><dt>Grau / tipo</dt><dd><?= e(process_detail_clean_text(($process['tipo_processo'] ?? '') ?: ($payload['grau'] ?? '-')) ?: '-') ?></dd></div>
              <div><dt>Classe</dt><dd><?= e(process_detail_clean_text($process['classe_processual'] ?? '-') ?: '-') ?></dd></div>
              <div><dt>Assunto</dt><dd><?= e(process_detail_subjects($process)) ?></dd></div>
              <div><dt>Orgao julgador</dt><dd><?= e(process_detail_clean_text(($process['comarca'] ?? '') ?: process_detail_named_value($payload['orgaoJulgador'] ?? null)) ?: '-') ?></dd></div>
            </dl>
          </article>

          <article class="process-detail-card">
            <div class="process-detail-section-title">
              <span>Datas</span>
              <h3>Andamento</h3>
            </div>
            <dl class="process-detail-list">
              <div><dt>Ajuizamento</dt><dd><?= e(process_detail_date($payload['dataAjuizamento'] ?? null)) ?></dd></div>
              <div><dt>Ultima atualizacao</dt><dd><?= e(process_detail_date($process['data_ultima_atualizacao'] ?? null)) ?></dd></div>
              <div><dt>Andamento mais recente</dt><dd><?= e(process_detail_date($process['data_andamento_mais_recente'] ?? null)) ?></dd></div>
            </dl>
          </article>
        </aside>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
