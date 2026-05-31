<?php

class RedirectException extends Exception
{
    private string $url;

    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('Redirect', $status);
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
