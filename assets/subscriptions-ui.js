(function () {
  'use strict';
  const config = window.DaymarkSubscriptionsConfig || {};
  const t = (key, fallback) => config.strings && config.strings[key] ? config.strings[key] : fallback;
  const status = document.querySelector('.daymark-subscription-status');
  const cancelScheduled = document.querySelector('.daymark-cancel-plan-change');
  const current = document.querySelector('.daymark-current-subscription');
  const buttons = [...document.querySelectorAll('[data-daymark-plan-id]')];
  const drawer = document.querySelector('.daymark-plan-change-drawer');
  const drawerTitle = drawer && drawer.querySelector('#daymark-plan-change-title');
  const drawerSummary = drawer && drawer.querySelector('.daymark-plan-change-summary');
  const drawerPrice = drawer && drawer.querySelector('.daymark-plan-change-price');
  const drawerEffective = drawer && drawer.querySelector('.daymark-plan-change-effective');
  const drawerPreview = drawer && drawer.querySelector('.daymark-plan-change-preview');
  const drawerNote = drawer && drawer.querySelector('.daymark-plan-change-note');
  const drawerConfirm = drawer && drawer.querySelector('.daymark-plan-change-confirm');
  let selectedChange = null;
  if (!status) return;
  const request = async (path, options = {}) => { const response = await fetch(config.apiBase + path, { ...options, headers: { Accept: 'application/json', ...(options.headers || {}), ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } }); const body = await response.json(); if (!response.ok) throw new Error(body && body.message ? body.message : 'Subscription request failed'); return body; };
  const setLoading = (target, loading) => { if (!target) return; target.disabled = loading; target.classList.toggle('is-loading', loading); target.setAttribute('aria-busy', loading ? 'true' : 'false'); };
  const catalogLabel = button => { const plan = (config.plans || []).find(item => String(item.id) === String(button?.dataset.daymarkPlanId)); return plan?.cta_label || t('choosePlan', 'Choose a plan'); };
	if (false === config.checkoutReady) buttons.forEach(button => setButton(button, t('checkoutUnavailable', 'Checkout unavailable'), true));
  (config.plans || []).forEach(plan => { if (!Number(plan.trial_days)) return; const card = document.querySelector(`[data-daymark-plan-id="${Number(plan.id)}"]`)?.closest('.daymark-plan'); const price = card?.querySelector('.daymark-plan-price'); if (!price || price.querySelector('.daymark-plan-trial')) return; const trial = document.createElement('small'); trial.className = 'daymark-plan-trial'; trial.textContent = `${Number(plan.trial_days)}-day free trial`; price.appendChild(trial); });
  const formatDate = value => { if (!value) return ''; const date = new Date(`${String(value).replace(' ', 'T')}Z`); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); };
  const setButton = (button, label, disabled, currentPlan = false) => { if (!button) return; setLoading(button, false); const labelNode = button.querySelector('.daymark-plan-action-label'); if (labelNode) labelNode.textContent = label; button.disabled = disabled; button.classList.toggle('is-current', currentPlan); };
  function showSubscription(subscription) {
    if (!current) return;
    if (cancelScheduled) { setLoading(cancelScheduled, false); cancelScheduled.hidden = true; }
    const ended = subscription && subscription.id && ['canceled', 'expired'].includes(subscription.status);
    const pendingReview = subscription && subscription.id && ['pending_review', 'payment_pending', 'pending_payment', 'pending_activation'].includes(subscription.status);
    if (!subscription || ! subscription.id || ended) {
      current.textContent = ended ? t('previousEnded', 'Your previous subscription has ended. Choose a plan to restart.') : t('noActive', 'No active subscription. Choose a plan to get started.');
      buttons.forEach(button => setButton(button, catalogLabel(button), false));
      return;
    }
    const cancellationScheduled = Boolean(Number(subscription.cancel_at_period_end));
    const currentPlanId = String(subscription.plan_id);
    const pendingPlanId = subscription.pending_plan_id ? String(subscription.pending_plan_id) : '';
    const currentPrice = Number(subscription.price || 0);
    const periodLabel = formatDate(subscription.current_period_end);
    current.textContent = `${subscription.name} subscription: ${subscription.status}${cancellationScheduled ? ` — cancellation scheduled${periodLabel ? ` until ${periodLabel}` : ''}` : periodLabel ? ` — renews ${periodLabel}` : ''}.`;
    if (pendingPlanId) {
		const pendingButton = buttons.find(button => button.dataset.daymarkPlanId === pendingPlanId);
      const pendingCard = pendingButton && pendingButton.closest('.daymark-plan');
      const pendingName = pendingCard ? pendingCard.dataset.daymarkPlanName : 'your scheduled plan';
      current.textContent += ` ${t('changeTo', 'Change to')} ${pendingName} ${t('next', 'is scheduled for the next renewal')}.`;
      if (cancelScheduled) cancelScheduled.hidden = false;
    }
    if (pendingReview) { const awaitingPayment = ['payment_pending', 'pending_payment'].includes(subscription.status); const awaitingActivation = 'pending_activation' === subscription.status; const label = awaitingActivation ? t('activationPending', 'Payment verified — activate your account') : awaitingPayment ? t('paymentPending', 'Payment pending') : t('pendingReview', 'Pending review'); current.textContent = `${subscription.name} ${label}.`; buttons.forEach(button => setButton(button, label, true)); return; }
    if (subscription.status === 'past_due') current.textContent += ` ${t('paymentIssue', 'Payment issue: update your payment method with the provider.')}`;
    buttons.forEach(button => {
      const isCurrent = button.dataset.daymarkPlanId === currentPlanId;
      const isPending = button.dataset.daymarkPlanId === pendingPlanId;
      const card = button.closest('.daymark-plan');
      if (!card) return;
      const planPrice = Number(card.dataset.daymarkPlanPrice || 0);
	      const unsupported = config.provider === 'woocommerce' && !isCurrent;
	      const label = unsupported ? t('providerUnsupported', 'Plan changes require Stripe billing.') : isCurrent ? t('currentPlan', 'Current plan') : isPending ? t('scheduled', 'Scheduled') : subscription.status === 'active' ? (planPrice > currentPrice ? t('upgrade', 'Upgrade') : t('downgrade', 'Downgrade')) : t('changePlan', 'Change plan');
	      setButton(button, label, isCurrent || isPending || unsupported, isCurrent);
    });
  }
  function closeDrawer() { if (!drawer) return; drawer.hidden = true; selectedChange = null; if (drawerConfirm) setLoading(drawerConfirm, false); }
  async function openDrawer(button, subscription) {
    if (!drawer || !drawerTitle || !drawerSummary || !drawerPrice || !drawerEffective || !drawerNote || !drawerConfirm) {
      status.className = 'daymark-subscription-status is-error';
      status.textContent = t('confirmUnavailable', 'Plan change confirmation is unavailable. Please refresh the page and try again.');
      return false;
    }
    const card = button && button.closest('.daymark-plan');
    if (!card) { status.className = 'daymark-subscription-status is-error'; status.textContent = t('detailsUnavailable', 'Plan details are unavailable. Please refresh the page and try again.'); return false; }
    const upgrade = Number(card.dataset.daymarkPlanPrice || 0) > Number(subscription.price || 0);
    const immediate = 'immediate' === config.planChangeTiming;
    selectedChange = { button, planId: button.dataset.daymarkPlanId, effective: immediate ? 'immediate' : 'period_end' };
    drawerTitle.textContent = `${immediate ? t('changeNow', 'Change now to') : t('scheduleChange', 'Schedule change to')} ${card.dataset.daymarkPlanName}`;
    drawerSummary.textContent = immediate ? 'The new plan will take effect as soon as the provider confirms the payment change.' : 'Your current plan stays active until the end of this billing period.';
    drawerPrice.textContent = `${card.dataset.daymarkPlanCurrency} ${Number(card.dataset.daymarkPlanPrice).toFixed(2)} / ${card.dataset.daymarkPlanInterval}`;
    drawerEffective.textContent = immediate ? t('immediate', 'Immediately') : (formatDate(subscription.current_period_end) || t('nextRenewal', 'At next renewal'));
    drawerNote.textContent = immediate ? 'The payment provider may create a prorated invoice. If the payment fails, the plan change will not be applied.' : 'The new plan will activate and its recurring charge will apply after the current plan period ends.';
    drawerConfirm.textContent = immediate ? t('confirmChange', 'Confirm plan change') : (upgrade ? t('scheduleUpgrade', 'Schedule upgrade') : t('scheduleDowngrade', 'Schedule downgrade'));
    setLoading(drawerConfirm, true); if (drawerPreview) { drawerPreview.hidden = false; drawerPreview.textContent = t('previewLoading', 'Checking billing preview…'); }
    drawer.hidden = false;
    try {
      const preview = await request('/me/subscription/change/preview', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ plan_id: Number(selectedChange.planId), effective: selectedChange.effective }) });
      if (drawerPreview) { drawerPreview.textContent = preview.provider_preview && preview.provider_preview.available && preview.provider_preview.amount_due !== null ? `${t('proration', 'Estimated immediate amount')} ${preview.provider_preview.currency} ${Number(preview.provider_preview.amount_due).toFixed(2)}. ${t('previewEstimate', 'Final amount is confirmed by the provider.')}` : (preview.provider_preview?.message || t('previewUnavailable', 'The provider will confirm the final amount at payment time.')); }
      setLoading(drawerConfirm, false); drawerConfirm.focus(); return true;
    } catch (error) { if (drawerPreview) drawerPreview.textContent = error.message; setLoading(drawerConfirm, false); drawerConfirm.focus(); return false; }
  }
  async function refresh() { try { showSubscription(await request('/me/subscription')); } catch (error) { status.textContent = error.message; } }
  buttons.forEach(button => button.addEventListener('click', async () => {
    setLoading(button, true); status.className = 'daymark-subscription-status is-saving'; status.textContent = t('checkoutOpening', 'Opening checkout…');
    try {
      const subscription = config.loggedIn ? await request('/me/subscription') : null;
      const renewable = subscription && subscription.id && !['canceled', 'expired'].includes(subscription.status);
      if (renewable) { if (await openDrawer(button, subscription)) { setLoading(button, false); status.textContent = ''; } else setLoading(button, false); }
	      else if (false === config.checkoutReady) throw new Error(config.checkoutMessage || t('checkoutUnavailable', 'Checkout is unavailable until the configured payment gateway is ready.'));
	      else if (config.checkoutUrl) window.location.assign(`${config.checkoutUrl}${config.checkoutUrl.includes('?') ? '&' : '?'}plan_id=${encodeURIComponent(button.dataset.daymarkPlanId)}`);
      else throw new Error(t('checkoutMissing', 'Checkout page is not configured.'));
    } catch (error) { status.className = 'daymark-subscription-status is-error'; status.textContent = error.message; setLoading(button, false); }
  }));
  if (drawer) {
    drawer.querySelectorAll('[data-daymark-plan-change-close]').forEach(element => element.addEventListener('click', closeDrawer));
    if (drawerConfirm) drawerConfirm.addEventListener('click', async () => {
      if (!selectedChange) return;
      setLoading(drawerConfirm, true); status.className = 'daymark-subscription-status is-saving'; status.textContent = t('savingChange', 'Saving plan change…');
      try { await request('/me/subscription/change', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ plan_id: Number(selectedChange.planId), effective: selectedChange.effective }) }); closeDrawer(); status.className = 'daymark-subscription-status is-success'; status.textContent = t('savedChange', 'Plan change saved.'); await refresh(); }
      catch (error) { status.className = 'daymark-subscription-status is-error'; status.textContent = error.message; setLoading(drawerConfirm, false); }
    });
  }
  if (cancelScheduled) cancelScheduled.addEventListener('click', async () => {
    setLoading(cancelScheduled, true); status.className = 'daymark-subscription-status is-saving'; status.textContent = t('cancelingChange', 'Cancelling scheduled change…');
    try { await request('/me/subscription/change/cancel', { method: 'POST' }); status.className = 'daymark-subscription-status is-success'; status.textContent = t('cancelledChange', 'Scheduled plan change cancelled.'); await refresh(); }
    catch (error) { status.className = 'daymark-subscription-status is-error'; status.textContent = error.message; setLoading(cancelScheduled, false); }
  });
  if (config.loggedIn) refresh();
})();
