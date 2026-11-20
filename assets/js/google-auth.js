/**
 * google-auth.js
 * Handles "Continue with Google account" for login and register pages.
 *
 * Login page:
 *   - Existing account → sign in directly (no OTP for returning users)
 *   - No account       → show error with "Register here →" link
 *
 * Register page:
 *   - No account yet   → prefill form with Google profile data
 *   - Already exists   → show error with "Sign in →" link
 */

var _googleMode = window.location.pathname.indexOf('register') !== -1 ? 'register' : 'login';

function _handleGoogleToken(idToken) {
  var errorEl = document.getElementById('google-error');
  var btn     = document.getElementById('google-btn');

  if (errorEl) { errorEl.innerHTML = ''; errorEl.style.display = 'none'; errorEl.className = 'notice notice-error'; }
  if (btn)     { btn.disabled = true; btn.classList.add('btn-google--loading'); }

  fetch('api/auth/google.php', {
    method:      'POST',
    credentials: 'same-origin',
    headers:     { 'Content-Type': 'application/json' },
    body:        JSON.stringify({ credential: idToken, mode: _googleMode }),
  })
    .then(function (res) {
      return res.text().then(function (text) {
        try   { var d = JSON.parse(text); d._status = res.status; return d; }
        catch { throw new Error('HTTP ' + res.status + ': ' + text.slice(0, 200)); }
      });
    })
    .then(function (data) {
      if (btn) { btn.disabled = false; btn.classList.remove('btn-google--loading'); }

      // ── Error responses ───────────────────────────────────────────────────
      if (!data.ok) {
        var msg = data.needs_register
          ? 'No account found. Please register first.'
          : (data.error || 'Google sign-in failed. Please try again.');

        if (errorEl) {
          errorEl.style.display = '';
          errorEl.innerHTML = '';
          errorEl.appendChild(document.createTextNode(msg));
          if (data.needs_register) {
            var a = document.createElement('a');
            a.href = 'register.html';
            a.textContent = ' Register here →';
            a.style.cssText = 'margin-left:0.4rem;font-weight:700;text-decoration:underline;color:inherit;';
            errorEl.appendChild(a);
          }
        }
        return;
      }

      // ── Register page: prefill form with Google profile ───────────────────
      if (data.prefill_only && _googleMode === 'register') {
        var p = data.profile;
        sessionStorage.setItem('agapalert_google_prefill', JSON.stringify(p));

        var fnEl = document.getElementById('fname');
        var lnEl = document.getElementById('lname');
        var emEl = document.getElementById('email');
        if (fnEl && p.fname) fnEl.value = p.fname;
        if (lnEl && p.lname) lnEl.value = p.lname;
        if (emEl && p.email) {
          emEl.value     = p.email;
          emEl.readOnly  = true;
          emEl.style.opacity = '0.65';
        }

        // Hide Google button and divider
        var gBtn = document.getElementById('google-btn');
        var div  = document.querySelector('.auth-divider');
        if (gBtn) gBtn.style.display = 'none';
        if (div)  div.style.display  = 'none';

        // Show success notice
        if (errorEl) {
          errorEl.className    = 'notice notice-success';
          errorEl.textContent  = 'Google account connected. Fill in the remaining fields to complete registration.';
          errorEl.style.display = '';
        }
        return;
      }

      // ── Register page: account already exists ─────────────────────────────
      if (_googleMode === 'register' && !data.is_new) {
        if (errorEl) {
          errorEl.style.display = '';
          errorEl.innerHTML = '';
          errorEl.appendChild(document.createTextNode(
            'This Google account is already registered. Please sign in instead.'
          ));
          var a2 = document.createElement('a');
          a2.href = 'login.html';
          a2.textContent = ' Sign in →';
          a2.style.cssText = 'margin-left:0.4rem;font-weight:700;text-decoration:underline;color:inherit;';
          errorEl.appendChild(a2);
        }
        return;
      }

      // ── OTP required (new registration) ──────────────────────────────────
      if (data.requires_otp) {
        sessionStorage.setItem('agapalert_otp_token', data.otp_token);
        sessionStorage.setItem('agapalert_otp_email', data.email_hint || '');
        sessionStorage.setItem('agapalert_otp_isnew', data.is_new ? '1' : '0');
        window.location.href = 'verify-otp.html';
        return;
      }

      // ── Signed in directly (returning user, no OTP) ───────────────────────
      localStorage.setItem('agapalert_user', JSON.stringify(data.user));
      localStorage.setItem('agapalert_flash', JSON.stringify({
        type:    'success',
        message: 'Signed in with Google. Welcome back.',
      }));
      window.location.href = 'index.html';
    })
    .catch(function (err) {
      if (btn) { btn.disabled = false; btn.classList.remove('btn-google--loading'); }
      if (errorEl) {
        errorEl.textContent   = err.message || 'Could not reach the server.';
        errorEl.style.display = '';
      }
    });
}

// Called by GIS library
window.handleGoogleCredential = function (response) {
  _handleGoogleToken(response.credential);
};

(function () {
  fetch('api/auth/google-config.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (cfg) {
      if (!cfg.client_id) return;

      var btn = document.getElementById('google-btn');
      if (!btn) return;

      var script   = document.createElement('script');
      script.src   = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;

      script.onload = function () {
        var gisWrap = document.createElement('div');
        gisWrap.id  = 'gis-real-btn';
        gisWrap.style.cssText = 'position:absolute;width:100%;height:100%;top:0;left:0;opacity:0;overflow:hidden;cursor:pointer;';
        btn.style.position = 'relative';
        btn.appendChild(gisWrap);

        google.accounts.id.initialize({
          client_id: cfg.client_id,
          callback:  window.handleGoogleCredential,
          ux_mode:   'popup',
        });

        google.accounts.id.renderButton(gisWrap, {
          type:  'standard',
          theme: 'outline',
          size:  'large',
          width: btn.offsetWidth || 320,
        });

        btn.style.display = '';
      };

      document.head.appendChild(script);
    })
    .catch(function () {
      var btn     = document.getElementById('google-btn');
      var divider = document.querySelector('.auth-divider');
      if (btn)     btn.style.display     = 'none';
      if (divider) divider.style.display = 'none';
    });
}());
