<?php

class SlaService
{
    private const SLA_HOURS = [
        'baixa' => 72,
        'media' => 48,
        'normal' => 48,
        'alta' => 24,
        'urgente' => 4,
    ];

    public static function hoursForPriority(string $priority): int
    {
        return self::SLA_HOURS[$priority] ?? self::SLA_HOURS['media'];
    }

    public static function deadlineFor(string $createdAt, string $priority): DateTimeImmutable
    {
        $created = new DateTimeImmutable($createdAt ?: 'now');
        return $created->modify('+' . self::hoursForPriority($priority) . ' hours');
    }

    public static function statusForCase(array $case, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $deadline = self::deadlineFor((string) ($case['created_at'] ?? 'now'), (string) ($case['prioridade'] ?? 'media'));
        $status = (string) ($case['status'] ?? '');

        if ($status === 'finalizado') {
            return ['state' => 'done', 'deadline' => $deadline, 'hours_remaining' => null];
        }

        $hoursRemaining = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 3600);
        if ($hoursRemaining < 0) {
            return ['state' => 'overdue', 'deadline' => $deadline, 'hours_remaining' => $hoursRemaining];
        }

        if ($hoursRemaining <= 6) {
            return ['state' => 'due_soon', 'deadline' => $deadline, 'hours_remaining' => $hoursRemaining];
        }

        return ['state' => 'on_track', 'deadline' => $deadline, 'hours_remaining' => $hoursRemaining];
    }

    public static function deadlineForPriority(string $priority): string
    {
        return (new DateTimeImmutable())->modify('+' . self::hoursForPriority($priority) . ' hours')->format('Y-m-d H:i:s');
    }

    public static function status(?string $deadline, string $caseStatus): string
    {
        if ($caseStatus === 'finalizado') {
            return 'ok';
        }

        if (!$deadline) {
            return 'sem_sla';
        }

        $now = new DateTimeImmutable();
        $due = new DateTimeImmutable($deadline);
        if ($due < $now) {
            return 'vencido';
        }

        if ($due <= $now->modify('+12 hours')) {
            return 'em_risco';
        }

        return 'ok';
    }
}
