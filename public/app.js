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
    if (passwordField && passwordField.required && (password.length < 12 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password))) {
      messages.push('Password must be at least 12 characters with uppercase, lowercase, and a number.');
    }
    if (passwordField && passwordField.hasAttribute('data-optional-strong-password') && password !== '') {
      if (password.length < 12 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password)) {
        messages.push('Optional password reset must be at least 12 characters with uppercase, lowercase, and a number.');
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
    if (field.name === 'student_number' && value && !/^[A-Za-z0-9][A-Za-z0-9-]{1,28}[A-Za-z0-9]$/.test(value)) field.setCustomValidity('Student number must be 3–30 characters using letters, numbers, and hyphens.');
    if (field.name === 'first_name' && value && !/^[\p{L}][\p{L}\p{M} .,'-]*$/u.test(value)) field.setCustomValidity('First name must use letters and common name punctuation only.');
    if (field.name === 'last_name' && value && !/^[\p{L}][\p{L}\p{M} .,'-]*$/u.test(value)) field.setCustomValidity('Last name must use letters and common name punctuation only.');
    if (field.name === 'phone' && value && !/^[0-9+() -]{7,20}$/.test(value)) field.setCustomValidity('Enter a valid phone number.');
    if (field.name === 'year_level' && value && (!Number.isInteger(Number(value)) || Number(value) < 1 || Number(value) > 4)) field.setCustomValidity('College year must be 1st through 4th year.');
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
    };
    rules?.querySelectorAll('[data-rule]').forEach((item) => {
      item.classList.toggle('is-met', Boolean(checks[item.dataset.rule]));
    });
    const strong = checks.length && checks.upper && checks.lower && checks.digit;
    const matched = confirm.value !== '' && value === confirm.value;
    password.setCustomValidity(strong || value === '' ? '' : 'Password must be at least 12 characters with uppercase, lowercase, and a number.');
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

}

initializeForms(document);

document.querySelectorAll('details.nav-more').forEach((details) => {
  document.addEventListener('click', (event) => {
    if (!details.open || details.contains(event.target)) return;
    details.open = false;
  });
  details.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && details.open) {
      details.open = false;
      details.querySelector('summary')?.focus();
    }
  });
});

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
      const strong = value.length >= 12 && /[A-Z]/.test(value) && /[a-z]/.test(value) && /\d/.test(value);
      input.setCustomValidity(strong ? '' : 'Password must be at least 12 characters with uppercase, lowercase, and a number.');
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

function dismissAnnouncement(announcement) {
  if (!announcement || announcement.classList.contains('is-leaving')) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const remove = () => announcement.closest('.announcement-layer')?.remove();
  if (reduced) {
    remove();
    return;
  }
  announcement.classList.add('is-leaving');
  announcement.addEventListener('animationend', remove, { once: true });
  window.setTimeout(remove, 220);
}

function bindAnnouncements(root = document) {
  root.querySelectorAll('[data-announcement]').forEach((announcement) => {
    if (announcement.dataset.announcementBound) return;
    announcement.dataset.announcementBound = 'true';
    const close = announcement.querySelector('[data-dismiss-announcement]');
    close?.addEventListener('click', () => dismissAnnouncement(announcement));
    const timeout = Number(announcement.dataset.announcementTimeout || 0);
    if (!timeout) return;
    let timer = window.setTimeout(() => dismissAnnouncement(announcement), timeout);
    const pause = () => window.clearTimeout(timer);
    const resume = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => dismissAnnouncement(announcement), timeout);
    };
    announcement.addEventListener('mouseenter', pause);
    announcement.addEventListener('focusin', pause);
    announcement.addEventListener('mouseleave', resume);
    announcement.addEventListener('focusout', (event) => {
      if (!announcement.contains(event.relatedTarget)) resume();
    });
  });
}

bindAnnouncements(document);

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  if (document.querySelector('dialog[open]')) return;
  const announcement = document.querySelector('[data-announcement]');
  if (!announcement) return;
  event.preventDefault();
  dismissAnnouncement(announcement);
});

function prepareDialog(dialog, opener, dynamic = false) {
  if (dialog.dataset.modalReady) return;
  dialog.dataset.modalReady = 'true';
  dialog.addEventListener('close', () => {
    if (dynamic) dialog.remove();
    else if (dialog.dataset.returnUrl && !document.querySelector('dialog[open]')) {
      window.location.assign(dialog.dataset.returnUrl);
      return;
    }
    if (!document.querySelector('dialog[open]')) {
      document.body.classList.remove('modal-open');
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
  if (firstField) {
    firstField.focus({ preventScroll: true });
  } else {
    dialog.tabIndex = -1;
    dialog.focus({ preventScroll: true });
  }
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

function resolveEditorUrl(href) {
  const base = new URL(window.location.pathname, window.location.origin);
  return new URL(href, base);
}

let modalLoading = false;

async function openEditorUrl(url, opener) {
  if (modalLoading) return;
  modalLoading = true;
  if (opener?.setAttribute) opener.setAttribute('aria-busy', 'true');
  setModalStatus('Loading form…', true);
  const nested = Boolean(document.querySelector('dialog[open]'));
  try {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'text/html', 'X-Requested-With': 'fetch' },
    });
    if (!response.ok) throw new Error('Form unavailable');
    const html = new DOMParser().parseFromString(await response.text(), 'text/html');
    const dialog = html.querySelector('dialog[open], dialog');
    if (!dialog) throw new Error('Form unavailable');
    dialog.setAttribute('open', '');
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
    // Keep parent roster/list dialogs open; stack the next editor on top.
    document.body.append(dialog);
    initializeForms(dialog);
    bindPasswordToggles(dialog);
    bindOptionalStrongPasswords(dialog);
    setModalStatus('');
    openDialog(dialog, opener?.focus ? opener : null, true);
  } catch (error) {
    setModalStatus('');
    if (nested) {
      setModalStatus('Could not open that form. Try again, or refresh the page.');
      console.error(error);
    } else {
      window.location.assign(url.href);
    }
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
    event.stopPropagation();
    await openEditorUrl(resolveEditorUrl(row.dataset.href), row);
    return;
  }
  const link = event.target.closest('a[href]');
  if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
  const url = new URL(link.href, location.href);
  if (url.origin !== location.origin) return;
  const page = url.searchParams.get('page');
  const isEditor = ['student_form', 'user_form', 'submit_scores'].includes(page)
    || (['blocks','courses','departments'].includes(page) && (url.searchParams.has('add') || url.searchParams.has('edit')))
    || (['assigned_blocks', 'my_subjects', 'student_subjects'].includes(page) && url.searchParams.has('assignment_id'))
    || (page === 'enrollments' && url.searchParams.has('id'))
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
  openEditorUrl(resolveEditorUrl(row.dataset.href), row);
});

function formatWeightSum(value) {
  const rounded = Math.round(value * 100) / 100;
  return String(rounded).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
}

function refreshWeightSums(form) {
  ['category', 'term'].forEach((group) => {
    const inputs = form.querySelectorAll(`[data-weight-group="${group}"]`);
    const panel = form.querySelector(`[data-weight-sum="${group}"]`);
    if (!panel || !inputs.length) return;
    let total = 0;
    inputs.forEach((input) => {
      const n = Number.parseFloat(input.value);
      if (!Number.isNaN(n)) total += n;
    });
    const ok = Math.abs(total - 100) < 0.01;
    panel.dataset.ok = ok ? '1' : '0';
    const valueEl = panel.querySelector('[data-weight-sum-value]');
    const hintEl = panel.querySelector('[data-weight-sum-hint]');
    if (valueEl) valueEl.textContent = formatWeightSum(total);
    if (hintEl) hintEl.textContent = ok ? ' (ready)' : ' — must equal 100';
  });
}

document.querySelectorAll('[data-grading-weights]').forEach((form) => {
  const submit = form.querySelector('button[type="submit"]');
  const update = () => {
    refreshWeightSums(form);
    if (!submit) return;
    const bad = [...form.querySelectorAll('[data-weight-sum]')].some((panel) => panel.dataset.ok !== '1');
    submit.disabled = bad;
  };
  form.addEventListener('input', update);
  update();
});

document.querySelectorAll('[data-score-draft-form]').forEach((form) => {
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-confirm-unsaved]');
    if (!link || !dirty) return;
    if (!window.confirm(link.dataset.confirmUnsaved)) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('[data-no-middle-name]').forEach((box) => {
  const input = document.getElementById('enroll-middle');
  if (!input) return;
  const sync = () => {
    input.disabled = box.checked;
    if (box.checked) input.value = '';
  };
  box.addEventListener('change', sync);
  sync();
});

document.querySelectorAll('[data-enroll-app-type]').forEach((select) => {
  const form = select.closest('form');
  if (!form) return;
  const priorBlocks = form.querySelectorAll('[data-enroll-prior-school]');
  const newProgramBlocks = form.querySelectorAll('[data-enroll-new-program]');
  const currentProgramBlock = form.querySelector('[data-enroll-current-program]');
  const studentIdBlock = form.querySelector('[data-enroll-student-id]');
  const newIdNote = form.querySelector('[data-enroll-new-id-note]');
  const studentIdInput = form.querySelector('[data-enroll-student-id-input]');
  const schoolInput = form.querySelector('#enroll-school');
  const courseNew = form.querySelector('[data-enroll-course-new]');
  const courseSecond = form.querySelector('[data-enroll-course-second]');
  const courseCurrent = form.querySelector('[data-enroll-course-current]');

  const setBlockEnabled = (block, enabled, clearValues) => {
    if (!block) return;
    block.hidden = !enabled;
    block.querySelectorAll('input, select, textarea').forEach((field) => {
      field.disabled = !enabled;
      if (!enabled && clearValues && field.type !== 'hidden') field.value = '';
    });
  };

  const sync = () => {
    const movingUp = select.value === 'moving_up';
    priorBlocks.forEach((block) => {
      block.hidden = movingUp;
      block.querySelectorAll('input, select, textarea').forEach((field) => {
        if (movingUp) {
          if (field === schoolInput) field.required = false;
          field.disabled = true;
          if (field.type !== 'hidden') field.value = '';
        } else {
          field.disabled = false;
          if (field === schoolInput) field.required = true;
        }
      });
    });

    newProgramBlocks.forEach((block) => setBlockEnabled(block, !movingUp, movingUp));
    setBlockEnabled(currentProgramBlock, movingUp, !movingUp);
    setBlockEnabled(studentIdBlock, movingUp, !movingUp);
    if (newIdNote) newIdNote.hidden = movingUp;
    if (studentIdInput) {
      studentIdInput.required = movingUp;
      studentIdInput.disabled = !movingUp;
      if (!movingUp) studentIdInput.value = '';
    }

    if (courseNew) {
      courseNew.required = !movingUp;
      courseNew.disabled = movingUp;
      if (movingUp) courseNew.name = '';
      else courseNew.name = 'course_id';
    }
    if (courseSecond) {
      courseSecond.disabled = movingUp;
      if (movingUp) {
        courseSecond.value = '';
        courseSecond.name = '';
      } else {
        courseSecond.name = 'course_id_second';
      }
    }
    if (courseCurrent) {
      courseCurrent.required = movingUp;
      courseCurrent.disabled = !movingUp;
      if (movingUp) courseCurrent.name = 'course_id';
      else {
        courseCurrent.name = '';
        courseCurrent.value = '';
      }
    }
  };
  select.addEventListener('change', sync);
  sync();
});

document.querySelectorAll('[data-enroll-dob]').forEach((dob) => {
  const out = document.querySelector('[data-enroll-age]');
  if (!out) return;
  const update = () => {
    if (!dob.value) {
      out.textContent = 'Age is calculated from date of birth.';
      return;
    }
    const born = new Date(dob.value + 'T00:00:00');
    if (Number.isNaN(born.getTime())) return;
    const today = new Date();
    let age = today.getFullYear() - born.getFullYear();
    const m = today.getMonth() - born.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < born.getDate())) age -= 1;
    out.textContent = 'Age: ' + age;
  };
  dob.addEventListener('change', update);
  update();
});

document.querySelectorAll('[data-ph-locations]').forEach(async (root) => {
  const url = root.dataset.locationsUrl;
  const provinceSelect = root.querySelector('[data-ph-province]');
  const citySelect = root.querySelector('[data-ph-city]');
  const barangaySelect = root.querySelector('[data-ph-barangay]');
  const provinceName = root.querySelector('[data-ph-province-name]');
  const cityName = root.querySelector('[data-ph-city-name]');
  const barangayName = root.querySelector('[data-ph-barangay-name]');
  if (!url || !provinceSelect || !citySelect || !barangaySelect) return;

  let data;
  try {
    const res = await fetch(url, { credentials: 'same-origin' });
    data = await res.json();
  } catch (err) {
    provinceSelect.innerHTML = '<option value="">Could not load locations</option>';
    return;
  }

  const provinces = data.provinces || [];
  const prefProvince = root.dataset.province || '';
  const prefCity = root.dataset.city || '';
  const prefBarangay = root.dataset.barangay || '';

  const fill = (select, items, placeholder, selected) => {
    select.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder;
    select.appendChild(opt0);
    items.forEach((item) => {
      const opt = document.createElement('option');
      opt.value = item.code;
      opt.textContent = item.name;
      if (item.code === selected) opt.selected = true;
      select.appendChild(opt);
    });
  };

  fill(provinceSelect, provinces, 'Select province', prefProvince);

  const syncCity = () => {
    const province = provinces.find((p) => p.code === provinceSelect.value);
    provinceName.value = province ? province.name : '';
    const cities = province ? province.cities || [] : [];
    fill(citySelect, cities, 'Select city / municipality', prefCity);
    citySelect.disabled = !province;
    barangaySelect.disabled = true;
    fill(barangaySelect, [], 'Select barangay', '');
    barangayName.value = '';
    if (prefCity && cities.some((c) => c.code === prefCity)) {
      citySelect.value = prefCity;
      syncBarangay();
    }
  };

  const syncBarangay = () => {
    const province = provinces.find((p) => p.code === provinceSelect.value);
    const city = province ? (province.cities || []).find((c) => c.code === citySelect.value) : null;
    cityName.value = city ? city.name : '';
    const barangays = city ? city.barangays || [] : [];
    fill(barangaySelect, barangays, 'Select barangay', prefBarangay);
    barangaySelect.disabled = !city;
    if (prefBarangay && barangays.some((b) => b.code === prefBarangay)) {
      barangaySelect.value = prefBarangay;
    }
    const brgy = barangays.find((b) => b.code === barangaySelect.value);
    barangayName.value = brgy ? brgy.name : '';
  };

  provinceSelect.addEventListener('change', syncCity);
  citySelect.addEventListener('change', syncBarangay);
  barangaySelect.addEventListener('change', () => {
    const province = provinces.find((p) => p.code === provinceSelect.value);
    const city = province ? (province.cities || []).find((c) => c.code === citySelect.value) : null;
    const brgy = city ? (city.barangays || []).find((b) => b.code === barangaySelect.value) : null;
    barangayName.value = brgy ? brgy.name : '';
  });

  if (prefProvince) syncCity();
});

document.querySelectorAll('[data-enroll-review]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    const submitter = event.submitter;
    if (!submitter || submitter.getAttribute('name') !== 'review_action') return;
    if (submitter.value === 'approve') {
      const msg = submitter.getAttribute('data-confirm-approve') || 'Approve this enrollee?';
      if (!window.confirm(msg)) event.preventDefault();
    }
  });
});
