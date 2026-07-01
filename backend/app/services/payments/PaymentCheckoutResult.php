<?php

namespace App\Services\Payments {
    class PaymentCheckoutResult
    {
        public bool $ok;
        public string $redirectUrl;
        public ?int $subscriptionId;
        public ?string $errorMessage;
        public array $metadata;

        private function __construct(bool $ok, string $redirectUrl = '', ?int $subscriptionId = null, ?string $errorMessage = null, array $metadata = [])
        {
            $this->ok = $ok;
            $this->redirectUrl = $redirectUrl;
            $this->subscriptionId = $subscriptionId;
            $this->errorMessage = $errorMessage;
            $this->metadata = $metadata;
        }

        public static function success(string $redirectUrl, ?int $subscriptionId = null, array $metadata = []): self
        {
            return new self(true, $redirectUrl, $subscriptionId, null, $metadata);
        }

        public static function error(string $message): self
        {
            return new self(false, '', null, $message);
        }
    }
}

namespace {
    if (!class_exists('PaymentCheckoutResult')) {
        class_alias('App\Services\Payments\PaymentCheckoutResult', 'PaymentCheckoutResult');
    }
}
