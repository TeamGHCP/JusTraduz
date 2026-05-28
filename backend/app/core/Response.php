<?php

class Response
{
    // Redireciona para uma URL
    public function redirect(string $url): void
    {
        header("Location: $url");
        exit();
    }

    // Redireciona com uma mensagem de erro na URL
    public function redirectWithError(string $url, string $erro): void
    {
        $this->redirect("$url?erro=" . urlencode($erro));
    }

    // Redireciona com uma mensagem de sucesso na URL
    public function redirectWithSuccess(string $url, string $msg): void
    {
        $this->redirect("$url?sucesso=" . urlencode($msg));
    }

    // Retorna JSON (para APIs)
    public function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}