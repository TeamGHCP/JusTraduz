<?php

$root = dirname(__DIR__);
$extensions = ['php', 'html', 'css', 'js'];
$ignorePrefixes = ['http://', 'https://', 'mailto:', 'tel:', '#', 'data:', 'blob:', 'javascript:', 'cid:'];
$failures = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($relative, '.git/') || str_starts_with($relative, 'node_modules/')) {
        continue;
    }

    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true)) {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    if ($content === false) {
        continue;
    }

    preg_match_all('/\b(?:href|src|action)=["\']([^"\']+)["\']/i', $content, $matches);
    foreach ($matches[1] as $reference) {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES, 'UTF-8'));
        if ($reference === '' || str_contains($reference, '<?') || str_contains($reference, '{$') || should_ignore_reference($reference, $ignorePrefixes)) {
            continue;
        }

        $path = parse_url($reference, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || str_contains($path, '<?')) {
            continue;
        }

        $candidates = [];
        if (str_starts_with($path, '/')) {
            $candidates[] = $root . $path;
            $candidates[] = $root . preg_replace('#^/JusTraduz/#', '/', $path);
        } else {
            $candidates[] = dirname($file->getPathname()) . '/' . $path;

            if (str_starts_with($relative, 'frontend/pages/app/')) {
                $candidates[] = $root . '/frontend/' . $path;
            }

            if (str_starts_with($relative, 'frontend/pages/admin/')) {
                $candidates[] = $root . '/frontend/' . preg_replace('#^\.\./#', '', $path);
            }
        }

        if (!any_reference_exists($candidates)) {
            $failures[] = $relative . ' -> ' . $reference;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Referencias locais inexistentes:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Reference check: OK\n";

function should_ignore_reference(string $reference, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if (str_starts_with($reference, $prefix)) {
            return true;
        }
    }

    return false;
}

function any_reference_exists(array $candidates): bool
{
    foreach ($candidates as $candidate) {
        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        if (file_exists($candidate)) {
            return true;
        }
    }

    return false;
}
