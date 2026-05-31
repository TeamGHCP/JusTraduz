<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';

class ScheduleController extends BaseController
{
    private AuditService $audit;
    private NotificationService $notifications;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
        $this->notifications = new NotificationService($this->pdo);
    }

    public function createSlot(): void
    {
        $this->requireLogin();
        $this->requireProfessional();

        // TEMP DEBUG: log incoming request and session to help diagnose why slot creation may fail
        error_log('[DEBUG createSlot] session_id=' . (isset($_SESSION['id']) ? (int) $_SESSION['id'] : 'none') . ' POST_starts=' . ($this->request->post('starts_at', '') ?? '') . ' POST_ends=' . ($this->request->post('ends_at', '') ?? '') . ' REMOTE_ADDR=' . ($_SERVER['REMOTE_ADDR'] ?? ''));

        $startsAt = $this->parseDateTime((string) $this->request->post('starts_at', ''));
        $endsAt = $this->parseDateTime((string) $this->request->post('ends_at', ''));
        $titulo = trim((string) $this->request->post('titulo', ''));
        $status = (string) $this->request->post('status', 'livre');
        $isAjax = $this->isAjaxRequest();
        if (!in_array($status, ['livre', 'bloqueado'], true)) {
            $status = 'livre';
        }

        if (!$startsAt || !$endsAt || $endsAt <= $startsAt) {
            $this->respondSlotError($isAjax, 'Informe início e fim válidos.', 400);
            return;
        }

        $today = (new DateTimeImmutable('today'))->setTime(0, 0, 0);
        if ($startsAt->setTime(0, 0, 0) < $today) {
            $this->respondSlotError($isAjax, 'Não é possível criar horário em dia passado.', 422);
            return;
        }

        if ($startsAt < new DateTimeImmutable('-5 minutes')) {
            $this->respondSlotError($isAjax, 'Não é possível criar horário no passado.', 422);
            return;
        }

        if ($this->hasOverlap((int) $_SESSION['id'], $startsAt, $endsAt)) {
            $this->respondSlotError($isAjax, 'Já existe um horário nessa faixa.', 409);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO schedule_slots (professional_id, starts_at, ends_at, status, titulo) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $_SESSION['id'],
            $startsAt->format('Y-m-d H:i:s'),
            $endsAt->format('Y-m-d H:i:s'),
            $status,
            $titulo ?: null,
        ]);

        $slotId = (int) $this->pdo->lastInsertId();
        $this->audit->log('schedule.slot_created', 'schedule_slot', $slotId, [
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'status' => $status,
        ]);

        if ($isAjax) {
            $this->response->json(['success' => true, 'slot_id' => $slotId]);
            return;
        }

        $this->response->redirect(app_url('/frontend/agenda.php?sucesso=' . urlencode('Horário criado na agenda.')));
    }

    public function updateSlot(): void
    {
        $this->requireLogin();

        $isAjax = $this->isAjaxRequest();

        $slotId = (int) $this->request->post('slot_id', 0);

        if ($slotId <= 0) {
            $this->respondSlotError($isAjax, 'Dados inválidos do horário.', 400);
            return;
        }

        $slot = $this->slotById($slotId);
        if (!$slot || !$this->canManageSlot($slot)) {
            $this->respondSlotError($isAjax, 'Horário indisponível para seu perfil.', 403);
            return;
        }

        // If starts_at provided, treat as edit of slot (start/end/title)
        $startsRaw = $this->request->post('starts_at', '');
        $endsRaw = $this->request->post('ends_at', '');
        $titulo = trim((string) $this->request->post('titulo', ''));

        if ($startsRaw !== '' && $endsRaw !== '') {
            if ($this->hasActiveAppointment($slotId)) {
                $this->respondSlotError($isAjax, 'Horário com agendamento ativo não pode ser editado.', 409);
                return;
            }

            $startsAt = $this->parseDateTime((string) $startsRaw);
            $endsAt = $this->parseDateTime((string) $endsRaw);

            if (!$startsAt || !$endsAt || $endsAt <= $startsAt) {
                $this->respondSlotError($isAjax, 'Informe início e fim válidos.', 400);
                return;
            }

            $today = (new DateTimeImmutable('today'))->setTime(0, 0, 0);
            if ($startsAt->setTime(0, 0, 0) < $today) {
                $this->respondSlotError($isAjax, 'Não é possível editar para dia passado.', 422);
                return;
            }

            if ($startsAt < new DateTimeImmutable('-5 minutes')) {
                $this->respondSlotError($isAjax, 'Não é possível editar para horário no passado.', 422);
                return;
            }

            if ($this->hasOverlap((int) $_SESSION['id'], $startsAt, $endsAt, $slotId)) {
                $this->respondSlotError($isAjax, 'Já existe um horário nessa faixa.', 409);
                return;
            }

            $status = (string) $this->request->post('status', (string) ($slot['status'] ?? 'livre'));
            if (!in_array($status, ['livre', 'bloqueado'], true)) {
                $status = (string) ($slot['status'] ?? 'livre');
            }

            $stmt = $this->pdo->prepare('UPDATE schedule_slots SET starts_at = ?, ends_at = ?, titulo = ?, status = ? WHERE id = ?');
            $stmt->execute([$startsAt->format('Y-m-d H:i:s'), $endsAt->format('Y-m-d H:i:s'), $titulo ?: null, $status, $slotId]);

            $this->audit->log('schedule.slot_updated', 'schedule_slot', $slotId, ['starts_at' => $startsAt->format('Y-m-d H:i:s'), 'ends_at' => $endsAt->format('Y-m-d H:i:s'), 'status' => $status]);

            if ($isAjax) {
                $this->response->json(['success' => true, 'slot_id' => $slotId]);
                return;
            }

            $this->response->redirect(app_url('/frontend/agenda.php?sucesso=' . urlencode('Horário atualizado.')));
            return;
        }

        // fallback: status update (block/unblock)
        $status = (string) $this->request->post('status', '');
        if (!in_array($status, ['livre', 'bloqueado'], true)) {
            $this->respondSlotError($isAjax, 'Dados inválidos do horário.', 400);
            return;
        }

        if ($this->hasActiveAppointment($slotId)) {
            $this->respondSlotError($isAjax, 'Horários com agendamento ativo não podem ser bloqueados/liberados manualmente.', 409);
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE schedule_slots SET status = ? WHERE id = ?');
        $stmt->execute([$status, $slotId]);

        $this->audit->log('schedule.slot_updated', 'schedule_slot', $slotId, ['status' => $status]);

        if ($isAjax) {
            $this->response->json(['success' => true, 'slot_id' => $slotId]);
            return;
        }

        $this->response->redirect(app_url('/frontend/agenda.php?sucesso=' . urlencode('Horário atualizado.')));
    }

    public function book(): void
    {
        $this->requireLogin();

        if (($_SESSION['tipo'] ?? '') !== 'cliente') {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Apenas clientes podem agendar atendimentos.')));
        }

        $slotId = (int) $this->request->post('slot_id', 0);
        $caseId = $this->request->post('case_id', '');
        $caseId = $caseId === '' ? null : (int) $caseId;
        $assunto = trim((string) $this->request->post('assunto', ''));
        $observacoes = trim((string) $this->request->post('observacoes', ''));

        if ($slotId <= 0 || $assunto === '') {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Escolha um horário e informe o assunto.')));
        }

        $slot = $this->slotById($slotId);
        if (!$slot || $slot['status'] !== 'livre' || new DateTimeImmutable($slot['starts_at']) < new DateTimeImmutable()) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Este horário não está mais livre.')));
        }

        if (!in_array($slot['tipo'], ['advogado', 'estagiario'], true)) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Profissional inválido para agenda.')));
        }

        if ($caseId !== null && !$this->caseBelongsToClient($caseId, (int) $_SESSION['id'])) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Caso inválido para seu usuário.')));
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE schedule_slots SET status = 'ocupado' WHERE id = ? AND status = 'livre'");
            $stmt->execute([$slotId]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Este horário acabou de ser reservado.')));
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO appointments (slot_id, client_id, case_id, assunto, observacoes) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$slotId, (int) $_SESSION['id'], $caseId, $assunto, $observacoes ?: null]);
            $appointmentId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifications->notify((int) $slot['professional_id'], 'Novo agendamento em sua agenda: ' . $assunto);
        $this->notifications->notify((int) $_SESSION['id'], 'Agendamento confirmado com ' . (string) $slot['professional_name'] . '.');
        $this->notifications->notifyMany($this->notifications->activeAdmins(), 'Novo agendamento criado: ' . $assunto);
        $this->audit->log('schedule.appointment_booked', 'appointment', $appointmentId, [
            'slot_id' => $slotId,
            'professional_id' => (int) $slot['professional_id'],
            'case_id' => $caseId,
        ]);

        $this->response->redirect(app_url('/frontend/agenda.php?sucesso=' . urlencode('Atendimento agendado.')));
    }

    public function updateAppointment(): void
    {
        $this->requireLogin();

        $appointmentId = (int) $this->request->post('appointment_id', 0);
        $status = (string) $this->request->post('status', '');

        if ($appointmentId <= 0 || !in_array($status, ['cancelado', 'concluido'], true)) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Dados inválidos do agendamento.')));
        }

        $appointment = $this->appointmentById($appointmentId);
        if (!$appointment || !$this->canManageAppointment($appointment, $status)) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Agendamento indisponível para seu perfil.')));
        }

        if (($appointment['status'] ?? '') !== 'agendado') {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Apenas agendamentos ativos podem ser atualizados.')));
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
            $stmt->execute([$status, $appointmentId]);

            if ($status === 'cancelado') {
                $stmt = $this->pdo->prepare("UPDATE schedule_slots SET status = 'livre' WHERE id = ? AND starts_at > NOW()");
                $stmt->execute([(int) $appointment['slot_id']]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifications->notifyMany(
            [(int) $appointment['client_id'], (int) $appointment['professional_id']],
            'Agendamento "' . (string) $appointment['assunto'] . '" atualizado para: ' . $status
        );
        $this->audit->log('schedule.appointment_updated', 'appointment', $appointmentId, ['status' => $status]);

        $this->response->redirect(app_url('/frontend/agenda.php?sucesso=' . urlencode('Agendamento atualizado.')));
    }

    // -------------------------------------------------------
    // GET /schedule/calendar
    // Returns JSON with schedule_slots and appointments between start and end (inclusive)
    // Params: start=YYYY-MM-DD, end=YYYY-MM-DD, professional_id (optional)
    // -------------------------------------------------------
    public function calendarData(): void
    {
        // allow anonymous viewing in public pages (clients) but still start session for identity
        $this->startSession();

        $start = (string) ($this->request->get('start', ''));
        $end = (string) ($this->request->get('end', ''));
        $professionalId = (int) ($this->request->get('professional_id', 0));

        // default month range if not provided
        if ($start === '' || $end === '') {
            $now = new DateTimeImmutable();
            $first = $now->modify('first day of this month')->setTime(0, 0, 0);
            $last = $now->modify('last day of this month')->setTime(23, 59, 59);
            $start = $first->format('Y-m-d');
            $end = $last->format('Y-m-d');
        }

        $startDt = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $endDt = DateTimeImmutable::createFromFormat('Y-m-d', $end);
        if (!$startDt || !$endDt) {
            $this->response->json(['error' => 'invalid_range'], 400);
            return;
        }

        $startStr = $startDt->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $endStr = $endDt->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $params = [$startStr, $endStr];
        $profWhere = '';
        if ($professionalId > 0) {
            $profWhere = ' AND s.professional_id = ?';
            $params[] = $professionalId;
        }

        $slotStatusWhere = '';
        if (($_SESSION['tipo'] ?? '') === 'cliente') {
            // clients should only see free slots
            $slotStatusWhere = " AND s.status = 'livre'";
        }

        $slots = fetch_all(
            $this->pdo,
            'SELECT s.id, s.professional_id, s.starts_at, s.ends_at, s.status, s.titulo, u.nome AS professional_name, u.tipo
             FROM schedule_slots s
             INNER JOIN users u ON u.id = s.professional_id
             WHERE s.starts_at >= ? AND s.ends_at <= ?' . $slotStatusWhere . $profWhere . '
             ORDER BY s.starts_at ASC',
            $params
        );

        $appointments = [];
        if (($_SESSION['tipo'] ?? '') !== 'cliente') {
            $appointments = fetch_all(
                $this->pdo,
                'SELECT a.id, a.slot_id, a.client_id, a.case_id, a.assunto, a.status, s.starts_at, s.ends_at, s.professional_id, u.nome AS professional_name, cli.nome AS client_name
                 FROM appointments a
                 INNER JOIN schedule_slots s ON s.id = a.slot_id
                 LEFT JOIN users u ON u.id = s.professional_id
                 LEFT JOIN users cli ON cli.id = a.client_id
                 WHERE s.starts_at >= ? AND s.ends_at <= ?' . $profWhere . '
                 ORDER BY s.starts_at ASC',
                $params
            );
        }

        $this->response->json(['slots' => $slots, 'appointments' => $appointments]);
    }

    private function requireLogin(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para continuar.')));
        }
    }

    private function requireProfessional(): void
    {
        if (!in_array($_SESSION['tipo'] ?? '', ['advogado', 'estagiario'], true)) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Apenas advogados e estagiários gerenciam horários.')));
        }

        $stmt = $this->pdo->prepare('SELECT oab_verificado FROM users WHERE id = ?');
        $stmt->execute([(int) ($_SESSION['id'] ?? 0)]);

        if ((int) $stmt->fetchColumn() !== 1) {
            $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode('Valide sua inscrição OAB no CNA antes de abrir horários.')));
        }
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable(str_replace('T', ' ', $value));
        } catch (Throwable) {
            return null;
        }
    }

    private function hasOverlap(int $professionalId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, ?int $excludeSlotId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM schedule_slots
             WHERE professional_id = ?
             AND status <> "bloqueado"
             AND starts_at < ?
             AND ends_at > ?';
        $params = [$professionalId, $endsAt->format('Y-m-d H:i:s'), $startsAt->format('Y-m-d H:i:s')];
        if ($excludeSlotId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeSlotId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function isAjaxRequest(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || ((string) $this->request->header('X-Requested-With') === 'XMLHttpRequest');
    }

    private function respondSlotError(bool $isAjax, string $message, int $statusCode = 400): void
    {
        if ($isAjax) {
            $this->response->json(['success' => false, 'error' => $message], $statusCode);
            return;
        }

        $this->response->redirect(app_url('/frontend/agenda.php?erro=' . urlencode($message)));
    }

    private function slotById(int $slotId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.nome AS professional_name, u.tipo
             FROM schedule_slots s
             INNER JOIN users u ON u.id = s.professional_id
             WHERE s.id = ?'
        );
        $stmt->execute([$slotId]);
        $slot = $stmt->fetch();

        return $slot ?: null;
    }

    private function appointmentById(int $appointmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, s.professional_id, s.starts_at, s.ends_at
             FROM appointments a
             INNER JOIN schedule_slots s ON s.id = a.slot_id
             WHERE a.id = ?'
        );
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();

        return $appointment ?: null;
    }

    private function hasActiveAppointment(int $slotId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appointments WHERE slot_id = ? AND status = 'agendado'");
        $stmt->execute([$slotId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function caseBelongsToClient(int $caseId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cases WHERE id = ? AND cliente_id = ?');
        $stmt->execute([$caseId, $clientId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function canManageSlot(array $slot): bool
    {
        $type = (string) ($_SESSION['tipo'] ?? '');
        $userId = (int) ($_SESSION['id'] ?? 0);
        return $type === 'admin' || (int) $slot['professional_id'] === $userId;
    }

    private function canManageAppointment(array $appointment, string $newStatus): bool
    {
        $type = (string) ($_SESSION['tipo'] ?? '');
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($type === 'admin') {
            return true;
        }

        if ($type === 'cliente') {
            return (int) $appointment['client_id'] === $userId && $newStatus === 'cancelado';
        }

        if (in_array($type, ['advogado', 'estagiario'], true)) {
            return (int) $appointment['professional_id'] === $userId;
        }

        return false;
    }
}
