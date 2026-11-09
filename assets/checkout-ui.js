(function () {
  'use strict';
  const config = window.DaymarkCheckoutConfig || {};
  const checkout = document.querySelector('[data-daymark-checkout-plan]');
  const button = document.querySelector('.daymark-checkout-submit');
  const status = document.querySelector('.daymark-checkout-status');
  if (!checkout || !button) return;
	if (false === config.checkoutReady) { button.disabled = true; status.className = 'daymark-checkout-status is-error'; status.textContent = config.checkoutMessage || 'Checkout is unavailable until the configured payment gateway is ready.'; return; }
  const setLoading = (target, loading) => { target.disabled = loading; target.classList.toggle('is-loading', loading); target.setAttribute('aria-busy', loading ? 'true' : 'false'); };
  const request = async (path, options = {}) => { const response = await fetch(config.apiBase + path, { ...options, headers: { Accept: 'application/json', ...(options.headers || {}), ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } }); const body = await response.json(); if (!response.ok) throw new Error(body && body.message ? body.message : 'Subscription request failed'); return body; };
  let quantityInput = null;
  request('/plans').then(plans => { const plan = Array.isArray(plans) ? plans.find(item => Number(item.id) === Number(checkout.dataset.daymarkCheckoutPlan)) : null; if (!plan || !Number(plan.quantity_enabled)) return; const label = document.createElement('label'); label.textContent = 'Quantity / seats'; quantityInput = document.createElement('input'); quantityInput.className = 'daymark-checkout-quantity'; quantityInput.type = 'number'; quantityInput.min = String(Math.max(1, Number(plan.quantity_min) || 1)); quantityInput.max = String(Math.max(Number(quantityInput.min), Number(plan.quantity_max) || 1)); quantityInput.step = '1'; quantityInput.value = quantityInput.min; quantityInput.required = true; label.appendChild(quantityInput); document.querySelector('.daymark-checkout-fields')?.appendChild(label); }).catch(() => {});
  button.addEventListener('click', async () => {
    setLoading(button, true);
    status.className = 'daymark-checkout-status is-saving';
    status.textContent = 'Preparing secure payment…';
    try {
      const result = await request('/me/checkout', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Daymark-Idempotency-Key': (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now())) }, body: JSON.stringify({
        plan_id: Number(checkout.dataset.daymarkCheckoutPlan),
        customer_name: document.querySelector('.daymark-checkout-name').value.trim(),
        customer_email: document.querySelector('.daymark-checkout-email').value.trim(),
        customer_phone: document.querySelector('.daymark-checkout-phone').value.trim(),
        quantity: quantityInput ? Number(quantityInput.value) : 1
      }) });
      if (!result || !result.url) throw new Error('The payment provider did not return a checkout URL.');
      window.location.assign(result.url);
    } catch (error) {
      status.className = 'daymark-checkout-status is-error';
      status.textContent = error.message;
      setLoading(button, false);
    }
  });
})();
