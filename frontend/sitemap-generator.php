<?php
/**
 * JusTraduz - Sitemap Generator
 */

require_once __DIR__ . '/includes/seo.php';

// Impedir acesso não autorizado se necessário, mas como é sitemap, deixamos público com opção de rodar
$baseUrl = get_seo_base_url();

$pages = [
    '', // Home
    '/como-funciona',
    '/traduzir-juridiques',
    '/simplificar-documento-juridico',
    '/ajuda-juridica-online',
    '/para-clientes',
    '/para-advogados',
    '/seguranca-lgpd',
    '/contato',
    '/termos',
    '/privacidade',
    '/blog',
    '/blog/o-que-e-juridiques',
    '/blog/como-entender-contrato',
    '/blog/termos-juridicos-mais-comuns'
];

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$currentDate = date('Y-m-d');

foreach ($pages as $page) {
    $url = rtrim($baseUrl, '/') . $page;
    $priority = ($page === '') ? '1.0' : '0.8';
    $changefreq = ($page === '' || $page === '/blog') ? 'daily' : 'weekly';

    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc>\n";
    $xml .= "    <lastmod>" . $currentDate . "</lastmod>\n";
    $xml .= "    <changefreq>" . $changefreq . "</changefreq>\n";
    $xml .= "    <priority>" . $priority . "</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= '</urlset>';

// Escrever na raiz do projeto e na pasta frontend
$rootPath = dirname(__DIR__);
$successRoot = @file_put_contents($rootPath . '/sitemap.xml', $xml);
$successFrontend = @file_put_contents(__DIR__ . '/sitemap.xml', $xml);

header('Content-Type: text/plain; charset=UTF-8');
if ($successRoot && $successFrontend) {
    echo "Sitemap gerado com sucesso!\n";
    echo "URL Base utilizada: " . $baseUrl . "\n";
    echo "Total de páginas indexadas: " . count($pages) . "\n";
    echo "Caminhos salvos:\n";
    echo " - " . realpath($rootPath . '/sitemap.xml') . "\n";
    echo " - " . realpath(__DIR__ . '/sitemap.xml') . "\n";
} else {
    http_response_code(500);
    echo "Erro ao gravar os arquivos sitemap.xml nas pastas de destino.\n";
}
