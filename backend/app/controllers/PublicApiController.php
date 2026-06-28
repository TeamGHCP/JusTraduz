<?php

require_once dirname(__DIR__) . '/core/Response.php';

class PublicApiController
{
    private Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function openApi(): void
    {
        $baseUrl = defined('APP_URL') ? APP_URL : '';
        $this->response->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'JusTraduz API',
                'version' => '1.0.0',
                'description' => 'Contrato inicial para integracoes externas. Endpoints administrativos exigem sessao admin ou futura credencial de integracao.',
            ],
            'servers' => [
                ['url' => rtrim($baseUrl, '/') . '/backend/public/index.php?rota='],
            ],
            'paths' => [
                '/api/v1/health' => [
                    'get' => [
                        'summary' => 'Healthcheck operacional',
                        'responses' => [
                            '200' => ['description' => 'Sistema operacional'],
                            '503' => ['description' => 'Sistema degradado'],
                        ],
                    ],
                ],
                '/api/v1/admin/reports/summary' => [
                    'get' => [
                        'summary' => 'Resumo gerencial',
                        'responses' => [
                            '200' => ['description' => 'Resumo por usuarios, documentos, casos, SLA e IA'],
                            '403' => ['description' => 'Permissao insuficiente'],
                        ],
                    ],
                ],
                '/api/v1/admin/reports/export' => [
                    'get' => [
                        'summary' => 'Exportacao CSV de relatorios',
                        'parameters' => [
                            [
                                'name' => 'type',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string', 'enum' => ['cases', 'users', 'documents']],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Arquivo CSV'],
                            '403' => ['description' => 'Permissao insuficiente'],
                        ],
                    ],
                ],
                '/api/v1/integrations/health' => [
                    'get' => [
                        'summary' => 'Healthcheck para integracoes externas',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Token valido e API disponivel'],
                            '401' => ['description' => 'Token inválido'],
                            '429' => ['description' => 'Limite de chamadas excedido'],
                        ],
                    ],
                ],
                '/api/v1/integrations/reports/summary' => [
                    'get' => [
                        'summary' => 'Resumo operacional para integracoes externas',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Resumo operacional'],
                            '401' => ['description' => 'Token inválido'],
                            '429' => ['description' => 'Limite de chamadas excedido'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
        ]);
    }
}
