<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/OabService.php';

class OabController extends BaseController
{
    private OabService $oabService;

    public function __construct()
    {
        parent::__construct();
        $this->oabService = new OabService();
    }

    public function lookup(): void
    {
        $result = $this->oabService->lookup(
            (string) $this->request->post('inscricao', ''),
            (string) $this->request->post('oab_uf', ''),
            (string) $this->request->post('tipo', ''),
            (string) $this->request->post('nome', ''),
            (string) $this->request->post('recaptcha_token', ''),
            (string) $this->request->post('recaptcha_version', 'v3')
        );

        $this->response->json($result, $result['source_available'] ? 200 : 503);
    }
}
