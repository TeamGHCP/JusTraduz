(function () {
  'use strict';

  var lastHelpTrigger = null;

  function svgPath(d) {
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', d);
    return path;
  }

  function helpIcon() {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'svg-icon');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.appendChild(svgPath('M12 3.25a8.75 8.75 0 0 0-7.62 13.05l-1.13 4.45 4.45-1.13A8.75 8.75 0 1 0 12 3.25Z'));
    svg.appendChild(svgPath('M9.35 9.45a2.75 2.75 0 1 1 4.85 1.78c-.95.82-1.7 1.3-1.7 2.77'));
    svg.appendChild(svgPath('M12.5 17.15h.01'));
    return svg;
  }

  function buildModal(title, description) {
    var modal = document.createElement('div');
    modal.className = 'context-help-modal';

    var dialog = document.createElement('section');
    dialog.className = 'context-help-dialog';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'context-help-title');

    var heading = document.createElement('h2');
    heading.id = 'context-help-title';
    heading.textContent = String(title || '');

    var paragraph = document.createElement('p');
    paragraph.textContent = String(description || '');

    var actions = document.createElement('div');
    actions.className = 'onboarding-actions';

    var close = document.createElement('button');
    close.className = 'btn btn-primary';
    close.type = 'button';
    close.dataset.helpClose = '';
    close.textContent = 'Entendi';

    actions.appendChild(close);
    dialog.appendChild(heading);
    dialog.appendChild(paragraph);
    dialog.appendChild(actions);
    modal.appendChild(dialog);

    return modal;
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
      button.appendChild(helpIcon());
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

    var modal = buildModal(button.dataset.helpTitle, button.dataset.helpDescription);
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
