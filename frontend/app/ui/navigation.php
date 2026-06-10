<?php

function dashboard_url(?string $type = null): string
{
    switch ($type ?? current_user_type()) {
        case 'advogado':
            return app_url('/frontend/dashboard-advogado.php');
        case 'estagiario':
            return app_url('/frontend/dashboard-estagiario.php');
        case 'admin':
            return app_url('/frontend/admin/dashboard-admin.php');
        default:
            return app_url('/frontend/dashboard-cliente.php');
    }
}

function sidebar_navigation_modules(string $type, bool $isAdminPath = false): array
{
    switch ($type) {
        case 'advogado':
            return [
                [
                    'id' => 'overview',
                    'label' => 'Visao geral',
                    'icon' => 'home',
                    'items' => [
                        ['href' => 'dashboard-advogado.php', 'label' => 'Dashboard', 'icon' => 'chart'],
                        ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                    ],
                ],
                [
                    'id' => 'service',
                    'label' => 'Atendimentos',
                    'icon' => 'case',
                    'items' => [
                        ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Casos e solicitacoes', 'icon' => 'case'],
                        ['href' => 'chat.php', 'label' => 'Chat por caso', 'icon' => 'chat'],
                        ['href' => 'lista-advogados.php', 'label' => 'Rede de advogados', 'icon' => 'users'],
                    ],
                ],
                [
                    'id' => 'work',
                    'label' => 'Trabalho juridico',
                    'icon' => 'folder',
                    'items' => [
                        ['href' => 'processos.php', 'label' => 'Processos', 'icon' => 'file'],
                        ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'folder'],
                        ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                        ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                    ],
                ],
                [
                    'id' => 'account',
                    'label' => 'Conta',
                    'icon' => 'user',
                    'items' => [
                        ['href' => 'perfil.php', 'label' => 'Perfil profissional', 'icon' => 'user'],
                    ],
                ],
            ];
        case 'estagiario':
            return [
                [
                    'id' => 'overview',
                    'label' => 'Visao geral',
                    'icon' => 'home',
                    'items' => [
                        ['href' => 'dashboard-estagiario.php', 'label' => 'Dashboard', 'icon' => 'chart'],
                        ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                    ],
                ],
                [
                    'id' => 'work',
                    'label' => 'Trabalho assistido',
                    'icon' => 'case',
                    'items' => [
                        ['href' => 'processos.php', 'label' => 'Processos', 'icon' => 'file'],
                        ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                    ],
                ],
                [
                    'id' => 'account',
                    'label' => 'Conta',
                    'icon' => 'user',
                    'items' => [
                        ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
                    ],
                ],
            ];
        case 'admin':
            $adminPrefix = $isAdminPath ? '' : 'admin/';
            $appPrefix = $isAdminPath ? '../' : '';

            return [
                [
                    'id' => 'overview',
                    'label' => 'Visao geral',
                    'icon' => 'chart',
                    'items' => [
                        ['href' => $adminPrefix . 'dashboard-admin.php', 'label' => 'Dashboard admin', 'icon' => 'chart'],
                        ['href' => $appPrefix . 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                    ],
                ],
                [
                    'id' => 'management',
                    'label' => 'Gestao',
                    'icon' => 'users',
                    'items' => [
                        ['href' => $adminPrefix . 'usuarios.php', 'label' => 'Usuarios', 'icon' => 'users'],
                        ['href' => $adminPrefix . 'validar-oab.php', 'label' => 'Validacao OAB', 'icon' => 'shield'],
                        ['href' => $adminPrefix . 'solicitacoes.php', 'label' => 'Solicitacoes', 'icon' => 'case'],
                    ],
                ],
                [
                    'id' => 'operation',
                    'label' => 'Operacao',
                    'icon' => 'folder',
                    'items' => [
                        ['href' => $adminPrefix . 'documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                        ['href' => $appPrefix . 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                        ['href' => $appPrefix . 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                    ],
                ],
                [
                    'id' => 'security',
                    'label' => 'Seguranca',
                    'icon' => 'shield',
                    'items' => [
                        ['href' => $adminPrefix . 'auditoria.php', 'label' => 'Auditoria', 'icon' => 'shield'],
                    ],
                ],
                [
                    'id' => 'account',
                    'label' => 'Conta',
                    'icon' => 'user',
                    'items' => [
                        ['href' => $appPrefix . 'perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
                    ],
                ],
            ];
        default:
            return [
                [
                    'id' => 'overview',
                    'label' => 'Visao geral',
                    'icon' => 'home',
                    'items' => [
                        ['href' => 'dashboard-cliente.php', 'label' => 'Dashboard', 'icon' => 'chart'],
                        ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                    ],
                ],
                [
                    'id' => 'documents',
                    'label' => 'Documentos',
                    'icon' => 'folder',
                    'items' => [
                        ['href' => 'visualizar-documento.php', 'label' => 'Meus documentos', 'icon' => 'file'],
                        ['href' => 'processos.php', 'label' => 'Processos', 'icon' => 'case'],
                    ],
                ],
                [
                    'id' => 'legal-service',
                    'label' => 'Atendimento juridico',
                    'icon' => 'help',
                    'items' => [
                        ['href' => 'solicitar-ajuda.php', 'label' => 'Solicitar ajuda', 'icon' => 'help'],
                        ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Acompanhar solicitacoes', 'icon' => 'case'],
                        ['href' => 'chat.php', 'label' => 'Chat com advogado', 'icon' => 'chat'],
                        ['href' => 'lista-advogados.php', 'label' => 'Advogados verificados', 'icon' => 'users'],
                    ],
                ],
                [
                    'id' => 'organization',
                    'label' => 'Organizacao',
                    'icon' => 'calendar',
                    'items' => [
                        ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                        ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                    ],
                ],
                [
                    'id' => 'account',
                    'label' => 'Conta',
                    'icon' => 'user',
                    'items' => [
                        ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
                    ],
                ],
            ];
    }
}

function sidebar_item_is_active(string $href, string $active): bool
{
    $path = parse_url($href, PHP_URL_PATH);
    return basename((string) ($path ?: $href)) === basename($active);
}
