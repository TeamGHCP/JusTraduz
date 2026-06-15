<?php

class SlaService
{
    public static function deadlineForPriority(string $priority): string
    {
        $hours = match ($priority) {
            'alta' => 24,
            'baixa' => 120,
            default => 72,
        };

        return (new DateTimeImmutable())->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
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
