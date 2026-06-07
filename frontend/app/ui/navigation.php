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
                ['href' => 'dashboard-advogado.php', 'label' => 'Inicio', 'icon' => 'home'],
                ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Casos', 'icon' => 'case'],
                ['href' => 'processos.php', 'label' => 'Processos', 'icon' => 'file'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
                ['href' => 'lista-advogados.php', 'label' => 'Advogados', 'icon' => 'users'],
                ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
                ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
            ];
        case 'estagiario':
            return [
                ['href' => 'dashboard-estagiario.php', 'label' => 'Inicio', 'icon' => 'home'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'processos.php', 'label' => 'Processos', 'icon' => 'file'],
                ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
            ];
        case 'admin':
            return $isAdminPath ? [
                ['href' => 'dashboard-admin.php', 'label' => 'Visao geral', 'icon' => 'chart'],
                ['href' => 'usuarios.php', 'label' => 'Usuarios', 'icon' => 'users'],
                ['href' => 'validar-oab.php', 'label' => 'Validar OAB', 'icon' => 'shield'],
                ['href' => 'solicitacoes.php', 'label' => 'Solicitacoes', 'icon' => 'case'],
                ['href' => '../tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => '../agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                ['href' => 'auditoria.php', 'label' => 'Auditoria', 'icon' => 'shield'],
                ['href' => '../notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                ['href' => '../perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
            ] : [
                ['href' => 'admin/dashboard-admin.php', 'label' => 'Visao geral', 'icon' => 'chart'],
                ['href' => 'admin/usuarios.php', 'label' => 'Usuarios', 'icon' => 'users'],
                ['href' => 'admin/validar-oab.php', 'label' => 'Validar OAB', 'icon' => 'shield'],
                ['href' => 'admin/solicitacoes.php', 'label' => 'Solicitacoes', 'icon' => 'case'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'admin/documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                ['href' => 'admin/auditoria.php', 'label' => 'Auditoria', 'icon' => 'shield'],
                ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
            ];
        default:
            return [
                ['href' => 'dashboard-cliente.php', 'label' => 'Inicio', 'icon' => 'home'],
                ['href' => 'processos.php', 'label' => 'Processos', 'icon' => 'file'],
                ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
                ['href' => 'solicitar-ajuda.php', 'label' => 'Solicitar ajuda', 'icon' => 'help'],
                ['href' => 'lista-advogados.php', 'label' => 'Advogados', 'icon' => 'users'],
                ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Solicitacoes', 'icon' => 'case'],
                ['href' => 'tarefas.php', 'label' => 'Tarefas', 'icon' => 'check'],
                ['href' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'calendar'],
                ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
                ['href' => 'notificacoes.php', 'label' => 'Notificacoes', 'icon' => 'bell'],
                ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
            ];
    }
}
