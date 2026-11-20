/**
 * google-auth.js
 * Uses Google Identity Services tokenClient (OAuth2 popup) so the account
 * picker always opens reliably on button click — no One Tap suppression issues.
 */

var _googleMode = window.location.pathname.indexOf('register') !== -1 ? 'register' : 'login';

function _handleGoogleToken(idToken) {
  var errorEl = document.getElementById('google-error');
  var btn     = document.getElementById('google-btn');

  if (errorEl) { errorEl.innerHTML = ''; errorEl.style.display = 'none'; }
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

      // OTP required — redirect to verify page
      if (data.requires_otp) {

        // On register page: if account already exists, warn and redirect to login
        if (_googleMode === 'register' && !data.is_new) {
          if (errorEl) {
            errorEl.innerHTML = '';
            errorEl.style.display = '';
            errorEl.appendChild(document.createTextNode(
              'This Google account is already registered. Please sign in instead.'
            ));
            var a = document.createElement('a');
            a.href = 'login.html';
            a.textContent = ' Sign in →';
            a.style.cssText = 'margin-left:0.4rem;font-weight:700;text-decoration:underline;color:inherit;';
            errorEl.appendChild(a);
          }
          if (btn) { btn.disabled = false; btn.classList.remove('btn-google--loading'); }
          return;
        }

        sessionStorage.setItem('agapalert_otp_token', data.otp_token);
        sessionStorage.setItem('agapalert_otp_email', data.email_hint || '');
        sessionStorage.setItem('agapalert_otp_isnew', data.is_new ? '1' : '0');
        window.location.href = 'verify-otp.html';
        return;
      }

      localStorage.setItem('agapalert_user', JSON.stringify(data.user));
      var msg = data.is_new
        ? 'Account created with Google. Welcome to AgapAlert.'
        : 'Signed in with Google. Welcome back.';
      localStorage.setItem('agapalert_flash', JSON.stringify({ type: 'success', message: msg }));
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

// Called by GIS library via the data-callback attribute (One Tap fallback)
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

      // Load GIS script
      var script   = document.createElement('script');
      script.src   = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;

      script.onload = function () {
        // Use renderButton inside a real (visible) wrapper — GIS clicks it on our behalf
        var gisWrap = document.createElement('div');
        gisWrap.id  = 'gis-real-btn';
        gisWrap.style.cssText = 'position:absolute;width:100%;height:100%;top:0;left:0;opacity:0;overflow:hidden;cursor:pointer;';
        btn.style.position = 'relative';   // ensure the wrapper positions inside btn
        btn.appendChild(gisWrap);

        google.accounts.id.initialize({
          client_id: cfg.client_id,
          callback:  window.handleGoogleCredential,
          ux_mode:   'popup',
        });

        // Render GIS button into the transparent overlay — same size as our button
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
