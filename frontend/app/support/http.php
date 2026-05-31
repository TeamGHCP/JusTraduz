<?php

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function query_message(): string
{
    return (string) ($_GET['erro'] ?? $_GET['sucesso'] ?? '');
}

function query_message_kind(): string
{
    return isset($_GET['erro']) ? 'alert-error' : 'alert-success';
}
