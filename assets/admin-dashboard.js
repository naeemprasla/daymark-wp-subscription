(function () {
  'use strict';
  const setLoading = button => { if (!button) return; if (!button.dataset.daymarkOriginalLabel) button.dataset.daymarkOriginalLabel = 'value' in button ? button.value : button.textContent; button.classList.add('is-loading'); button.disabled = true; button.setAttribute('aria-busy', 'true'); };
  document.addEventListener('submit', event => { const form = event.target; if (!(form instanceof HTMLFormElement)) return; if (!form.closest('.daymark-admin, .daymark-modal, .daymark-drawer') && !form.querySelector('input[name="action"][value^="daymark_"]')) return; const button = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]'); if (button) setLoading(button); form.setAttribute('aria-busy', 'true'); });
  const root = document.querySelector('.daymark-admin');
  const configStrings = (window.DaymarkAdminConfig && window.DaymarkAdminConfig.strings) || {};
  const t = (key, fallback) => configStrings[key] || fallback;
  if (!root) return;
  document.querySelectorAll('.daymark-design-token-card input[type="submit"], .daymark-layout-token-card input[type="submit"]').forEach(button => { button.style.width = '148px'; button.style.maxWidth = '148px'; });
  const addPricingFields = form => {
    if (!form || form.dataset.daymarkPricingFields) return;
    form.dataset.daymarkPricingFields = '1';
    const anchor = form.querySelector('input[name="intro_cycles"]')?.closest('label') || form.querySelector('input[name="price"]')?.closest('label');
    if (!anchor) return;
    const field = (html, className = '') => { const label = document.createElement('label'); label.className = className; label.innerHTML = html; return label; };
    const refund = field('Refund policy<small>Explain the plan-specific refund terms shown before checkout</small><textarea name="refund_policy" rows="3"></textarea>', 'daymark-form-wide');
    const fee = field('One-time setup fee<small>Charged only on initial checkout</small><input name="setup_fee" type="number" min="0" step="0.01" value="0">');
    const commitment = field('Minimum commitment cycles<small>Members cannot cancel before this commitment ends</small><input name="minimum_commitment_cycles" type="number" min="0" max="60" step="1" value="0">');
    const tax = field('Tax behavior<small>Used when Stripe creates the price</small><select name="tax_behavior"><option value="automatic">Automatic</option><option value="inclusive">Tax inclusive</option><option value="exclusive">Tax exclusive</option></select>');
    const promo = field('<input name="allow_promotion_codes" type="checkbox" value="1"> <span>Allow promotion codes<small>Let Stripe Checkout accept eligible codes</small></span>', 'daymark-check-field');
    const quantity = field('<input name="quantity_enabled" type="checkbox" value="1"> <span>Enable quantity / seats<small>Charge per selected unit</small></span>', 'daymark-check-field');
    const pause = field('<input name="pause_enabled" type="checkbox" value="1" checked> <span>Allow pause<small>Members can pause this plan when provider support is available</small></span>', 'daymark-check-field');
    const cancellation = field('Cancellation policy<small>Choose the plan rule or inherit the site default</small><select name="cancellation_mode"><option value="inherit">Use site default</option><option value="period_end">At period end</option><option value="immediate">Immediately</option></select>');
    const minimum = field('Minimum quantity<input name="quantity_min" type="number" min="1" max="1000" step="1" value="1">');
    const maximum = field('Maximum quantity<input name="quantity_max" type="number" min="1" max="1000" step="1" value="1">');
    [refund, fee, commitment, tax, promo, quantity, pause, cancellation, minimum, maximum].forEach(item => anchor.after(item));
    if (form.classList.contains('daymark-plan-edit-form')) form.addEventListener('submit', async event => {
      event.preventDefault(); event.stopImmediatePropagation();
      const button = form.querySelector('button[type="submit"]'); if (button) setLoading(button);
      const payload = {}; new FormData(form).forEach((value, key) => { payload[key] = value; });
      ['price', 'intro_price', 'intro_cycles', 'trial_days', 'sort_order', 'setup_fee', 'minimum_commitment_cycles', 'quantity_min', 'quantity_max'].forEach(key => { if (form.elements[key]) payload[key] = Number(form.elements[key].value) || 0; });
      ['featured', 'active', 'allow_promotion_codes', 'quantity_enabled', 'pause_enabled'].forEach(key => { if (form.elements[key]) payload[key] = form.elements[key].checked; });
      payload.benefits = form.elements.benefits?.value || '';
      payload.refund_policy = form.elements.refund_policy?.value || '';
      try {
        const response = await fetch(`${window.DaymarkAdminConfig.apiBase}/plans/${Number(form.elements.id.value)}`, { method: 'PATCH', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-WP-Nonce': window.DaymarkAdminConfig.nonce }, body: JSON.stringify(payload) });
        const body = await response.json(); if (!response.ok) throw new Error(body?.message || 'Plan request failed'); window.location.reload();
      } catch (error) { const status = form.querySelector('.daymark-plan-edit-status'); if (status) status.textContent = error.message; if (button) { button.disabled = false; button.classList.remove('is-loading'); } }
    }, true);
  };
  addPricingFields(root.querySelector('.daymark-create-plan form'));
  new MutationObserver(() => document.querySelectorAll('.daymark-plan-edit-form').forEach(addPricingFields)).observe(document.body, { childList: true, subtree: true });
  const policyForm = document.querySelector('form [name="default_approval_mode"]')?.form;
  if (policyForm && !policyForm.elements.require_second_admin) { const label = document.createElement('label'); label.className = 'daymark-check-field'; label.innerHTML = '<input name="require_second_admin" type="checkbox" value="1"> <span>Require a second admin for sensitive actions<small>Refunds, revokes, and approval decisions wait for another administrator.</small></span>'; policyForm.appendChild(label); if (window.DaymarkPolicyConfig?.requireSecondAdmin) label.querySelector('input').checked = true; }
  const labels = {
    daymark_create_plan: 'Create a plan',
    daymark_save_provider: 'Payment provider',
    daymark_save_tax_settings: 'Tax settings'
  };
  const sanitizeEditorHtml = html => {
    const documentFragment = new DOMParser().parseFromString(String(html || ''), 'text/html');
    documentFragment.querySelectorAll('script, iframe, object, embed, link, meta, style').forEach(node => node.remove());
    documentFragment.querySelectorAll('*').forEach(node => [...node.attributes].forEach(attribute => {
      const name = attribute.name.toLowerCase();
      const value = attribute.value.trim().toLowerCase();
      if (name.indexOf('on') === 0 || 'style' === name || (('href' === name || 'src' === name) && /^(javascript:|vbscript:|data:)/i.test(value))) node.removeAttribute(attribute.name);
    }));
    return documentFragment.body.innerHTML;
  };
  const initializeEditors = () => document.querySelectorAll('textarea:not([data-daymark-wysiwyg])').forEach((textarea, index) => {
    if (!textarea.id) textarea.id = `daymark-editor-${index + 1}`;
    textarea.dataset.daymarkWysiwyg = '1';
    const wrapper = document.createElement('div'); wrapper.className = 'daymark-wysiwyg';
    const toolbar = document.createElement('div'); toolbar.className = 'daymark-wysiwyg__toolbar';
    [['bold', 'B'], ['italic', 'I'], ['insertUnorderedList', '• List'], ['insertOrderedList', '1. List']].forEach(([command, label]) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = label; button.setAttribute('aria-label', label); button.addEventListener('mousedown', event => event.preventDefault()); button.addEventListener('click', () => { document.execCommand(command, false); editor.focus(); sync(); }); toolbar.appendChild(button); });
    const editor = document.createElement('div'); editor.className = 'daymark-wysiwyg__editor'; editor.contentEditable = 'true'; editor.innerHTML = sanitizeEditorHtml(textarea.value); editor.setAttribute('role', 'textbox'); editor.setAttribute('aria-multiline', 'true'); editor.setAttribute('aria-label', textarea.getAttribute('aria-label') || textarea.previousElementSibling?.textContent || 'Rich text editor');
    const sync = () => { editor.innerHTML = sanitizeEditorHtml(editor.innerHTML); textarea.value = editor.innerHTML; };
    editor.addEventListener('input', sync); textarea.form?.addEventListener('submit', sync); textarea.hidden = true; textarea.parentNode.insertBefore(wrapper, textarea); wrapper.append(toolbar, editor);
  });
  const initializeCodeEditors = () => { if (!window.wp?.codeEditor) return; document.querySelectorAll('textarea.daymark-code-editor').forEach(textarea => { if (!textarea.id || textarea.dataset.daymarkCodeEditor) return; textarea.dataset.daymarkCodeEditor = '1'; window.wp.codeEditor.initialize(textarea.id, window.wp.codeEditor.defaultSettings || {}); }); };
  initializeCodeEditors();
  initializeEditors();
  const initializePlanDrawer = () => {
    const panel = root.querySelector('.daymark-create-plan');
    const form = panel?.querySelector('form');
    if (!panel || !form) return;
    form.classList.add('daymark-plan-form');
    if (form.elements.approval_mode && window.DaymarkAdminConfig?.defaultApproval) form.elements.approval_mode.value = window.DaymarkAdminConfig.defaultApproval;
    form.elements.slug?.closest('label')?.remove();
    form.querySelector('input[name="currency"]')?.closest('label')?.remove();
    if (!form.elements.trial_days) { const trialLabel = document.createElement('label'); trialLabel.innerHTML = 'Trial days<small>Stripe-only free access before the first recurring charge</small><input name="trial_days" type="number" min="0" max="365" step="1" value="0">'; form.querySelector('select[name="approval_mode"]')?.closest('label')?.after(trialLabel); }
    if (!form.elements.intro_price) { const introLabel = document.createElement('label'); introLabel.innerHTML = 'Introductory price<small>Stripe-only price for the first billing cycles</small><input name="intro_price" type="number" min="0" step="0.01" value="0">'; form.querySelector('input[name="trial_days"]')?.closest('label')?.after(introLabel); const cyclesLabel = document.createElement('label'); cyclesLabel.innerHTML = 'Introductory cycles<small>Monthly or yearly billing only; 0 disables the offer</small><input name="intro_cycles" type="number" min="0" max="12" step="1" value="0">'; introLabel.after(cyclesLabel); }
    if (!form.elements.setup_fee) { const fee = document.createElement('label'); fee.innerHTML = 'One-time setup fee<small>Charged only on the initial checkout</small><input name="setup_fee" type="number" min="0" step="0.01" value="0">'; form.querySelector('input[name="intro_cycles"]')?.closest('label')?.after(fee); const commitment = document.createElement('label'); commitment.innerHTML = 'Minimum commitment cycles<small>Members cannot cancel before this many billing cycles</small><input name="minimum_commitment_cycles" type="number" min="0" max="60" step="1" value="0">'; fee.after(commitment); const tax = document.createElement('label'); tax.innerHTML = 'Tax behavior<small>Applied when Stripe creates the recurring price</small><select name="tax_behavior"><option value="automatic">Automatic</option><option value="inclusive">Tax inclusive</option><option value="exclusive">Tax exclusive</option></select>'; commitment.after(tax); const promo = document.createElement('label'); promo.className = 'daymark-check-field'; promo.innerHTML = '<input name="allow_promotion_codes" type="checkbox" value="1"> <span>Allow promotion codes<small>Let Stripe Checkout accept eligible customer promotion codes</small></span>'; tax.after(promo); const quantity = document.createElement('label'); quantity.className = 'daymark-check-field'; quantity.innerHTML = '<input name="quantity_enabled" type="checkbox" value="1"> <span>Enable quantity / seats<small>Charge the recurring price per selected unit</small></span>'; promo.after(quantity); const min = document.createElement('label'); min.innerHTML = 'Minimum quantity<input name="quantity_min" type="number" min="1" max="1000" step="1" value="1">'; quantity.after(min); const max = document.createElement('label'); max.innerHTML = 'Maximum quantity<input name="quantity_max" type="number" min="1" max="1000" step="1" value="1">'; min.after(max); }
    if (!form.elements.catalog_visibility) { const visibility = document.createElement('label'); visibility.innerHTML = 'Catalog visibility<small>Hidden plans remain purchasable by direct checkout link</small><select name="catalog_visibility"><option value="visible">Visible in public catalog</option><option value="hidden">Hidden from public catalog</option></select>'; form.querySelector('input[name="trial_days"]')?.closest('label')?.after(visibility); }
    if (!form.elements.cta_label) { const cta = document.createElement('label'); cta.innerHTML = 'CTA label<small>Optional plan-specific action text</small><input name="cta_label" maxlength="80" placeholder="Choose plan">'; form.querySelector('select[name="catalog_visibility"]')?.closest('label')?.after(cta); }
    const heading = panel.querySelector('.daymark-panel-heading');
    const step = panel.querySelector('.daymark-form-step');
    const trigger = document.createElement('button');
    trigger.type = 'button'; trigger.className = 'button button-primary daymark-drawer-trigger'; trigger.textContent = t('createPlan', 'Create a plan'); trigger.setAttribute('aria-haspopup', 'dialog');
    const drawer = document.createElement('aside');
    drawer.className = 'daymark-drawer'; drawer.hidden = true; drawer.setAttribute('role', 'dialog'); drawer.setAttribute('aria-modal', 'true'); drawer.setAttribute('aria-label', 'Create a plan');
    drawer.innerHTML = '<div class="daymark-drawer__backdrop" data-daymark-drawer-close></div><div class="daymark-drawer__panel" tabindex="-1"><header class="daymark-drawer__header"><div><span class="daymark-kicker">DAYMARK / CATALOG</span><h2>Create a plan</h2><p>Define the offer customers will see.</p></div><button type="button" class="daymark-drawer__close" aria-label="Close create plan drawer" data-daymark-drawer-close>×</button></header><div class="daymark-drawer__body"></div></div>';
    if (step) step.replaceWith(trigger); else heading?.appendChild(trigger);
    drawer.querySelector('.daymark-drawer__body').appendChild(form);
    document.body.appendChild(drawer);
    let lastFocus = null;
    const close = () => { drawer.hidden = true; drawer.classList.remove('is-open'); document.body.classList.remove('daymark-drawer-open'); if (lastFocus) lastFocus.focus(); };
    const open = () => { lastFocus = document.activeElement; drawer.hidden = false; requestAnimationFrame(() => drawer.classList.add('is-open')); document.body.classList.add('daymark-drawer-open'); const first = form.querySelector('input:not([type="hidden"]), select, textarea, button'); (first || drawer.querySelector('.daymark-drawer__panel')).focus(); };
    trigger.addEventListener('click', open);
    drawer.querySelectorAll('[data-daymark-drawer-close]').forEach(button => button.addEventListener('click', close));
    drawer.addEventListener('keydown', event => {
      if ('Escape' === event.key) { close(); return; }
      if ('Tab' !== event.key) return;
      const focusable = [...drawer.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"]')];
      if (!focusable.length) return;
      const first = focusable[0]; const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  };
  initializePlanDrawer();
  const initializePlanManagement = () => {
    const catalog = root.querySelector('.daymark-plan-catalog');
    const plans = (window.DaymarkAdminConfig && window.DaymarkAdminConfig.plans) || [];
    if (!catalog || !plans.length) return;
    const config = window.DaymarkAdminConfig || {};
    const request = async (path, options) => {
      const response = await fetch(`${config.apiBase}${path}`, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } });
      const body = await response.json();
      if (!response.ok) { const error = new Error(body && body.message ? body.message : t('planRequestFailed', 'Plan request failed')); error.body = body; throw error; }
      return body;
    };
    const drawer = document.createElement('aside');
    drawer.className = 'daymark-plan-edit-drawer'; drawer.hidden = true; drawer.setAttribute('role', 'dialog'); drawer.setAttribute('aria-modal', 'true'); drawer.setAttribute('aria-label', t('edit', 'Edit'));
    drawer.innerHTML = '<div class="daymark-drawer__backdrop" data-daymark-plan-edit-close></div><div class="daymark-drawer__panel" tabindex="-1"><header class="daymark-drawer__header"><div><span class="daymark-kicker">DAYMARK / CATALOG</span><h2>' + t('edit', 'Edit') + ' plan</h2><p>Update the offer without changing its billing history.</p></div><button type="button" class="daymark-drawer__close" data-daymark-plan-edit-close aria-label="' + t('close', 'Close') + '">×</button></header><div class="daymark-drawer__body"><form class="daymark-plan-edit-form"><input type="hidden" name="id"><label>Plan name<input name="name" required maxlength="120"></label><p class="description">The URL slug is locked after creation so existing checkout links keep working.</p><label>Price<input name="price" type="number" min="0" step="0.01" required></label><label>Currency<input name="currency" pattern="[A-Za-z]{3}" maxlength="3" required></label><label>Billing interval<select name="interval_unit"><option value="day">Daily</option><option value="month">Monthly</option><option value="year">Yearly</option></select></label><label>Trial days<input name="trial_days" type="number" min="0" max="365" step="1"></label><label>Introductory price<input name="intro_price" type="number" min="0" step="0.01"></label><label>Introductory cycles<input name="intro_cycles" type="number" min="0" max="12" step="1"></label><label>Approval<select name="approval_mode"><option value="direct">Direct approval</option><option value="review">Admin review</option></select></label><label>Sort order<input name="sort_order" type="number" min="0" step="1"></label><label>Catalog visibility<select name="catalog_visibility"><option value="visible">Visible in public catalog</option><option value="hidden">Hidden from public catalog</option></select></label><label>CTA label<input name="cta_label" maxlength="80"></label><label class="daymark-plan-edit-active"><input name="featured" type="checkbox"> <span>Featured plan</span></label><label class="daymark-form-wide">Description<textarea name="description" rows="6"></textarea></label><label class="daymark-form-wide">Benefits<textarea name="benefits" rows="5" placeholder="One benefit per line"></textarea></label><label class="daymark-plan-edit-active"><input name="active" type="checkbox"> <span>Available for new checkout</span></label><p class="daymark-plan-edit-status" role="status" aria-live="polite"></p><button class="button button-primary" type="submit">' + t('savePlan', 'Save plan changes') + '</button></form></div></div>';
    document.body.appendChild(drawer);
    const form = drawer.querySelector('form'); const status = drawer.querySelector('.daymark-plan-edit-status'); let selectedPlan = null;
    form.classList.add('daymark-plan-form');
    if (form.elements.currency) { const currency = form.elements.currency; const currencyLabel = currency.closest('label'); if (currencyLabel) currencyLabel.remove(); currency.type = 'hidden'; form.insertBefore(currency, form.firstChild); }
    const close = () => { drawer.hidden = true; document.body.classList.remove('daymark-drawer-open'); selectedPlan = null; };
    drawer.querySelectorAll('[data-daymark-plan-edit-close]').forEach(button => button.addEventListener('click', close));
    const fill = plan => { selectedPlan = plan; Object.keys(plan).forEach(key => { const field = form.elements[key]; if (!field) return; if ('checkbox' === field.type) field.checked = Boolean(Number(plan[key])); else field.value = Array.isArray(plan[key]) ? plan[key].join('\n') : (plan[key] ?? ''); }); status.textContent = ''; drawer.hidden = false; document.body.classList.add('daymark-drawer-open'); form.elements.name.focus(); };
    form.addEventListener('submit', async event => { event.preventDefault(); const button = form.querySelector('button[type="submit"]'); setLoading(button); status.textContent = 'Saving…'; try { await request(`/plans/${Number(form.elements.id.value)}`, { method: 'PATCH', body: JSON.stringify({ name: form.elements.name.value.trim(), price: Number(form.elements.price.value), intro_price: Number(form.elements.intro_price.value) || 0, intro_cycles: Number(form.elements.intro_cycles.value) || 0, currency: form.elements.currency.value.trim().toUpperCase(), interval_unit: form.elements.interval_unit.value, trial_days: Number(form.elements.trial_days.value) || 0, approval_mode: form.elements.approval_mode.value, sort_order: Number(form.elements.sort_order.value) || 0, catalog_visibility: form.elements.catalog_visibility.value, cta_label: form.elements.cta_label.value.trim(), featured: form.elements.featured.checked, benefits: form.elements.benefits.value, description: form.elements.description.value, active: form.elements.active.checked }) }); window.location.reload(); } catch (error) { status.textContent = error.message; button.disabled = false; button.classList.remove('is-loading'); } });
    const action = async (plan, archive) => { if (!window.confirm(archive ? `Archive ${plan.name}? It will stop appearing in new checkout.` : `Delete ${plan.name}? This cannot be undone.`)) return; try { if (archive) await request(`/plans/${Number(plan.id)}`, { method: 'PATCH', body: JSON.stringify({ active: false }) }); else await request(`/plans/${Number(plan.id)}`, { method: 'DELETE' }); window.location.reload(); } catch (error) { window.alert(error.message); } };
    catalog.querySelectorAll('.daymark-plan-row').forEach(row => { const slug = row.querySelector('code')?.textContent.trim(); const plan = plans.find(item => item.slug === slug); if (!plan) return; const controls = document.createElement('div'); controls.className = 'daymark-plan-controls'; const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'button daymark-plan-edit-button'; edit.textContent = 'Edit'; edit.addEventListener('click', () => fill(plan)); const lifecycle = document.createElement('button'); lifecycle.type = 'button'; lifecycle.className = 'button'; lifecycle.textContent = Number(plan.active) ? 'Archive' : 'Delete'; lifecycle.addEventListener('click', () => action(plan, Number(plan.active))); controls.append(edit, lifecycle); row.querySelector('.daymark-plan-status')?.appendChild(controls); });
  };
  initializePlanManagement();
  const initializePlanSubscribers = () => {
    const catalog = root.querySelector('.daymark-plan-catalog');
    const config = window.DaymarkAdminConfig || {};
    const plans = Array.isArray(config.plans) ? config.plans : [];
    if (!catalog || !plans.length) return;
    const request = async (path, options) => {
      const response = await fetch(`${config.apiBase}${path}`, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } });
      const body = await response.json();
      if (!response.ok) throw new Error(body && body.message ? body.message : t('subscriberRequestFailed', 'Subscriber request failed'));
      return body;
    };
    const drawer = document.createElement('aside');
    drawer.className = 'daymark-drawer daymark-plan-subscriber-drawer'; drawer.hidden = true; drawer.setAttribute('role', 'dialog'); drawer.setAttribute('aria-modal', 'true'); drawer.setAttribute('aria-label', 'Plan subscribers');
    drawer.innerHTML = '<div class="daymark-drawer__backdrop" data-daymark-subscriber-close></div><div class="daymark-drawer__panel" tabindex="-1"><header class="daymark-drawer__header"><div><span class="daymark-kicker">DAYMARK / MEMBERS</span><h2 class="daymark-subscriber-drawer-title">Plan subscribers</h2><p>Review members and manage their current subscription.</p></div><button type="button" class="daymark-drawer__close" data-daymark-subscriber-close aria-label="Close subscribers drawer">×</button></header><div class="daymark-drawer__body"><p class="daymark-subscriber-status" role="status" aria-live="polite">Loading subscribers…</p><div class="daymark-subscriber-list"></div></div></div>';
    document.body.appendChild(drawer);
    const body = drawer.querySelector('.daymark-drawer__body'); const list = drawer.querySelector('.daymark-subscriber-list'); const status = drawer.querySelector('.daymark-subscriber-status'); const title = drawer.querySelector('.daymark-subscriber-drawer-title');
    const close = () => { drawer.hidden = true; drawer.classList.remove('is-open'); document.body.classList.remove('daymark-drawer-open'); };
    drawer.querySelectorAll('[data-daymark-subscriber-close]').forEach(button => button.addEventListener('click', close));
    const render = (rows, plan) => {
      list.textContent = '';
      if (!rows.length) { status.textContent = t('noSubscribers', 'No current subscribers on this plan.'); return; }
      status.textContent = `${rows.length} current subscriber${1 === rows.length ? '' : 's'}`;
      rows.forEach(row => {
        const item = document.createElement('article'); item.className = 'daymark-subscriber-item';
        const heading = document.createElement('div'); heading.className = 'daymark-subscriber-item__heading';
        const name = document.createElement('strong'); name.textContent = row.display_name || t('unknownMember', 'Unknown member');
        const email = document.createElement('span'); email.textContent = row.user_email || '';
        heading.append(name, email);
        const meta = document.createElement('p'); meta.textContent = `${String(row.status).replace(/_/g, ' ')} · ${row.pending_plan_name ? `Scheduled: ${row.pending_plan_name}` : t('noPendingPlanChange', 'No pending plan change')}`;
        const actions = document.createElement('div'); actions.className = 'daymark-subscriber-item__actions';
        const select = document.createElement('select'); select.setAttribute('aria-label', `Change plan for ${row.display_name || row.user_email}`);
        plans.filter(itemPlan => Number(itemPlan.active)).forEach(itemPlan => { const option = document.createElement('option'); option.value = itemPlan.id; option.textContent = itemPlan.name; option.selected = Number(itemPlan.id) === Number(row.pending_plan_id || row.plan_id); select.appendChild(option); });
        const save = document.createElement('button'); save.type = 'button'; save.className = 'button button-primary'; save.textContent = t('changePlan', 'Change plan');
        save.addEventListener('click', async () => { if (Number(select.value) === Number(row.plan_id) && !row.pending_plan_id) return; const reason = window.prompt('Reason for this plan change (optional):', ''); if (null === reason) return; setLoading(save); try { await request(`/subscriptions/${Number(row.id)}/plan`, { method: 'POST', body: JSON.stringify({ plan_id: Number(select.value), reason }) }); window.location.reload(); } catch (error) { save.disabled = false; save.classList.remove('is-loading'); status.textContent = error.message; } });
        const revoke = document.createElement('button'); revoke.type = 'button'; revoke.className = 'button daymark-button-danger'; revoke.textContent = t('revoke', 'Revoke');
        revoke.addEventListener('click', async () => { if (!window.confirm(`Revoke ${row.display_name || row.user_email}'s subscription immediately?`)) return; const reason = window.prompt('Reason for revoking this subscription:', ''); if (null === reason || !reason.trim()) return; setLoading(revoke); try { await request(`/subscriptions/${Number(row.id)}/revoke`, { method: 'POST', body: JSON.stringify({ reason }) }); window.location.reload(); } catch (error) { revoke.disabled = false; revoke.classList.remove('is-loading'); status.textContent = error.message; } });
        actions.append(select, save, revoke); item.append(heading, meta, actions); list.appendChild(item);
      });
    };
    const open = async plan => { title.textContent = `${plan.name} subscribers`; drawer.hidden = false; requestAnimationFrame(() => drawer.classList.add('is-open')); document.body.classList.add('daymark-drawer-open'); list.textContent = ''; status.textContent = t('loadingSubscribers', 'Loading subscribers…'); try { render(await request(`/plans/${Number(plan.id)}/subscribers`), plan); } catch (error) { status.textContent = error.message; } };
    catalog.querySelectorAll('.daymark-plan-row').forEach(row => { const slug = row.querySelector('code')?.textContent.trim(); const plan = plans.find(item => String(item.slug).trim() === slug); if (!plan) return; const count = document.createElement('button'); count.type = 'button'; count.className = 'button daymark-plan-subscriber-count'; count.textContent = `${Number(plan.subscriber_count || 0)} subscriber${1 === Number(plan.subscriber_count || 0) ? '' : 's'}`; count.setAttribute('aria-haspopup', 'dialog'); count.addEventListener('click', () => open(plan)); row.querySelector('.daymark-plan-meta')?.appendChild(count); });
  };
  initializePlanSubscribers();
  const initializeBulkSubscriptions = () => {
    const table = root.querySelector('table.daymark-subscription-list-table tr[data-daymark-subscription-id]')?.closest('table');
    const config = window.DaymarkAdminConfig || {};
    const plans = Array.isArray(config.plans) ? config.plans.filter(plan => Number(plan.active)) : [];
    if (!table || !config.apiBase) return;
    const header = table.querySelector('thead tr');
    if (!header || table.querySelector('[data-daymark-bulk-select-all]')) return;
    const selectAllCell = document.createElement('th');
    selectAllCell.innerHTML = '<input type="checkbox" data-daymark-bulk-select-all aria-label="' + t('selectAllSubscriptions', 'Select all subscriptions') + '">';
    header.prepend(selectAllCell);
    table.querySelectorAll('tbody tr[data-daymark-subscription-id]').forEach(row => {
      const cell = document.createElement('td');
      cell.innerHTML = `<input type="checkbox" data-daymark-bulk-select value="${row.dataset.daymarkSubscriptionId}" aria-label="${t('selectSubscription', 'Select subscription')}">`;
      row.prepend(cell);
    });
    const toolbar = document.createElement('div');
    toolbar.className = 'daymark-bulk-toolbar';
    toolbar.innerHTML = '<strong>' + t('bulkActions', 'Bulk actions') + '</strong><select data-daymark-bulk-action aria-label="' + t('chooseAction', 'Bulk action') + '"><option value="">' + t('chooseAction', 'Choose action') + '</option><option value="change_plan">' + t('changePlan', 'Change plan') + '</option><option value="revoke">' + t('revoke', 'Revoke') + '</option></select><select data-daymark-bulk-plan aria-label="' + t('choosePlan', 'Target plan') + '" hidden></select><button type="button" class="button button-primary" data-daymark-bulk-submit>' + t('apply', 'Apply') + '</button><span class="daymark-bulk-status" role="status" aria-live="polite"></span>';
    const action = toolbar.querySelector('[data-daymark-bulk-action]');
    const plan = toolbar.querySelector('[data-daymark-bulk-plan]');
    plans.forEach(item => { const option = document.createElement('option'); option.value = item.id; option.textContent = item.name; plan.appendChild(option); });
    action.addEventListener('change', () => { plan.hidden = 'change_plan' !== action.value; });
    const status = toolbar.querySelector('.daymark-bulk-status');
    const selectAll = header.querySelector('[data-daymark-bulk-select-all]');
    selectAll.addEventListener('change', () => table.querySelectorAll('[data-daymark-bulk-select]').forEach(input => { input.checked = selectAll.checked; }));
    toolbar.querySelector('[data-daymark-bulk-submit]').addEventListener('click', async event => {
      const selected = [...table.querySelectorAll('[data-daymark-bulk-select]:checked')].map(input => Number(input.value)).filter(Boolean);
      if (!selected.length) { status.textContent = t('selectOne', 'Select at least one subscription.'); return; }
      if (!action.value) { status.textContent = t('chooseBulk', 'Choose a bulk action.'); return; }
      if ('revoke' === action.value && !window.confirm(`Revoke ${selected.length} subscription${1 === selected.length ? '' : 's'} immediately?`)) return;
      const reason = 'revoke' === action.value ? window.prompt('Reason for revoking these subscriptions:', '') : '';
      if ('revoke' === action.value && (null === reason || !reason.trim())) return;
      if ('change_plan' === action.value && !Number(plan.value)) { status.textContent = t('choosePlan', 'Choose a target plan.'); return; }
      const button = event.currentTarget; setLoading(button); status.textContent = t('applying', 'Applying…');
      try {
        const response = await fetch(`${config.apiBase}/subscriptions/bulk`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) }, body: JSON.stringify({ subscription_ids: selected, action: action.value, ...(Number(plan.value) ? { plan_id: Number(plan.value) } : {}), ...(reason ? { reason } : {}) }) });
        const body = await response.json();
        if (!response.ok) throw new Error(body && body.message ? body.message : t('bulkFailed', 'Bulk action failed'));
        const failed = body && body.failed && 'object' === typeof body.failed ? Object.keys(body.failed).length : 0;
        const updated = Number(body && body.updated) || selected.length - failed;
        status.textContent = failed ? `${updated} updated; ${failed} failed.` : `${updated} updated.`;
        if (!failed) window.location.reload();
      } catch (error) { status.textContent = error.message; button.disabled = false; button.classList.remove('is-loading'); button.removeAttribute('aria-busy'); }
    });
    table.parentNode.insertBefore(toolbar, table);
  };
  initializeBulkSubscriptions();
  document.querySelectorAll('[data-daymark-billing-action]').forEach(button => {
    button.addEventListener('click', async () => {
      const cell = button.closest('.daymark-billing-actions'); const row = button.closest('[data-daymark-subscription-id]'); const action = cell.querySelector('select').value; const amount = cell.querySelector('input').value; const status = cell.querySelector('.daymark-billing-action-status');
      if (['refund', 'credit'].includes(action) && !(Number(amount) > 0)) { status.textContent = t('adjustmentAmount', 'Enter an adjustment amount.'); return; }
      if (!window.confirm(`Apply ${action.replace('_', ' ')} to this subscription?`)) return;
      setLoading(button); status.textContent = 'Saving…';
      try { const key = window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()); const config = window.DaymarkAdminConfig || {}; const reason = ['refund', 'credit', 'void', 'uncollectible'].includes(action) ? window.prompt(t('billingReason', 'Enter a reason for this billing action.')) : ''; if (['refund', 'credit', 'void', 'uncollectible'].includes(action) && !reason) throw new Error(t('billingReasonRequired', 'A reason is required.')); const response = await fetch(`${config.apiBase}/subscriptions/${Number(row.dataset.daymarkSubscriptionId)}/billing-action`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) }, body: JSON.stringify({ action, ...(amount ? { amount: Number(amount) } : {}), ...(reason ? { reason } : {}), idempotency_key: key }) }); const body = await response.json(); if (!response.ok) throw new Error(body && body.message ? body.message : t('billingFailed', 'Billing action failed')); status.textContent = t('billingCompleted', 'Billing action completed.'); } catch (error) { status.textContent = error.message; } finally { button.disabled = false; button.classList.remove('is-loading'); }
    });
  });
  let sequence = 0;
  root.querySelectorAll('form').forEach(form => {
    if (form.closest('.daymark-modal') || form.closest('.daymark-drawer') || form.classList.contains('daymark-no-modal') || form.classList.contains('daymark-settings-form') || form.closest('.daymark-form-panel')) return;
    const actionInput = form.querySelector('input[name="action"]');
    const pageInput = form.querySelector('input[name="page"]');
    const action = actionInput ? actionInput.value : '';
    const page = pageInput ? pageInput.value : '';
    const title = labels[action] || (page === 'daymark-subscriptions-list' ? t('filterSubscriptions', 'Filter subscriptions') : t('editSettings', 'Edit settings'));
    const id = `daymark-modal-${++sequence}`;
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'button daymark-modal-trigger';
    trigger.textContent = title;
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', id);
    const originalParent = form.parentNode;
    originalParent.insertBefore(trigger, form);
    const modal = document.createElement('div');
    modal.className = 'daymark-modal';
    modal.id = id;
    modal.hidden = true;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', `${id}-title`);
    modal.innerHTML = `<div class="daymark-modal__backdrop" data-daymark-modal-close></div><div class="daymark-modal__dialog" tabindex="-1"><div class="daymark-modal__header"><div><span class="daymark-kicker">DAYMARK / FORM</span><h2 id="${id}-title"></h2></div><button type="button" class="daymark-modal__close" aria-label="${t('closeDialog', 'Close dialog')}" data-daymark-modal-close>×</button></div><div class="daymark-modal__body"></div></div>`;
    modal.querySelector('h2').textContent = title;
    modal.querySelector('.daymark-modal__body').appendChild(form);
    document.body.appendChild(modal);
    const dialog = modal.querySelector('.daymark-modal__dialog');
    let lastFocus = null;
    const close = () => { modal.hidden = true; document.body.classList.remove('daymark-modal-open'); if (lastFocus) lastFocus.focus(); };
    const open = () => { lastFocus = document.activeElement; modal.hidden = false; document.body.classList.add('daymark-modal-open'); const first = form.querySelector('input:not([type="hidden"]), select, textarea, button'); (first || dialog).focus(); };
    trigger.addEventListener('click', open);
    modal.querySelectorAll('[data-daymark-modal-close]').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('keydown', event => {
      if ('Escape' === event.key) { close(); return; }
      if ('Tab' !== event.key) return;
      const focusable = [...modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')];
      if (!focusable.length) return;
      const first = focusable[0]; const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  });
  const healthButton = root.querySelector('[data-daymark-provider-health]');
  const healthStatus = root.querySelector('[data-daymark-provider-health-status]');
  const webhookHealth = root.querySelector('[data-daymark-webhook-health]');
  if (healthButton && healthStatus && config.apiBase) {
    healthButton.addEventListener('click', async () => {
      setLoading(healthButton); healthStatus.textContent = t('testingConnection', 'Testing connection…');
      try {
        const response = await fetch(config.apiBase + '/admin/provider-health', { headers: { Accept: 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } });
        const body = await response.json();
        if (!response.ok) throw new Error(body && body.message ? body.message : t('connectionFailed', 'Provider connection failed.'));
        const renewal = body.renewal_capability ? ' Renewal: ' + String(body.renewal_capability).replace(/_/g, ' ') + '.' : '';
        healthStatus.textContent = body.account_id ? 'Connected to ' + body.account_id + ' (' + (body.mode === 'live' ? 'Live' : 'Sandbox') + ').' + renewal : (body.message || 'Provider connection is available.') + renewal;
        healthStatus.className = 'is-success';
        if (webhookHealth && body.webhook) { const format = value => value || '—'; const values = webhookHealth.querySelectorAll('span'); if (values[0]) values[0].textContent = t('lastReceived', 'Last received: ') + format(body.webhook.last_received); if (values[1]) values[1].textContent = t('lastVerified', 'Last verified: ') + format(body.webhook.last_verified); if (values[2]) values[2].textContent = t('lastProcessed', 'Last processed: ') + format(body.webhook.last_processed); if (values[3]) values[3].textContent = t('lastFailure', 'Last failure: ') + format(body.webhook.last_failed); }
      } catch (error) { healthStatus.textContent = error.message; healthStatus.className = 'is-error'; }
      healthButton.disabled = false; healthButton.classList.remove('is-loading'); healthButton.removeAttribute('aria-busy');
    });
  }
  const picker = root.querySelector('[data-daymark-brand-picker]');
  const hex = root.querySelector('[data-daymark-brand-hex]');
  const preview = root.querySelector('[data-daymark-brand-preview]');
  if (picker && hex) {
    const setColor = color => { picker.value = color; hex.value = color; if (preview) preview.style.setProperty('--daymark-preview-accent', color); };
    picker.addEventListener('input', () => setColor(picker.value));
    hex.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(hex.value)) setColor(hex.value); });
    root.querySelectorAll('[data-daymark-brand-preset]').forEach(button => button.addEventListener('click', () => setColor(button.dataset.daymarkBrandPreset)));
  }
  const detailTable = root.querySelector('table.daymark-subscription-list-table');
  if (detailTable && config.apiBase) {
    const detailDrawer = document.createElement('aside'); detailDrawer.className = 'daymark-drawer daymark-member-detail-drawer'; detailDrawer.hidden = true; detailDrawer.setAttribute('role', 'dialog'); detailDrawer.setAttribute('aria-modal', 'true'); detailDrawer.innerHTML = '<div class="daymark-drawer__backdrop" data-daymark-member-detail-close></div><div class="daymark-drawer__panel" tabindex="-1"><header class="daymark-drawer__header"><div><span class="daymark-kicker">DAYMARK / MEMBER</span><h2>' + t('subscriptionDetail', 'Subscription detail') + '</h2><p class="daymark-member-detail-status" role="status" aria-live="polite">' + t('loading', 'Loading…') + '</p></div><button type="button" class="daymark-drawer__close" data-daymark-member-detail-close aria-label="' + t('close', 'Close') + '">×</button></header><div class="daymark-drawer__body"><div class="daymark-member-detail-content"></div></div></div>'; document.body.appendChild(detailDrawer);
    const content = detailDrawer.querySelector('.daymark-member-detail-content'); const status = detailDrawer.querySelector('.daymark-member-detail-status'); const close = () => { detailDrawer.hidden = true; detailDrawer.classList.remove('is-open'); document.body.classList.remove('daymark-drawer-open'); }; detailDrawer.querySelectorAll('[data-daymark-member-detail-close]').forEach(button => button.addEventListener('click', close));
    const open = async id => { detailDrawer.hidden = false; requestAnimationFrame(() => detailDrawer.classList.add('is-open')); document.body.classList.add('daymark-drawer-open'); content.textContent = ''; status.textContent = t('loading', 'Loading…'); try { const response = await fetch(`${config.apiBase}/subscriptions/${Number(id)}/detail`, { headers: { Accept: 'application/json', ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } }); const data = await response.json(); if (!response.ok) throw new Error(data.message || t('memberDetailFailed', 'Member detail failed.')); const sub = data.subscription || {}; const heading = document.createElement('div'); heading.className = 'daymark-member-detail-summary';[['strong', data.member?.name || t('unknownMember', 'Unknown member')], ['span', data.member?.email || ''], ['span', `${data.plan?.name || t('unknownMember', 'Unknown plan')} · ${String(sub.status || '').replace(/_/g, ' ')}`]].forEach(([tag, value]) => { const element = document.createElement(tag); element.textContent = value; heading.appendChild(element); }); content.appendChild(heading); const invoices = document.createElement('section'); const invoiceHeading = document.createElement('h3'); invoiceHeading.textContent = t('invoices', 'Invoices'); invoices.appendChild(invoiceHeading); const invoiceList = document.createElement('ul'); (data.invoices || []).forEach(invoice => { const item = document.createElement('li'); item.textContent = `${invoice.issued_at || '—'} · ${invoice.currency || ''} ${invoice.amount || '0.00'} · ${invoice.status || 'unknown'}`; invoiceList.appendChild(item); }); if (!invoiceList.children.length) { const item = document.createElement('li'); item.textContent = t('noInvoices', 'No invoices recorded.'); invoiceList.appendChild(item); } invoices.appendChild(invoiceList); content.appendChild(invoices); const audit = document.createElement('section'); const auditHeading = document.createElement('h3'); auditHeading.textContent = t('lifecycleTimeline', 'Lifecycle timeline'); audit.appendChild(auditHeading); const auditList = document.createElement('ul'); (data.audit || []).forEach(entry => { const item = document.createElement('li'); item.textContent = `${entry.created_at || '—'} · ${String(entry.action || '').replace(/_/g, ' ')}`; auditList.appendChild(item); }); if (!auditList.children.length) { const item = document.createElement('li'); item.textContent = t('noAdminActions', 'No recorded admin actions.'); auditList.appendChild(item); } audit.appendChild(auditList); content.appendChild(audit); status.textContent = t('memberContext', 'Read-only member and billing context'); } catch (error) { status.textContent = error.message; } };
    detailTable.querySelectorAll('tbody tr[data-daymark-subscription-id]').forEach(row => { const strong = row.querySelector('td strong'); if (!strong) return; const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'daymark-member-detail-trigger'; trigger.textContent = strong.textContent; trigger.addEventListener('click', () => open(row.dataset.daymarkSubscriptionId)); strong.replaceWith(trigger); });
  }
  const settingsTabs = root.querySelectorAll('[data-daymark-settings-tab]');
  const settingsPanels = root.querySelectorAll('[data-daymark-settings-panel]');
  if (settingsTabs.length && settingsPanels.length) {
    const activateSettingsTab = (name, updateHash = true) => {
      const active = [...settingsTabs].some(tab => tab.dataset.daymarkSettingsTab === name) ? name : 'general';
      settingsTabs.forEach(tab => {
        const selected = tab.dataset.daymarkSettingsTab === active;
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.tabIndex = selected ? 0 : -1;
      });
      settingsPanels.forEach(panel => { panel.hidden = panel.dataset.daymarkSettingsPanel !== active; panel.classList.toggle('is-active', panel.dataset.daymarkSettingsPanel === active); });
      if (updateHash && window.history && window.history.replaceState) window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${active}`);
    };
    const initial = window.location.hash.replace('#', '');
    activateSettingsTab(initial || 'general', false);
    settingsTabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activateSettingsTab(tab.dataset.daymarkSettingsTab));
      tab.addEventListener('keydown', event => {
        if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const next = 'Home' === event.key ? 0 : 'End' === event.key ? settingsTabs.length - 1 : (index + ('ArrowRight' === event.key ? 1 : -1) + settingsTabs.length) % settingsTabs.length;
        settingsTabs[next].focus(); activateSettingsTab(settingsTabs[next].dataset.daymarkSettingsTab);
      });
    });
  }
})();
