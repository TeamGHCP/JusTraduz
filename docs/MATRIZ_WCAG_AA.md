# Matriz WCAG AA pendente

Data da revisão: 23/06/2026

Esta matriz ainda precisa ser validada manualmente. O sistema já possui recursos de acessibilidade, mas a validação WCAG AA formal só deve ser marcada como concluída depois de evidências reais em navegador.

| Critério | Tela/componente | Status | Evidência pendente | Próxima ação |
| --- | --- | --- | --- | --- |
| 1.1.1 Texto alternativo | Logos, avatars e imagens | Pendente | Capturas e inspeção de `alt`/`aria-hidden`. | Revisar imagens em login, dashboards e páginas públicas. |
| 1.3.1 Informação e relações | Formulários principais | Pendente | Conferência de labels e relação campo/erro. | Testar cadastro, login, upload, filtros admin e perfil. |
| 1.4.3 Contraste mínimo | Tema padrão e alto contraste | Pendente | Medição com ferramenta externa. | Medir contraste em desktop e mobile. |
| 2.1.1 Teclado | Menu, formulários, chat e admin | Pendente | Roteiro executado sem mouse. | Navegar pelos fluxos principais usando Tab/Enter/Esc. |
| 2.4.1 Ignorar blocos | Páginas com menu lateral | Pendente | Confirmação de skip link funcional. | Validar em todas as telas autenticadas. |
| 2.4.3 Ordem do foco | Dashboards e formulários | Pendente | Sequência documentada por tela. | Registrar problemas encontrados e corrigir. |
| 2.4.7 Foco visível | Botões, links e campos | Pendente | Capturas do foco em componentes críticos. | Conferir foco em tema claro, escuro e alto contraste. |
| 3.3.1 Identificação de erro | Login, uploads e admin | Pendente | Erros associados a campos e regiões de alerta. | Testar entradas inválidas em cada fluxo. |
| 3.3.2 Labels ou instruções | Campos obrigatórios | Pendente | Lista de campos sem instrução suficiente. | Ajustar labels e textos auxiliares quando necessário. |
| 4.1.2 Nome, função e valor | Sidebar, modais, widgets e VLibras | Pendente | Teste com leitor de tela. | Validar nomes acessíveis e estados ARIA. |

## Falta concluir

- Rodar validação com NVDA ou leitor de tela equivalente.
- Medir contraste por tela.
- Registrar evidências por captura.
- Validar desktop, tablet e celular.
- Atualizar esta matriz com `OK`, `Parcial` ou `Reprovado` somente depois dos testes.
