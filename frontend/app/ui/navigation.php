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

function dashboard_nav_items(string $type, bool $isAdminPath = false): array
{
    switch ($type) {
        case 'advogado':
            return [
                ['href' => 'dashboard-advogado.php', 'label' => 'Início', 'icon' => 'home'],
                ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Casos', 'icon' => 'case'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
                ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
                ['href' => 'notificacoes.php', 'label' => 'Notificações', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
            ];
        case 'estagiario':
            return [
                ['href' => 'dashboard-estagiario.php', 'label' => 'Início', 'icon' => 'home'],
                ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Atendimentos', 'icon' => 'case'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
                ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
                ['href' => 'notificacoes.php', 'label' => 'Notificações', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
            ];
        case 'admin':
            return $isAdminPath ? [
                ['href' => 'dashboard-admin.php', 'label' => 'Visão geral', 'icon' => 'chart'],
                ['href' => 'usuarios.php', 'label' => 'Usuários', 'icon' => 'users'],
                ['href' => 'solicitacoes.php', 'label' => 'Solicitações', 'icon' => 'case'],
                ['href' => '../tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => '../agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                ['href' => 'auditoria.php', 'label' => 'Auditoria', 'icon' => 'shield'],
                ['href' => '../notificacoes.php', 'label' => 'Notificações', 'icon' => 'bell'],
                ['href' => '../perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
            ] : [
                ['href' => 'admin/dashboard-admin.php', 'label' => 'Visão geral', 'icon' => 'chart'],
                ['href' => 'admin/usuarios.php', 'label' => 'Usuários', 'icon' => 'users'],
                ['href' => 'admin/solicitacoes.php', 'label' => 'Solicitações', 'icon' => 'case'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'admin/documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                ['href' => 'admin/auditoria.php', 'label' => 'Auditoria', 'icon' => 'shield'],
                ['href' => 'notificacoes.php', 'label' => 'Notificações', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
            ];
        default:
            return [
                ['href' => 'dashboard-cliente.php', 'label' => 'Início', 'icon' => 'home'],
                ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
                ['href' => 'solicitar-ajuda.php', 'label' => 'Solicitar ajuda', 'icon' => 'help'],
                ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Solicitações', 'icon' => 'case'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
                ['href' => 'notificacoes.php', 'label' => 'Notificações', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
            ];
    }
}
