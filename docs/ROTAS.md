# Rotas e páginas

## Convenção

As rotas backend são acessadas por:

```text
/backend/public/index.php?rota=/caminho
```

As páginas de usuário ficam em:

```text
/frontend/*.php
/frontend/admin/*.php
```

## Backend

| Método | Rota | Controller | Acesso | Finalidade |
|---|---|---|---|---|
| POST | `/auth/registrar` | `AuthController::registrar` | Público | Criar conta de cliente, advogado, estagiário ou admin quando permitido pelo fluxo. |
| POST | `/auth/login` | `AuthController::login` | Público | Login comum por e-mail e senha. |
| GET | `/auth/csrf` | `AuthController::csrf` | Sessão | Obter token CSRF atual. |
| POST | `/auth/force-logout` | `AuthController::forceLogout` | Sessão | Encerrar sessão atual. |
| POST | `/auth/admin-login` | `AuthController::adminLogin` | Público | Login administrativo. |
| POST | `/auth/reset-password` | `AuthController::resetPassword` | Público | Fluxo de recuperação de senha. |
| POST | `/auth/logout` | `AuthController::logout` | Logado | Logout. |
| POST | `/profile/update` | `AuthController::updateProfile` | Logado | Atualizar perfil. |
| POST | `/profile/password-code` | `AuthController::profilePasswordCode` | Logado | Solicitar código para troca de senha no perfil. |
| POST | `/profile/password-reset` | `AuthController::profilePasswordReset` | Logado | Confirmar troca de senha no perfil. |
| POST | `/oab/lookup` | `OabController::lookup` | Público | Consultar OAB/CNA durante cadastro. |
| POST | `/documents/upload` | `DocumentController::upload` | Cliente | Enviar documento. |
| POST | `/documents/analyze` | `DocumentController::analyze` | Logado autorizado | Gerar análise por IA de documento permitido. |
| POST | `/documents/delete` | `DocumentController::delete` | Dono/admin autorizado | Excluir documento permitido. |
| GET | `/documents/download` | `DocumentController::download` | Logado autorizado | Baixar documento permitido. |
| POST | `/cases/create` | `CaseController::create` | Cliente | Criar solicitação de ajuda jurídica. |
| POST | `/cases/accept` | `CaseController::accept` | Advogado | Aceitar caso aberto. |
| POST | `/cases/status` | `CaseController::updateStatus` | Participante/admin conforme regra | Atualizar status de solicitação. |
| POST | `/tasks/create` | `CaseController::createTask` | Advogado/admin | Criar tarefa vinculada a caso. |
| POST | `/tasks/update` | `CaseController::updateTask` | Advogado/admin | Atualizar tarefa. |
| POST | `/messages/send` | `CaseController::sendMessage` | Participante do caso/admin | Enviar mensagem no chat. |
| POST | `/notifications/read` | `NotificationController::markRead` | Logado | Marcar notificação como lida. |
| POST | `/schedule/slots/create` | `ScheduleController::createSlot` | Advogado/estagiário | Criar horário de agenda. |
| POST | `/schedule/slots/update` | `ScheduleController::updateSlot` | Dono/admin | Atualizar horário. |
| POST | `/schedule/book` | `ScheduleController::book` | Cliente | Agendar atendimento. |
| POST | `/schedule/appointments/update` | `ScheduleController::updateAppointment` | Participante/admin | Atualizar agendamento. |
| GET | `/schedule/calendar` | `ScheduleController::calendarData` | Logado | Retornar dados de calendário filtrados por perfil. |
| POST | `/admin/users/status` | `AdminController::updateUserStatus` | Admin | Ativar/inativar usuário. |
| POST | `/admin/cases/update` | `AdminController::updateCase` | Admin | Atualizar solicitação, responsável e prioridade. |
| POST | `/admin/professionals/oab` | `AdminController::updateProfessionalOab` | Admin | Aprovar, reprovar ou revisar validação OAB/CNA. |

## Frontend público

| Página | Finalidade |
|---|---|
| `frontend/index.html` | Landing page do produto. |
| `frontend/login.html` | Login comum. |
| `frontend/cadastro.html` | Cadastro de usuários. |
| `frontend/recuperar-senha.html` | Recuperação de senha. |
| `frontend/privacidade.html` | Política de privacidade. |
| `frontend/termos.html` | Termos de uso. |
| `frontend/admin/login-admin.html` | Login administrativo. |

## Frontend autenticado

| Página | Perfis | Finalidade |
|---|---|---|
| `dashboard-cliente.php` | Cliente | Jornada de envio, análise e ajuda. |
| `dashboard-advogado.php` | Advogado | Mesa de trabalho, fila e prioridades. |
| `dashboard-estagiario.php` | Estagiário | Painel restrito de agenda própria. |
| `visualizar-documento.php` | Cliente, advogado, admin | Listar, abrir, analisar, baixar e excluir documentos conforme permissão. |
| `solicitar-ajuda.php` | Cliente | Criar solicitação jurídica. |
| `acompanhar-solicitacoes.php` | Cliente, advogado, admin | Acompanhar casos. |
| `tarefas.php` | Advogado, admin | Gerenciar tarefas. |
| `agenda.php` | Cliente, advogado, estagiário, admin | Ver agenda, criar slots e agendar atendimentos. |
| `chat.php` | Participantes/admin | Conversa por solicitação. |
| `notificacoes.php` | Logado | Notificações. |
| `perfil.php` | Logado | Perfil e senha. |
| `lista-advogados.php` | Cliente | Lista de profissionais ativos. |

## Frontend admin

| Página | Finalidade |
|---|---|
| `admin/dashboard-admin.php` | Central de operação com métricas, gráficos, OAB, IA, casos e auditoria. |
| `admin/usuarios.php` | Usuários, status e validação OAB/CNA. |
| `admin/solicitacoes.php` | Fila administrativa de casos. |
| `admin/documentos.php` | Auditoria e filtros de documentos/IA. |
| `admin/auditoria.php` | Logs com severidade e JSON legível. |

