'use strict';

function initializeForms(root) {
root.querySelectorAll('[data-user-form]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    const fullName = form.elements.full_name.value.trim();
    const username = form.elements.username.value.trim();
    const email = form.elements.email.value.trim();
    const passwordField = form.elements.password;
    const password = passwordField ? passwordField.value : '';
    const messages = [];

    if (!/^[\p{L}][\p{L}\p{M} .,'-]{2,149}$/u.test(fullName)) {
      messages.push('Enter a valid full name using letters and common name punctuation only.');
    }
    if (!/^[A-Za-z0-9_.-]{3,50}$/.test(username)) {
      messages.push('Username must be 3-50 characters and use only letters, numbers, dots, underscores, or hyphens.');
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      messages.push('Enter a valid email address.');
    }
    if (passwordField && passwordField.required && (password.length < 12 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password) || password === '123')) {
      messages.push('Password must be at least 12 characters with uppercase, lowercase, and a number.');
    }
    if (passwordField && passwordField.hasAttribute('data-optional-strong-password') && password !== '') {
      if (password.length < 12 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password) || password === '123') {
        messages.push('Optional password reset must be at least 12 characters with uppercase, lowercase, and a number, and must not be 123.');
      }
    }

    if (messages.length) {
      event.preventDefault();
      let summary = form.querySelector('[data-validation-summary]');
      if (!summary) {
        summary = document.createElement('div');
        summary.className = 'notice error';
        summary.dataset.validationSummary = 'true';
        summary.setAttribute('role', 'alert');
        summary.tabIndex = -1;
        form.prepend(summary);
      }
      summary.innerHTML = '<ul>' + messages.map((message) => `<li>${message.replace(/</g, '&lt;')}</li>`).join('') + '</ul>';
      summary.focus();
    } else {
      form.querySelector('[data-validation-summary]')?.remove();
    }
  });
});

root.querySelectorAll('[data-student-form]').forEach((form) => {
  const validateField = (field) => {
    const value = field.value.trim();
    field.setCustomValidity('');
    if (field.name === 'student_number' && value && !/^\d{3,30}$/.test(value)) field.setCustomValidity('Student number must contain digits only (3-30 digits).');
    if (field.name === 'first_name' && value && !/^[\p{L}][\p{L}\p{M} .,'-]*$/u.test(value)) field.setCustomValidity('First name must use letters and common name punctuation only.');
    if (field.name === 'last_name' && value && !/^[\p{L}][\p{L}\p{M} .,'-]*$/u.test(value)) field.setCustomValidity('Last name must use letters and common name punctuation only.');
    if (field.name === 'phone' && value && !/^[0-9+() -]{7,20}$/.test(value)) field.setCustomValidity('Enter a valid phone number.');
    if (field.name === 'year_level' && value && (!Number.isInteger(Number(value)) || Number(value) < 1 || Number(value) > 4)) field.setCustomValidity('Year level must be from 1 to 4.');
  };

  form.querySelectorAll('input').forEach((field) => {
    validateField(field);
    field.addEventListener('input', () => validateField(field));
    field.addEventListener('change', () => validateField(field));
  });
});

const loginUsernamePreview = (lastName, firstName) => {
  const letters = (value) => (String(value).match(/\p{L}/gu) || []).join('');
  const last = letters(lastName);
  const first = letters(firstName);
  if (!last || !first) return '';
  return last.charAt(0).toUpperCase() + last.slice(1).toLowerCase() + '_' + first.charAt(0).toUpperCase();
};

root.querySelectorAll('[data-username-preview]').forEach((preview) => {
  const form = preview.closest('form');
  if (!form) return;
  const update = () => {
    const built = loginUsernamePreview(form.elements.last_name?.value || '', form.elements.first_name?.value || '');
    preview.textContent = built || '—';
  };
  ['last_name', 'first_name'].forEach((name) => {
    const field = form.elements[name];
    if (!field) return;
    field.addEventListener('input', update);
    field.addEventListener('change', update);
  });
  update();
});

root.querySelectorAll('[data-change-password-form]').forEach((form) => {
  const password = form.elements.password;
  const confirm = form.elements.password_confirm;
  const rules = form.querySelector('[data-password-rules]');
  const matchStatus = form.querySelector('[data-password-match]');
  if (!password || !confirm) return;

  const evaluate = () => {
    const value = password.value;
    const checks = {
      length: value.length >= 12,
      upper: /[A-Z]/.test(value),
      lower: /[a-z]/.test(value),
      digit: /\d/.test(value),
      'not-temp': value !== '' && value !== '123',
    };
    rules?.querySelectorAll('[data-rule]').forEach((item) => {
      item.classList.toggle('is-met', Boolean(checks[item.dataset.rule]));
    });
    const strong = checks.length && checks.upper && checks.lower && checks.digit && checks['not-temp'];
    const matched = confirm.value !== '' && value === confirm.value;
    password.setCustomValidity(strong || value === '' ? '' : 'Password must be at least 12 characters with uppercase, lowercase, and a number, and must not be 123.');
    confirm.setCustomValidity(confirm.value === '' || matched ? '' : 'Password confirmation does not match.');
    if (matchStatus) {
      matchStatus.classList.toggle('is-ok', matched);
      matchStatus.classList.toggle('is-bad', confirm.value !== '' && !matched);
      if (confirm.value === '') matchStatus.textContent = 'Confirm must match the new password.';
      else if (matched) matchStatus.textContent = 'Passwords match.';
      else matchStatus.textContent = 'Passwords do not match yet.';
    }
  };

  password.addEventListener('input', evaluate);
  confirm.addEventListener('input', evaluate);
  form.addEventListener('submit', (event) => {
    evaluate();
    if (!form.checkValidity()) {
      event.preventDefault();
      let summary = form.querySelector('[data-validation-summary]');
      if (!summary) {
        summary = document.createElement('div');
        summary.className = 'notice error';
        summary.dataset.validationSummary = 'true';
        summary.setAttribute('role', 'alert');
        summary.tabIndex = -1;
        form.prepend(summary);
      }
      const messages = [];
      if (password.validationMessage) messages.push(password.validationMessage);
      if (confirm.validationMessage) messages.push(confirm.validationMessage);
      summary.innerHTML = '<ul>' + messages.map((message) => `<li>${message.replace(/</g, '&lt;')}</li>`).join('') + '</ul>';
      summary.focus();
    } else {
      form.querySelector('[data-validation-summary]')?.remove();
    }
  });
  evaluate();
});

root.querySelectorAll('.student-picker').forEach((picker) => {
  const search = picker.querySelector('[data-filter-students]');
  const rows = [...picker.querySelectorAll('[data-student-option]')];
  const count = picker.querySelector('[data-selection-count]');
  const empty = picker.querySelector('[data-no-student-results]');
  search.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase();
    rows.forEach((row) => { row.hidden = !row.textContent.toLocaleLowerCase().includes(query); });
    empty.hidden = !rows.length || rows.some((row) => !row.hidden);
  });
  picker.addEventListener('change', () => {
    count.textContent = `${picker.querySelectorAll('input[type="checkbox"]:checked').length} selected`;
  });
});

}

initializeForms(document);

function bindOptionalStrongPasswords(root = document) {
  root.querySelectorAll('[data-optional-strong-password]').forEach((input) => {
    if (input.dataset.optionalBound) return;
    input.dataset.optionalBound = 'true';
    const evaluate = () => {
      const value = input.value;
      if (value === '') {
        input.setCustomValidity('');
        return;
      }
      const strong = value.length >= 12 && /[A-Z]/.test(value) && /[a-z]/.test(value) && /\d/.test(value) && value !== '123';
      input.setCustomValidity(strong ? '' : 'Password must be at least 12 characters with uppercase, lowercase, and a number, and must not be 123.');
    };
    input.addEventListener('input', evaluate);
    evaluate();
  });
}

bindOptionalStrongPasswords(document);

function bindPasswordToggles(root = document) {
  root.querySelectorAll('[data-password-toggle]').forEach((button) => {
    if (button.dataset.toggleBound) return;
    button.dataset.toggleBound = 'true';
    button.addEventListener('click', () => {
      const field = button.closest('.password-field');
      const input = field?.querySelector('input');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', show ? 'true' : 'false');
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      button.title = show ? 'Hide password' : 'Show password';
      const eye = button.querySelector('.icon-eye');
      const eyeOff = button.querySelector('.icon-eye-off');
      if (eye) eye.hidden = show;
      if (eyeOff) eyeOff.hidden = !show;
    });
  });
}

bindPasswordToggles(document);

function prepareDialog(dialog, opener, dynamic = false) {
  if (dialog.dataset.modalReady) return;
  dialog.dataset.modalReady = 'true';
  dialog.addEventListener('close', () => {
    document.body.classList.remove('modal-open');
    if (dynamic) dialog.remove();
    else if (dialog.dataset.returnUrl) {
      window.location.assign(dialog.dataset.returnUrl);
      return;
    }
    if (opener?.isConnected) opener.focus();
  });
}

function openDialog(dialog, opener, dynamic = false) {
  prepareDialog(dialog, opener, dynamic);
  // Removing the server fallback attribute avoids firing a synthetic close event.
  dialog.removeAttribute('open');
  dialog.showModal();
  document.body.classList.add('modal-open');
  const firstField = dialog.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea');
  firstField?.focus({ preventScroll: true });
}

function setModalStatus(message, busy = false) {
  const status = document.getElementById('modal-status');
  if (!status) return;
  status.textContent = message;
  status.setAttribute('aria-busy', busy ? 'true' : 'false');
  status.classList.toggle('notice', Boolean(message));
  status.classList.toggle('sr-only', !message);
  status.classList.toggle('modal-status', Boolean(message));
}

let modalLoading = false;

async function openEditorUrl(url, opener) {
  if (modalLoading) return;
  modalLoading = true;
  if (opener?.setAttribute) opener.setAttribute('aria-busy', 'true');
  setModalStatus('Loading form…', true);
  try {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error('Form unavailable');
    const html = new DOMParser().parseFromString(await response.text(), 'text/html');
    const dialog = html.querySelector('dialog[open]');
    if (!dialog) throw new Error('Form unavailable');
    const prefix = `modal-${Date.now()}-`;
    const ids = new Map([...dialog.querySelectorAll('[id]')].map((node) => [node.id, prefix + node.id]));
    dialog.querySelectorAll('[id]').forEach((node) => { node.id = ids.get(node.id); });
    [dialog, ...dialog.querySelectorAll('*')].forEach((node) => {
      ['for', 'aria-labelledby', 'aria-describedby'].forEach((attribute) => {
        if (node.hasAttribute(attribute)) node.setAttribute(attribute, node.getAttribute(attribute).split(' ').map((id) => ids.get(id) || id).join(' '));
      });
    });
    dialog.querySelectorAll('form').forEach((form) => {
      form.action = new URL(form.getAttribute('action') || url.href, url).href;
    });
    document.body.append(dialog);
    initializeForms(dialog);
    bindPasswordToggles(dialog);
    bindOptionalStrongPasswords(dialog);
    setModalStatus('');
    openDialog(dialog, opener?.focus ? opener : null, true);
  } catch {
    setModalStatus('');
    window.location.assign(url.href);
  } finally {
    modalLoading = false;
    if (opener?.removeAttribute) opener.removeAttribute('aria-busy');
  }
}

document.querySelectorAll('dialog[open]').forEach((dialog) => openDialog(dialog));

document.addEventListener('click', async (event) => {
  const confirmControl = event.target.closest('[data-confirm]');
  if (confirmControl && !window.confirm(confirmControl.dataset.confirm)) {
    event.preventDefault();
    return;
  }
  const close = event.target.closest('[data-close-dialog]');
  if (close) { close.closest('dialog')?.close(); return; }
  const opener = event.target.closest('[data-open-dialog]');
  if (opener) {
    const dialog = document.getElementById(opener.dataset.openDialog);
    if (dialog && !dialog.open) openDialog(dialog, opener);
    return;
  }
  const row = event.target.closest('tr.row-link[data-href]');
  if (row && !event.target.closest('a, button, input, select, textarea, label')) {
    event.preventDefault();
    await openEditorUrl(new URL(row.dataset.href, location.href), row);
    return;
  }
  const link = event.target.closest('a[href]');
  if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
  const url = new URL(link.href, location.href);
  if (url.origin !== location.origin) return;
  const page = url.searchParams.get('page');
  const isEditor = ['student_form', 'user_form'].includes(page)
    || (['blocks','courses','departments'].includes(page) && (url.searchParams.has('add') || url.searchParams.has('edit')))
    || (page === 'teachers' && (url.searchParams.has('edit') || url.searchParams.has('add') || url.searchParams.has('assign')))
    || (page === 'users' && url.searchParams.has('add_teacher'));
  if (!isEditor) return;
  event.preventDefault();
  await openEditorUrl(url, link);
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter' && event.key !== ' ') return;
  const row = event.target.closest('tr.row-link[data-href]');
  if (!row || event.target !== row) return;
  event.preventDefault();
  openEditorUrl(new URL(row.dataset.href, location.href), row);
});
