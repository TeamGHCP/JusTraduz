<?php

$name = '';
$scopes = ['health:read', 'reports:read'];

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--name=')) {
        $name = trim(substr($argument, 7));
    } elseif (str_starts_with($argument, '--scopes=')) {
        $scopes = array_map('trim', explode(',', substr($argument, 9)));
    }
}

if ($name === '') {
    fwrite(STDERR, "Uso: php scripts/create-api-client.php --name=Integracao --scopes=health:read,reports:read\n");
    exit(2);
}

require_once dirname(__DIR__) . '/backend/app/config/database.php';
require_once dirname(__DIR__) . '/backend/app/services/PublicApiClientService.php';

$client = (new PublicApiClientService($pdo))->create($name, $scopes);

echo "Cliente API criado: {$client['name']}\n";
echo "ID: {$client['id']}\n";
echo "Scopes: {$client['scopes']}\n";
echo "Token: {$client['token']}\n";
echo "Guarde este token agora; ele nao fica recuperavel em texto claro.\n";
