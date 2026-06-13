(function () {
  'use strict';

  var lastHelpTrigger = null;

  function escapeHtml(value) {
    var node = document.createElement('div');
    node.textContent = String(value || '');
    return node.innerHTML;
  }

  function closeModal(modal, returnFocus) {
    modal.remove();
    if (returnFocus && returnFocus.focus) returnFocus.focus();
  }

  function descriptionFor(title) {
    var normalized = String(title || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    var descriptions = {
      documento: 'Consulte ou gerencie documentos autorizados para seu perfil. Confira o arquivo e preserve o sigilo do conteúdo.',
      tarefa: 'Organize ações pendentes com instruções objetivas. Confira responsável e prazo antes de alterar.',
      agenda: 'Consulte ou gerencie horários e atendimentos. Confirme data, responsável e disponibilidade.',
      atendimento: 'Acompanhe informações do atendimento. Mantenha mensagens, documentos e decisões no caso correto.',
      solicitacao: 'Consulte pedidos de ajuda e seus status. Verifique responsável e prioridade antes de agir.',
      caso: 'Acompanhe o trabalho jurídico vinculado ao cliente. Respeite permissões e acesso mínimo.',
      perfil: 'Revise dados da conta e controles de segurança. Mantenha as informações atualizadas.',
      seguranca: 'Use estes controles para proteger a conta. Não compartilhe senhas, códigos ou dados sensíveis.',
      auditoria: 'Consulte eventos para segurança e governança. Investigue sem copiar informações sensíveis.',
      usuario: 'Consulte contas e permissões. Confira o perfil e a finalidade antes de realizar alterações.',
      oab: 'Valide dados profissionais com cuidado e registre a justificativa das decisões.',
      notificacao: 'Acompanhe avisos do sistema e abra o item relacionado para conferir os detalhes.',
      analise: 'Consulte o resultado como apoio informativo. A análise automática não substitui orientação jurídica.',
      processo: 'Confira número, tribunal e data de atualização antes de usar a informação processual.'
    };
    var keys = Object.keys(descriptions);
    for (var i = 0; i < keys.length; i++) {
      if (normalized.indexOf(keys[i]) !== -1) return descriptions[keys[i]];
    }
    return 'Use esta área para consultar e executar esta função. Confira os dados antes de alterar e preserve informações pessoais ou jurídicas.';
  }

  function addSectionHelp() {
    document.querySelectorAll('main .dash-section-title h2').forEach(function (heading) {
      if (heading.querySelector('.help-dot')) return;
      var title = heading.textContent.trim();
      if (!title) return;
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'help-dot';
      button.dataset.helpTitle = title;
      button.dataset.helpDescription = descriptionFor(title);
      button.setAttribute('aria-label', 'Ajuda: ' + title);
      button.innerHTML = '<svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
        '<path d="M12 3.25a8.75 8.75 0 0 0-7.62 13.05l-1.13 4.45 4.45-1.13A8.75 8.75 0 1 0 12 3.25Z"></path>' +
        '<path d="M9.35 9.45a2.75 2.75 0 1 1 4.85 1.78c-.95.82-1.7 1.3-1.7 2.77"></path>' +
        '<path d="M12.5 17.15h.01"></path></svg>';
      heading.appendChild(document.createTextNode(' '));
      heading.appendChild(button);
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-help-title]');
    if (!button) return;

    event.preventDefault();
    event.stopPropagation();
    lastHelpTrigger = button;

    var existing = document.querySelector('.context-help-modal');
    if (existing) existing.remove();

    var modal = document.createElement('div');
    modal.className = 'context-help-modal';
    modal.innerHTML = '<section class="context-help-dialog" role="dialog" aria-modal="true" aria-labelledby="context-help-title">' +
      '<h2 id="context-help-title">' + escapeHtml(button.dataset.helpTitle) + '</h2>' +
      '<p>' + escapeHtml(button.dataset.helpDescription) + '</p>' +
      '<div class="onboarding-actions"><button class="btn btn-primary" type="button" data-help-close>Entendi</button></div></section>';
    document.body.appendChild(modal);

    var closeButton = modal.querySelector('[data-help-close]');
    closeButton.addEventListener('click', function () { closeModal(modal, button); });
    modal.addEventListener('click', function (modalEvent) {
      if (modalEvent.target === modal) closeModal(modal, button);
    });
    closeButton.focus();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var modal = document.querySelector('.context-help-modal');
    if (modal) closeModal(modal, lastHelpTrigger);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addSectionHelp);
  } else {
    addSectionHelp();
  }
}());
