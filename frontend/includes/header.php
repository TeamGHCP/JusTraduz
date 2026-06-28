<?php
/**
 * JusTraduz - Shared Header Template
 */
$prefix = $pathPrefix ?? '';
$activePage = $activePage ?? '';
?>
  <header class="site-header home-header" data-site-header>
    <div class="container nav-bar">
      <a class="brand" href="<?= $prefix ?>index.php" aria-label="JusTraduz">
        <img src="<?= $prefix ?>assets/img/logo.png" alt="JusTraduz">
      </a>

      <nav class="nav-links" aria-label="Menu principal">
        <a href="<?= $prefix ?>index.php#recursos">Recursos</a>
        <a href="<?= $prefix ?>index.php#fluxo">Fluxo</a>
        <a href="<?= $prefix ?>index.php#seguranca">Segurança</a>
        <a class="<?= $activePage === 'como-funciona' ? 'is-active' : '' ?>" href="<?= $prefix ?>como-funciona">Como funciona</a>
        <a class="<?= $activePage === 'blog' ? 'is-active' : '' ?>" href="<?= $prefix ?>blog">Blog</a>
      </nav>

      <div class="nav-actions">
        <a class="btn btn-outline btn-sm" href="<?= $prefix ?>login.html">Entrar</a>
        <a class="btn btn-primary btn-sm" href="<?= $prefix ?>login.html?cadastro">Cadastrar</a>
      </div>

      <button class="mobile-toggle" type="button" data-nav-toggle aria-label="Abrir menu">
        <svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M4 7h16"/>
          <path d="M4 12h16"/>
          <path d="M4 17h16"/>
        </svg>
      </button>
    </div>
  </header>
