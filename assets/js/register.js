/**
 * register.js — creates an account via api/register.php
 *
 * Everyone signs up as a resident. Checking "I have an invite code"
 * submits the code to the backend, which validates it and sets the
 * account as a pending official.
 */

/* ── UI helpers ─────────────────────────────────────────────────────────────── */
function fieldError(fieldId, msg) {
  var el = document.getElementById('err-' + fieldId);
  if (!el) return;
  el.textContent = msg;
  var input = document.getElementById(fieldId) || document.querySelector('[name="' + fieldId + '"]');
  if (input) input.classList.toggle('is-invalid', !!msg);
}

function clearErrors() {
  ['fname', 'mname', 'lname', 'email', 'phone',
   'password', 'password_confirm', 'invite_code', 'privacy'].forEach(function (f) {
    fieldError(f, '');
  });
}

function setLoading(loading) {
  var btn = document.querySelector('#register-form button[type="submit"]');
  btn.disabled    = loading;
  btn.textContent = loading ? 'Creating account…' : 'Create account';
}

/* ── Invite code toggle ─────────────────────────────────────────────────────── */
document.getElementById('has-invite').addEventListener('change', function () {
  var field = document.getElementById('invite-code-field');
  field.style.display = this.checked ? '' : 'none';
  if (!this.checked) {
    document.getElementById('invite_code').value = '';
    fieldError('invite_code', '');
  }
});

/* ── Privacy notice dialog ──────────────────────────────────────────────────── */
var privacyDialog = document.getElementById('privacy-dialog');
document.getElementById('show-privacy').addEventListener('click', function () {
  if (privacyDialog && privacyDialog.showModal) privacyDialog.showModal();
});
document.getElementById('close-privacy').addEventListener('click', function () {
  if (privacyDialog && privacyDialog.close) privacyDialog.close();
});

/* ── Form submission ────────────────────────────────────────────────────────── */
document.getElementById('register-form').addEventListener('submit', function (e) {
  e.preventDefault();
  clearErrors();

  var form      = e.target;
  var fname     = form.fname.value.trim();
  var mname     = form.mname.value.trim();
  var lname     = form.lname.value.trim();
  var phone     = form.phone.value.trim();
  var email     = form.email.value.trim().toLowerCase();
  var pass      = form.password.value;
  var conf      = form.password_confirm.value;
  var hasInvite = document.getElementById('has-invite').checked;
  var code      = hasInvite ? form.invite_code.value.trim() : '';
  var privacy   = document.getElementById('privacy_accepted').checked;

  // Client-side pre-validation (mirrors server rules)
  var errors = {};

  if (fname.length < 1 || fname.length > 50)
    errors['fname'] = 'Enter your first name.';

  if (mname.length > 50)
    errors['mname'] = 'Middle name is too long.';

  if (lname.length < 1 || lname.length > 50)
    errors['lname'] = 'Enter your last name.';

  if (!/^[0-9+()\-\s]{7,40}$/.test(phone))
    errors['phone'] = 'Enter a valid mobile number.';

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
    errors['email'] = 'Enter a valid email address — we use it to recover your password.';

  if (pass.length < 8)
    errors['password'] = 'Password must be at least 8 characters.';
  else if (pass.length > 72)
    errors['password'] = 'Password is too long (max 72 characters).';

  if (pass !== conf)
    errors['password_confirm'] = 'Passwords do not match.';

  if (hasInvite && !code)
    errors['invite_code'] = 'Enter the invite code, or uncheck the box.';

  if (!privacy)
    errors['privacy'] = 'You must accept the Data Privacy Notice to register.';

  if (Object.keys(errors).length > 0) {
    Object.keys(errors).forEach(function (f) { fieldError(f, errors[f]); });
    return;
  }

  setLoading(true);

  var payload = {
    fname:            fname,
    mname:            mname,
    lname:            lname,
    phone_number:     phone,
    email:            email || null,
    password:         pass,
    privacy_accepted: true,
  };
  if (hasInvite && code) payload.invite_code = code;

  fetch('api/register.php', {
    method:      'POST',
    credentials: 'same-origin',
    headers:     { 'Content-Type': 'application/json' },
    body:        JSON.stringify(payload),
  })
    .then(function (res) {
      return res.text().then(function (text) {
        var data;
        try {
          data = JSON.parse(text);
        } catch (err) {
          throw new Error(text ? 'Server error during registration.' : 'Could not reach the server. Check your connection.');
        }
        data._status = res.status;
        return data;
      });
    })
    .then(function (data) {
      if (!data.ok) {
        // Map server-side field errors back to the form
        if (data.errors) {
          Object.keys(data.errors).forEach(function (f) {
            // server uses phone_number, form uses phone
            var formField = f === 'phone_number' ? 'phone' : f;
            fieldError(formField, data.errors[f]);
          });
        } else {
          fieldError('fname', data.error || 'Registration failed.');
        }
        setLoading(false);
        return;
      }

      // Cache user and redirect
      localStorage.setItem('agapalert_user', JSON.stringify(data.user));
      localStorage.setItem('agapalert_flash', JSON.stringify({
        type:    data.user.role === 'official' ? 'warning' : 'success',
        message: data.message,
      }));

      window.location.href = 'index.html';
    })
    .catch(function (err) {
      fieldError('fname', (err && err.message) || 'Could not reach the server. Check your connection.');
      setLoading(false);
    });
});
