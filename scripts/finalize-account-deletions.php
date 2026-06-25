<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';
require_once dirname(__DIR__) . '/backend/app/controllers/PrivacyController.php';

$limit = 50;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = max(1, (int) substr($argument, 8));
    }
}

$finalized = (new PrivacyController())->finalizeExpiredDeletions($limit);
echo "Exclusoes definitivas processadas: {$finalized}\n";
