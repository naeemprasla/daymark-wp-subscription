(function () {
  'use strict';
  const config = window.DaymarkMemberSettingsConfig || {};
  const personalForm = document.querySelector('.daymark-settings-personal-form');
  const contactForm = document.querySelector('.daymark-settings-contact-form');
  if ((!personalForm && !contactForm) || !config.apiBase) return;
  const request = async (path, options = {}) => { const response = await fetch(config.apiBase + path, { ...options, headers: { Accept: 'application/json', ...(options.headers || {}), ...(config.nonce ? { 'X-WP-Nonce': config.nonce } : {}) } }); const body = await response.json(); if (!response.ok) throw new Error(body && body.message ? body.message : 'Subscription request failed'); return body; };
  const setLoading = (target, loading) => { if (!target) return; target.disabled = loading; target.classList.toggle('is-loading', loading); target.setAttribute('aria-busy', loading ? 'true' : 'false'); };
  const getSettings = () => request('/me/settings');
  const updateSettings = data => request('/me/settings', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  const requestEmailChange = email => request('/me/email-change', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email }) });
  const uploadProfilePicture = file => { const form = new FormData(); form.append('profile_picture', file); return request('/me/profile-picture', { method: 'POST', body: form }); };
  const picture = contactForm ? contactForm.elements.profile_picture : null;
  const emailStatus = contactForm ? contactForm.querySelector('.daymark-settings-email-status') : null;
  const picturePreview = contactForm ? contactForm.querySelector('.daymark-settings-picture-preview') : null;
  const cropModal = contactForm ? contactForm.querySelector('.daymark-picture-crop-modal') : null;
  const cropCanvas = cropModal ? cropModal.querySelector('.daymark-picture-crop-canvas') : null;
  const cropSave = cropModal ? cropModal.querySelector('.daymark-picture-crop-save') : null;
  const cropCancel = cropModal ? cropModal.querySelector('.daymark-picture-crop-cancel') : null;
  const cropContext = cropCanvas ? cropCanvas.getContext('2d') : null;
  let cropImage = null; let cropScale = 1; let cropX = 0; let cropY = 0; let dragStart = null;
  function formatDate(value) { if (!value) return ''; const date = new Date(`${String(value).replace(' ', 'T')}Z`); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
  function fill(settings) {
    [personalForm, contactForm].filter(Boolean).forEach(form => { ['email', 'phone', 'display_name', 'first_name', 'last_name', 'profile_description'].forEach(field => { if (form.elements[field]) form.elements[field].value = settings[field] || ''; }); });
    if (emailStatus) emailStatus.textContent = settings.pending_email ? `Confirmation pending for ${settings.pending_email}.` : 'Changing this email requires confirmation.';
    if (picture) { picture.value = ''; picturePreview.src = settings.profile_picture_url || ''; picturePreview.hidden = !settings.profile_picture_url; }
    if (personalForm && personalForm.elements.product_updates) personalForm.elements.product_updates.checked = Boolean(settings.product_updates);
  }
  async function load() { try { fill(await getSettings()); } catch (error) { const status = personalForm && personalForm.querySelector('.daymark-member-settings-status'); if (status) status.textContent = error.message; } }
  async function savePersonal(event) {
    event.preventDefault(); const saveButton = personalForm.querySelector('button[type="submit"]'); const status = personalForm.querySelector('.daymark-member-settings-status'); setLoading(saveButton, true); personalForm.setAttribute('aria-busy', 'true'); status.className = 'daymark-member-settings-status is-saving'; status.textContent = 'Saving…';
    try { if (window.tinymce) window.tinymce.triggerSave(); const settings = await updateSettings({ display_name: personalForm.elements.display_name.value, first_name: personalForm.elements.first_name.value, last_name: personalForm.elements.last_name.value, profile_description: personalForm.elements.profile_description ? personalForm.elements.profile_description.value : '', product_updates: personalForm.elements.product_updates.checked }); fill(settings); status.className = 'daymark-member-settings-status is-success'; status.textContent = 'Personal details saved.'; } catch (error) { status.className = 'daymark-member-settings-status is-error'; status.textContent = error.message; } finally { setLoading(saveButton, false); personalForm.removeAttribute('aria-busy'); }
  }
  function drawCrop() { if (!cropImage || !cropContext) return; cropContext.clearRect(0, 0, cropCanvas.width, cropCanvas.height); cropContext.fillStyle = '#eef5f4'; cropContext.fillRect(0, 0, cropCanvas.width, cropCanvas.height); cropContext.drawImage(cropImage, cropX, cropY, cropImage.width * cropScale, cropImage.height * cropScale); }
  function openCrop(file) { const reader = new FileReader(); reader.onload = () => { cropImage = new Image(); cropImage.onload = () => { cropScale = Math.max(cropCanvas.width / cropImage.width, cropCanvas.height / cropImage.height); cropX = (cropCanvas.width - cropImage.width * cropScale) / 2; cropY = (cropCanvas.height - cropImage.height * cropScale) / 2; drawCrop(); cropModal.hidden = false; cropSave.focus(); }; cropImage.src = reader.result; }; reader.readAsDataURL(file); }
  async function saveContact(file) {
    const status = contactForm.querySelector('.daymark-member-settings-status'); if (!file) return; contactForm.setAttribute('aria-busy', 'true'); status.className = 'daymark-member-settings-status is-saving'; status.textContent = 'Uploading…';
    try { fill(await uploadProfilePicture(file)); status.className = 'daymark-member-settings-status is-success'; status.textContent = 'Profile picture saved.'; } catch (error) { status.className = 'daymark-member-settings-status is-error'; status.textContent = error.message; } finally { contactForm.removeAttribute('aria-busy'); }
  }
  async function saveContactDetails(event) {
    event.preventDefault(); const saveButton = contactForm.querySelector('button[type="submit"]'); const status = contactForm.querySelector('.daymark-member-settings-status'); setLoading(saveButton, true); contactForm.setAttribute('aria-busy', 'true'); status.className = 'daymark-member-settings-status is-saving'; status.textContent = 'Saving…';
    try { const current = await getSettings(); let message = ''; if (contactForm.elements.email.value.trim().toLowerCase() !== String(current.email || '').toLowerCase()) { const result = await requestEmailChange(contactForm.elements.email.value.trim()); message = result.message || 'Confirmation email sent.'; } fill(await updateSettings({ phone: contactForm.elements.phone.value })); status.className = 'daymark-member-settings-status is-success'; status.textContent = message || 'Contact details saved.'; } catch (error) { status.className = 'daymark-member-settings-status is-error'; status.textContent = error.message; } finally { setLoading(saveButton, false); contactForm.removeAttribute('aria-busy'); }
  }
  if (personalForm) personalForm.addEventListener('submit', savePersonal);
  if (contactForm) contactForm.addEventListener('submit', saveContactDetails);
  if (cropCanvas) { cropCanvas.addEventListener('pointerdown', event => { if (!cropImage) return; dragStart = { x: event.clientX, y: event.clientY, cropX, cropY }; cropCanvas.setPointerCapture(event.pointerId); }); cropCanvas.addEventListener('pointermove', event => { if (!dragStart) return; cropX = Math.min(0, Math.max(cropCanvas.width - cropImage.width * cropScale, dragStart.cropX + event.clientX - dragStart.x)); cropY = Math.min(0, Math.max(cropCanvas.height - cropImage.height * cropScale, dragStart.cropY + event.clientY - dragStart.y)); drawCrop(); }); cropCanvas.addEventListener('pointerup', () => { dragStart = null; }); }
  if (cropCancel) cropCancel.addEventListener('click', () => { cropModal.hidden = true; cropImage = null; if (picture) picture.value = ''; });
  if (cropSave) cropSave.addEventListener('click', () => { if (!cropImage) return; cropSave.disabled = true; cropCanvas.toBlob(async blob => { if (!blob) { cropSave.disabled = false; return; } cropModal.hidden = true; cropImage = null; await saveContact(new File([blob], 'profile-picture.jpg', { type: 'image/jpeg' })); cropSave.disabled = false; }, 'image/jpeg', .9); });
  if (picture) picture.addEventListener('change', () => { const file = picture.files[0]; if (!file) return; const status = contactForm.querySelector('.daymark-member-settings-status'); if (!file.type.startsWith('image/')) { status.className = 'daymark-member-settings-status is-error'; status.textContent = 'Choose a valid image file.'; picture.value = ''; return; } if (file.size > 5 * 1024 * 1024) { status.className = 'daymark-member-settings-status is-error'; status.textContent = 'Profile pictures must be 5 MB or smaller.'; picture.value = ''; return; } openCrop(file); });
  load();
})();
