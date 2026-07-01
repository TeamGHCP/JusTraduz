<?php

namespace App\Core {
    use Exception;

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
}

namespace {
    if (!class_exists('RedirectException')) {
        class_alias('App\Core\RedirectException', 'RedirectException');
    }
}
