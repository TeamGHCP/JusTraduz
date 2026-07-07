<?php

namespace App\Services\Payments;

use RuntimeException;

class PixQrCodeService
{
    public function render(string $payload): array
    {
        $this->loadQrDependencies();

        if (!class_exists('BaconQrCode\\Writer')) {
            throw new RuntimeException('Biblioteca de QR Code nao encontrada.');
        }

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );

        $writer = new \BaconQrCode\Writer($renderer);
        $svg = $writer->writeString($payload);
        $encoded = base64_encode($svg);

        return [
            'payload' => $payload,
            'mime_type' => 'image/svg+xml',
            'encoded_image' => $encoded,
            'data_uri' => 'data:image/svg+xml;base64,' . $encoded,
        ];
    }

    private function loadQrDependencies(): void
    {
        $projectRoot = dirname(__DIR__, 4);
        $autoloadCandidates = [
            $projectRoot . '/vendor/autoload.php',
            dirname($projectRoot, 2) . '/phpMyAdmin/vendor/autoload.php',
        ];

        foreach ($autoloadCandidates as $autoload) {
            if (is_file($autoload)) {
                require_once $autoload;
                return;
            }
        }
    }
}

