# Frontend

## Organizacao

- `index.html`, `login.html`, `cadastro.html`, `recuperar-senha.html`, `termos.html`, `privacidade.html` e `contato.html`: paginas publicas.
- `*.php` na raiz de `frontend/`: wrappers estaveis para paginas autenticadas.
- `admin/*.php`: wrappers estaveis para paginas administrativas.
- `pages/app/`: implementacao real das telas de cliente, advogado e estagiario.
- `pages/admin/`: implementacao real das telas de admin.
- `app/`: bootstrap, sessao, helpers, navegacao e componentes.
- `assets/`: CSS, JavaScript e imagens publicas.

## Regra atual

Paginas HTML antigas da area logada foram removidas. Para novas telas autenticadas, crie a implementacao em `pages/app/` ou `pages/admin/` e, se precisar de URL estavel na raiz, crie apenas um wrapper PHP fino.

## Pendencias para 100%

Validacao visual final, acessibilidade, estados vazios, responsividade completa e demais melhorias de produto ficam centralizadas em `../docs/MELHORIAS_PARA_100.md`.
