# Frontend

Os arquivos na raiz de `frontend/` continuam existindo como entradas públicas para manter compatibilidade com links antigos.

A implementação das telas PHP fica organizada em:

- `app/`: bootstrap, sessão, helpers, navegação e componentes reutilizáveis.
- `pages/app/`: telas autenticadas usadas por clientes, advogados e estagiários.
- `pages/admin/`: telas da área administrativa.
- `assets/`: CSS, JavaScript e imagens públicos.

Ao criar uma tela nova, prefira colocá-la em `pages/app/` ou `pages/admin/` e deixe na raiz apenas um wrapper fino quando precisar manter uma URL pública estável.
