<?php

namespace App\Core;

class Response
{
    // Redireciona para uma URL
    public function redirect(string $url): void
    {
        // Lança uma exceção de redirect para ser tratada pelo handler global
        throw new RedirectException($url, 302);
    }

    // Redireciona com uma mensagem de erro na URL
    public function redirectWithError(string $url, string $erro): void
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $this->redirect($url . $separator . 'erro=' . urlencode($erro));
    }

    // Redireciona com uma mensagem de sucesso na URL
    public function redirectWithSuccess(string $url, string $msg): void
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $this->redirect($url . $separator . 'sucesso=' . urlencode($msg));
    }

    // Retorna JSON (para APIs)
    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        return;
    }
}
