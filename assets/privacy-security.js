(function (root) {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.daymark-privacy-security-form');
    var config = root.DaymarkPrivacySecurityConfig || {};
    if (!form || !config.apiBase) return;
    var request = function (path, options) { options = options || {}; return fetch(config.apiBase + path, Object.assign({}, options, { headers: Object.assign({ Accept: 'application/json' }, options.headers || {}, config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) })).then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body && body.message ? body.message : 'Subscription request failed'); return body; }); }); };
    var setLoading = function (target, loading) { target.disabled = loading; target.classList.toggle('is-loading', loading); target.setAttribute('aria-busy', loading ? 'true' : 'false'); };
    var button = form.querySelector('button[type="submit"]');
    var status = form.querySelector('.daymark-privacy-security-status');
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      setLoading(button, true);
      status.textContent = 'Saving…';
      status.className = 'daymark-privacy-security-status is-saving';
      request('/me/settings', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
        profile_visibility: form.querySelector('[name="profile_visibility"]').checked ? 'public' : 'private',
        product_updates: form.querySelector('[name="product_updates"]').checked
      }) }).then(function () {
        status.textContent = 'Privacy settings saved.';
        status.className = 'daymark-privacy-security-status is-success';
      }).catch(function (error) {
        status.textContent = error.message || 'Settings could not be saved.';
        status.className = 'daymark-privacy-security-status is-error';
      }).finally(function () { setLoading(button, false); });
    });
    var exportButton = document.querySelector('[data-daymark-privacy-export]'); var deleteButton = document.querySelector('[data-daymark-privacy-delete]'); var logoutButton = document.querySelector('[data-daymark-privacy-logout]'); var dataStatus = document.querySelector('.daymark-privacy-data-status');
    if (exportButton) exportButton.addEventListener('click', function () { setLoading(exportButton, true); dataStatus.textContent = 'Preparing export…'; request('/me/privacy/export').then(function (data) { var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }); var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'daymark-account-data.json'; link.click(); URL.revokeObjectURL(link.href); dataStatus.textContent = 'Your data export is ready.'; }).catch(function (error) { dataStatus.textContent = error.message || 'The export could not be prepared.'; }).finally(function () { setLoading(exportButton, false); }); });
    if (deleteButton) deleteButton.addEventListener('click', function () { if (!window.confirm('Send an account deletion request for administrator review?')) return; setLoading(deleteButton, true); dataStatus.textContent = 'Sending request…'; request('/me/privacy/delete-request', { method: 'POST' }).then(function (result) { dataStatus.textContent = result.message || 'Deletion request sent for review.'; }).catch(function (error) { dataStatus.textContent = error.message || 'The deletion request could not be sent.'; }).finally(function () { setLoading(deleteButton, false); }); });
    if (logoutButton) logoutButton.addEventListener('click', function () { if (!window.confirm('Sign out all active sessions? You will need to sign in again on this device.')) return; setLoading(logoutButton, true); dataStatus.textContent = 'Signing out sessions…'; request('/me/security/logout-sessions', { method: 'POST' }).then(function (result) { dataStatus.textContent = result.message || 'All active sessions were signed out.'; }).catch(function (error) { dataStatus.textContent = error.message || 'Sessions could not be signed out.'; }).finally(function () { setLoading(logoutButton, false); }); });
  });
})(window);
