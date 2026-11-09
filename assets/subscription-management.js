(function () {
  'use strict';
  const config = window.DaymarkSubscriptionManagementConfig || {};
  const t = (key, fallback) => config.strings && config.strings[key] ? config.strings[key] : fallback;
  const page = document.querySelector('.daymark-subscription-management');
  if (!page || !config.apiBase) return;
  const request = async (path, options = {}) => { const response = await fetch(config.apiBase + path, { ...options, headers: { Accept: 'application/json', ...(options.headers || {}), ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } }); const body = await response.json(); if (!response.ok) throw new Error(body && body.message ? body.message : 'Subscription request failed'); return body; };
  const setLoading = (target, loading) => { if (!target) return; target.disabled = loading; target.classList.toggle('is-loading', loading); target.setAttribute('aria-busy', loading ? 'true' : 'false'); };
  const getSettings = () => request('/me/settings');
  const pauseSubscription = () => request('/me/subscription/pause', { method: 'POST' });
  const resumeSubscription = () => request('/me/subscription/resume', { method: 'POST' });
  const cancelSubscription = reason => request('/me/subscription', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ immediate: false, ...(reason ? { reason: String(reason).slice(0, 500) } : {}) }) });
  const billingPortal = () => request('/me/billing-portal', { method: 'POST' });
  const paymentRecovery = () => request('/me/subscription/payment-recovery', { method: 'POST' });
  const summary = page.querySelector('.daymark-subscription-management-summary');
  const planName = page.querySelector('.daymark-subscription-plan-name');
  const statusBadge = page.querySelector('.daymark-subscription-status-badge');
  const billingAmount = page.querySelector('.daymark-subscription-billing-amount');
  const billingCycle = page.querySelector('.daymark-subscription-billing-cycle');
  const nextBilling = page.querySelector('.daymark-subscription-next-billing');
  const paymentStatus = page.querySelector('.daymark-subscription-payment-status');
  const invoiceList = page.querySelector('.daymark-member-invoice-list');
  const timelineList = page.querySelector('.daymark-member-timeline-list');
  const status = page.querySelector('.daymark-member-subscription-status');
  const pause = page.querySelector('.daymark-member-pause-subscription');
  const cancel = page.querySelector('.daymark-member-cancel-subscription');
  const portal = page.querySelector('.daymark-member-billing-portal');
  const recovery = page.querySelector('.daymark-member-payment-recovery');
  const renew = page.querySelector('.daymark-member-renew-subscription');
  const displayLocale = String(config.locale || '').replace('_', '-') || undefined;
  function formatDate(value) { if (!value) return ''; const date = new Date(`${String(value).replace(' ', 'T')}Z`); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(displayLocale, { timeZone: config.timezone || undefined, year: 'numeric', month: 'short', day: 'numeric' }); }
  function formatCycle(unit) { return { day: t('daily', 'Daily'), month: t('monthly', 'Monthly'), year: t('yearly', 'Yearly') }[unit] || unit || '—'; }
  function formatStatus(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase()) || '—'; }
  function renderInvoices(invoices) {
    invoiceList.replaceChildren();
    if (!invoices || !invoices.length) { invoiceList.textContent = t('noInvoices', 'No invoices yet.'); return; }
    invoices.forEach(invoice => {
      const row = document.createElement('div'); row.className = 'daymark-member-invoice';
      const title = document.createElement('strong'); title.textContent = invoice.plan_name || t('subscriptionInvoice', 'Subscription invoice');
      const meta = document.createElement('span'); meta.textContent = (invoice.currency || '') + ' ' + Number(invoice.amount || 0).toFixed(2) + ' · ' + formatDate(invoice.issued_at) + ' · ' + invoice.status;
      row.append(title, meta);
      const link = document.createElement('a'); link.textContent = invoice.invoice_url ? t('viewInvoice', 'View invoice') : t('downloadReceipt', 'Download receipt'); if (invoice.invoice_url) { link.href = invoice.invoice_url; link.target = '_blank'; link.rel = 'noopener'; } else { const receipt = `Subscription receipt\nPlan: ${invoice.plan_name || 'Subscription'}\nAmount: ${invoice.currency || ''} ${Number(invoice.amount || 0).toFixed(2)}\nIssued: ${invoice.issued_at || ''}\nStatus: ${invoice.status || ''}`; link.href = URL.createObjectURL(new Blob([receipt], { type: 'text/plain' })); link.download = `daymark-receipt-${invoice.id || 'invoice'}.txt`; } row.append(link);
      invoiceList.append(row);
    });
  }
  function renderTimeline(entries) {
    if (!timelineList) return;
    timelineList.replaceChildren();
    if (!entries || !entries.length) { timelineList.textContent = 'No subscription activity yet.'; return; }
    entries.forEach(entry => { const row = document.createElement('div'); row.className = 'daymark-member-timeline-entry'; const action = document.createElement('strong'); action.textContent = formatStatus(entry.action); const date = document.createElement('span'); date.textContent = formatDate(entry.created_at); row.append(action, date); timelineList.append(row); });
  }
  function fill(settings) {
    renderInvoices(settings.invoices || []);
    renderTimeline(settings.timeline || []);
    setLoading(pause, false); setLoading(cancel, false);
    const current = settings.subscription;
    if (!current || !current.id) { planName.textContent = t('noActiveSubscription', 'No active subscription'); statusBadge.hidden = true; summary.className = 'daymark-subscription-management-summary is-empty'; summary.textContent = t('noActiveSummary', 'You do not have an active subscription yet.'); billingAmount.textContent = '—'; billingCycle.textContent = '—'; nextBilling.textContent = '—'; paymentStatus.textContent = '—'; pause.hidden = true; cancel.hidden = true; portal.hidden = true; recovery.hidden = true; renew.hidden = false; renew.textContent = t('upgradePlan', 'Upgrade plan'); return; }
    planName.textContent = current.name || 'Subscription'; statusBadge.hidden = false; statusBadge.textContent = formatStatus(current.status); statusBadge.className = 'daymark-subscription-status-badge is-' + String(current.status || 'unknown').replace(/[^a-z0-9_-]/g, ''); summary.className = 'daymark-subscription-management-summary'; billingAmount.textContent = (current.currency_snapshot || current.currency || '') + ' ' + Number(current.price_snapshot ?? current.price ?? 0).toFixed(2) + (Number(current.quantity || 1) > 1 ? ` × ${Number(current.quantity)} units` : ''); billingCycle.textContent = formatCycle(current.interval_snapshot || current.interval_unit); nextBilling.textContent = formatDate(current.current_period_end) || 'Not scheduled'; paymentStatus.textContent = formatStatus(current.last_payment_status); renew.hidden = !['canceled', 'expired'].includes(current.status); renew.textContent = t('upgradePlan', 'Upgrade plan');
    if (['pending_review', 'payment_pending', 'pending_payment', 'pending_activation'].includes(current.status)) { const activationPending = current.status === 'pending_activation'; const paymentPending = ['payment_pending', 'pending_payment'].includes(current.status); summary.textContent = activationPending ? `${current.name}: ${t('activationAwaiting', 'Payment verified. Check your email to activate your account.')}` : paymentPending ? `${current.name}: ${t('paymentAwaiting', 'Payment is awaiting completion.')}` : `${current.name}: ${t('reviewAwaiting', 'Waiting for admin review. Access will start after approval.')}`; pause.hidden = true; cancel.hidden = true; portal.hidden = true; recovery.hidden = true; return; }
    const cancellationScheduled = Boolean(Number(current.cancel_at_period_end));
    const periodLabel = formatDate(current.current_period_end);
    const commitmentLabel = current.commitment_ends_at && new Date(`${String(current.commitment_ends_at).replace(' ', 'T')}Z`).getTime() > Date.now() ? ` — minimum commitment through ${formatDate(current.commitment_ends_at)}` : '';
    summary.textContent = `${current.name}: ${current.status}${cancellationScheduled ? ` — cancellation scheduled${periodLabel ? ` until ${periodLabel}` : ''}` : periodLabel ? ` — renews ${periodLabel}` : ''}${commitmentLabel}.`;
    const canResume = current.status === 'paused' || cancellationScheduled;
	    pause.hidden = config.provider !== 'stripe' || Number(current.pause_enabled) === 0 || !(['active', 'paused'].includes(current.status) || canResume);
    pause.textContent = canResume ? t('resume', 'Resume subscription') : t('pause', 'Pause subscription');
    cancel.hidden = current.status !== 'active' || cancellationScheduled;
    portal.hidden = config.provider !== 'stripe' || !['active', 'past_due', 'paused'].includes(current.status);
    recovery.hidden = current.status !== 'past_due';
  }
  async function load() { try { fill(await getSettings()); } catch (error) { status.className = 'daymark-member-subscription-status is-error'; status.textContent = error.message; } }
  pause.addEventListener('click', async () => { setLoading(pause, true); status.className = 'daymark-member-subscription-status is-saving'; status.textContent = t('saving', 'Saving…'); try { const current = (await getSettings()).subscription; if (current.status === 'paused' || Boolean(Number(current.cancel_at_period_end))) { await resumeSubscription(); status.textContent = t('resumed', 'Subscription resumed.'); } else { await pauseSubscription(); status.textContent = t('paused', 'Subscription paused.'); } await load(); } catch (error) { status.className = 'daymark-member-subscription-status is-error'; status.textContent = error.message; setLoading(pause, false); } });
  cancel.addEventListener('click', async () => { const reason = window.prompt(t('cancelPrompt', 'Optional: tell us why you are cancelling.')); if (reason === null) return; setLoading(cancel, true); status.className = 'daymark-member-subscription-status is-saving'; status.textContent = t('saving', 'Saving…'); try { await cancelSubscription(reason); status.textContent = t('cancelled', 'Cancellation scheduled for period end.'); await load(); } catch (error) { status.className = 'daymark-member-subscription-status is-error'; status.textContent = error.message; setLoading(cancel, false); } });
  portal.addEventListener('click', async () => { setLoading(portal, true); status.className = 'daymark-member-subscription-status is-saving'; status.textContent = t('billingSettings', 'Opening billing settings…'); try { const result = await billingPortal(); window.location.assign(result.url); } catch (error) { status.className = 'daymark-member-subscription-status is-error'; status.textContent = error.message; setLoading(portal, false); } });
  recovery.addEventListener('click', async () => { setLoading(recovery, true); status.className = 'daymark-member-subscription-status is-saving'; status.textContent = t('paymentOpening', 'Opening payment…'); try { const result = await paymentRecovery(); window.location.assign(result.url); } catch (error) { status.className = 'daymark-member-subscription-status is-error'; status.textContent = error.message; setLoading(recovery, false); } });
  load();
})();
