<?php

class Request
{
    // Retorna o método HTTP (GET, POST, etc)
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    // Retorna um campo do POST ou null se não existir
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    // Retorna um campo do GET ou null se não existir
    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    // Retorna todos os dados do POST sanitizados
    public function all(): array
    {
        return array_map('trim', $_POST);
    }

    // Verifica se é uma requisição POST
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }
}