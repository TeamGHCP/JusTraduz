<?php

require_once __DIR__ . '/OrganizationService.php';

class PublicApiClientService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function authenticate(string $scope): ?array
    {
        if (!OrganizationService::tableExists($this->pdo, 'public_api_clients')) {
            return null;
        }

        $token = $this->bearerToken();
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM public_api_clients WHERE status = 'ativo'");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $client) {
            if (!password_verify($token, (string) $client['token_hash'])) {
                continue;
            }

            $scopes = array_filter(array_map('trim', explode(',', (string) ($client['scopes'] ?? ''))));
            if (!in_array($scope, $scopes, true)) {
                return null;
            }

            $this->pdo->prepare('UPDATE public_api_clients SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([(int) $client['id']]);
            return $client;
        }

        return null;
    }

    public function create(string $name, array $scopes): array
    {
        if (!OrganizationService::tableExists($this->pdo, 'public_api_clients')) {
            throw new RuntimeException('Tabela public_api_clients ausente.');
        }

        $token = 'jt_' . bin2hex(random_bytes(24));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $scopeList = implode(',', array_values(array_unique(array_filter($scopes))));

        $stmt = $this->pdo->prepare('INSERT INTO public_api_clients (nome, token_hash, scopes, status) VALUES (?, ?, ?, "ativo")');
        $stmt->execute([$name, $hash, $scopeList !== '' ? $scopeList : 'health:read']);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $name,
            'token' => $token,
            'scopes' => $scopeList,
        ];
    }

    private function bearerToken(): string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
