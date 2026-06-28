<?php
/**
 * JusTraduz - Shared Footer Template
 */
$prefix = $pathPrefix ?? '';
?>
  <footer class="site-footer">
    <div class="container footer-shell">
      <div class="footer-grid">
        <div class="footer-brand">
          <a class="footer-logo-text" href="<?= $prefix ?>index.php">Jus<span>Traduz</span></a>
          <p>Direito em linguagem simples para entender documentos, agir com segurança e resolver próximos passos.</p>

          <p class="footer-social-links">
            <a class="footer-social-link footer-social-instagram" href="https://www.instagram.com/justraduz/" target="_blank" rel="noopener" aria-label="Instagram">
              <svg class="footer-social-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="2" y="2" width="20" height="20" rx="5"/>
                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                <path d="M17.5 6.5h.01"/>
              </svg>
            </a>
            <a class="footer-social-link footer-social-github" href="https://github.com/TeamGHCP/justraduz" target="_blank" rel="noopener" aria-label="GitHub">
              <svg class="footer-social-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 19c-5 1.5-5-2.5-7-3"/>
                <path d="M15 22v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 19 4.77 5.07 5.07 0 0 0 18.91 1S17.73.65 15 2.48a13.38 13.38 0 0 0-6 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>
              </svg>
            </a>
          </p>
        </div>

        <nav class="footer-columns" aria-label="Links do rodapé">
          <div class="footer-col">
            <h4>Produto</h4>
            <ul>
              <li><a href="<?= $prefix ?>index.php#recursos"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5Z"/><path d="M8 6h8"/><path d="M8 10h8"/></svg>Recursos</a></li>
              <li><a href="<?= $prefix ?>index.php#fluxo"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h10a4 4 0 0 1 0 8H9a4 4 0 0 0 0 8h11"/><path d="M17 19h3v3"/></svg>Fluxo</a></li>
              <li><a href="<?= $prefix ?>como-funciona"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>Como funciona</a></li>
              <li><a href="<?= $prefix ?>traduzir-juridiques"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m16 16 3-8 5-2-4-4-2 5-8 3-2-2-5 5v5h5z"/><path d="M14 14 9 19"/></svg>Traduzir juridiquês</a></li>
              <li><a href="<?= $prefix ?>simplificar-documento-juridico"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>Simplificar documento</a></li>
              <li><a href="<?= $prefix ?>ajuda-juridica-online"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Ajuda jurídica online</a></li>
              <li><a href="<?= $prefix ?>blog"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>Blog</a></li>
            </ul>
          </div>

          <div class="footer-col">
            <h4>Plataforma</h4>
            <ul>
              <li><a href="<?= $prefix ?>login.html"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>Entrar</a></li>
              <li><a href="<?= $prefix ?>login.html?cadastro"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>Criar conta</a></li>
              <li><a href="<?= $prefix ?>contato"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>Contato</a></li>
              <li><a href="<?= $prefix ?>para-clientes"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Para clientes</a></li>
              <li><a href="<?= $prefix ?>para-advogados"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Para advogados</a></li>
            </ul>
          </div>

          <div class="footer-col">
            <h4>Legal</h4>
            <ul>
              <li><a href="<?= $prefix ?>termos"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>Termos de Uso</a></li>
              <li><a href="<?= $prefix ?>privacidade"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Privacidade</a></li>
              <li><a href="<?= $prefix ?>seguranca-lgpd"><svg class="footer-link-icon svg-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Segurança e LGPD</a></li>
            </ul>
          </div>
        </nav>
      </div>

      <div class="footer-bottom">
        <p>&copy; <span data-current-year></span> JusTraduz. Todos os direitos reservados.</p>
      </div>
    </div>
  </footer>
