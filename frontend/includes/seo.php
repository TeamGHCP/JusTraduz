<?php
/**
 * JusTraduz - SEO Technical Helper
 */

if (!function_exists('get_seo_base_url')) {
    /**
     * Retorna a URL base do site a partir do .env ou de uma constante.
     */
    function get_seo_base_url(): string
    {
        if (defined('SEO_BASE_URL')) {
            return rtrim(SEO_BASE_URL, '/');
        }

        // Tentar ler do .env na pasta backend
        $envPath = dirname(__DIR__, 2) . '/backend/.env';
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $value] = array_map('trim', explode('=', $line, 2));
                    $value = trim($value, "\"'");
                    if ($key === 'APP_PUBLIC_URL' || $key === 'APP_URL') {
                        return rtrim($value, '/');
                    }
                }
            }
        }

        // Fallback para o domínio oficial de produção se não encontrar .env ou local
        return 'https://justraduz.com.br';
    }
}

if (!function_exists('renderSeo')) {
    /**
     * Renderiza as tags HTML de SEO.
     * 
     * @param array $seo Configurações da página.
     */
    function renderSeo(array $seo = []): void
    {
        $baseUrl = get_seo_base_url();

        // Parâmetros com valores padrão
        $charset = $seo['charset'] ?? 'UTF-8';
        $viewport = $seo['viewport'] ?? 'width=device-width, initial-scale=1';
        $title = $seo['title'] ?? 'JusTraduz | Direito em linguagem simples';
        $description = $seo['description'] ?? 'Entenda contratos, notificações e documentos jurídicos em linguagem simples com o JusTraduz.';
        $robots = $seo['robots'] ?? 'index, follow';

        // Canonical URL
        if (isset($seo['canonical']) && $seo['canonical'] !== '') {
            $canonical = $seo['canonical'];
        } elseif (isset($seo['url']) && $seo['url'] !== '') {
            $canonical = $seo['url'];
        } else {
            // Tenta detectar a URL atual
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
            $canonical = $baseUrl . $path;
        }

        // Garantir que a canonical seja absoluta
        if (!str_starts_with($canonical, 'http')) {
            $canonical = rtrim($baseUrl, '/') . '/' . ltrim($canonical, '/');
        }

        // Imagem de compartilhamento
        $image = $seo['image'] ?? 'assets/img/logo.png';
        if (!str_starts_with($image, 'http')) {
            // Em páginas do blog, o caminho relativo de assets muda se estiver em uma pasta filha
            // Vamos resolver isso verificando se estamos na pasta blog/
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            if (str_contains($path, '/blog/')) {
                $imagePath = 'frontend/assets/img/' . ltrim(str_replace('assets/img/', '', $image), '/');
                $image = rtrim(str_replace('/frontend', '', $baseUrl), '/') . '/' . $imagePath;
            } else {
                $image = rtrim($baseUrl, '/') . '/' . ltrim($image, '/');
            }
        }

        // Renderizar tags
        echo "  <meta charset=\"" . htmlspecialchars($charset, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta name=\"viewport\" content=\"" . htmlspecialchars($viewport, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
        echo "  <meta name=\"description\" content=\"" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <link rel=\"canonical\" href=\"" . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta name=\"robots\" content=\"" . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . "\">\n";

        // Open Graph
        echo "  <meta property=\"og:locale\" content=\"pt_BR\">\n";
        echo "  <meta property=\"og:type\" content=\"website\">\n";
        echo "  <meta property=\"og:title\" content=\"" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta property=\"og:description\" content=\"" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta property=\"og:url\" content=\"" . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta property=\"og:site_name\" content=\"JusTraduz\">\n";
        echo "  <meta property=\"og:image\" content=\"" . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . "\">\n";

        // Twitter Card
        echo "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        echo "  <meta name=\"twitter:title\" content=\"" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta name=\"twitter:description\" content=\"" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "\">\n";
        echo "  <meta name=\"twitter:image\" content=\"" . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . "\">\n";
    }
}
