# Matriz WCAG AA

Data da validacao inicial: 23/06/2026

Escopo revisado: login/cadastro, dashboards, documentos, casos/chat, admin, validacao OAB, relatorios e navegacao principal.

| Criterio | Tela/componente | Status | Evidencia | Acao recomendada |
| --- | --- | --- | --- | --- |
| 1.1.1 Texto alternativo | Logo, avatar e imagens principais | Parcial | Logos e avatars possuem `alt` ou `aria-hidden` nos componentes principais. | Revisar imagens editoriais futuras antes de publicar. |
| 1.3.1 Informacao e relacoes | Formularios principais | Parcial | Campos revisados usam labels em telas criticas; filtros admin usam labels visiveis. | Validar todos os formulários em QA manual com leitor de tela. |
| 1.4.3 Contraste minimo | Tema padrao e alto contraste | Parcial | Existe modo de alto contraste e foco reforcado em CSS. | Medir contraste com ferramenta dedicada em todas as telas finais. |
| 2.1.1 Teclado | Menu lateral, botoes e formularios | Parcial | Controles principais sao botoes/links nativos e foco visivel foi reforcado. | Executar roteiro completo somente por teclado. |
| 2.4.1 Ignorar blocos | Navegacao principal | Parcial | Classe `.skip-link` existe no CSS. | Garantir skip link renderizado em todas as telas base. |
| 2.4.3 Ordem do foco | Dashboards e admin | Parcial | Estrutura segue ordem DOM natural. | Validar em desktop e mobile apos ajustes visuais. |
| 2.4.7 Foco visivel | CSS global | OK | `:focus-visible` reforcado em `base.css` e `accessibility.css`. | Manter regra em novos componentes. |
| 3.3.1 Identificacao de erro | Login, uploads e admin | Parcial | Mensagens de erro usam alerts e query messages. | Associar erros inline com `aria-describedby` em formularios longos. |
| 3.3.2 Labels ou instrucoes | Formularios de login/cadastro/admin | Parcial | Labels existem nos fluxos principais revisados. | Auditar campos dinamicos e filtros avancados. |
| 4.1.2 Nome, funcao e valor | Botoes, menus e widgets | Parcial | Sidebar usa `aria-expanded`, `aria-controls` e nomes acessiveis. | Testar widgets de chat, VLibras e acessibilidade com leitor de tela. |

## Pendencias formais

- Rodar validacao manual com NVDA ou leitor de tela equivalente.
- Medir contraste por tela com ferramenta externa.
- Registrar evidencias por captura para login, dashboards, documentos, casos/chat, admin e validacao OAB.
- Completar matriz depois do QA manual em desktop, tablet e celular.
