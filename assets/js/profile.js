(function () {

  /* ── Confirm helper ──────────────────────────────────────────────────────── */
  function confirm(opts) {
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,5,15,.7);display:flex;align-items:center;justify-content:center;z-index:999;padding:20px;backdrop-filter:blur(4px)';
    var box = document.createElement('div');
    box.style.cssText = 'background:var(--s1,#fff);border:1px solid var(--line2,#ddd);border-radius:12px;padding:24px;width:min(400px,100%);display:flex;flex-direction:column;gap:14px;box-shadow:0 12px 40px rgba(0,0,0,.4)';
    box.innerHTML = '<h3 style="margin:0;font-size:18px;font-weight:700;color:var(--ink,#111)">' + opts.title + '</h3>'
      + '<p style="margin:0;font-size:14px;color:var(--mute,#666);line-height:1.6">' + opts.body + '</p>'
      + '<div style="display:flex;gap:8px;justify-content:flex-end">'
          + '<button class="btn o" id="ac-cancel">Kanselahin</button>'
          + '<button class="btn' + (opts.okClass ? ' ' + opts.okClass : ' ac') + '" id="ac-ok">' + (opts.okLabel || 'Kumpirmahin') + '</button>'
      + '</div>';
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    function close() { document.body.removeChild(overlay); }
    box.querySelector('#ac-cancel').addEventListener('click', close);
    box.querySelector('#ac-ok').addEventListener('click', function () { close(); opts.onOk(); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); } });
  }

  /* ── Auth guard ──────────────────────────────────────────────────────────── */
  var raw  = localStorage.getItem('agapalert_user');
  var user = null;
  try { user = JSON.parse(raw); } catch (e) {}
  if (!user) { window.location.href = 'login.html'; return; }

  /* ── Theme ───────────────────────────────────────────────────────────────── */
  var root     = document.documentElement;
  var themeBtn = document.getElementById('theme-btn');

  function applyTheme(dark) {
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    themeBtn.innerHTML = dark
      ? '<svg class="i"><use href="#i-sun"/></svg>'
      : '<svg class="i"><use href="#i-moon"/></svg>';
    try { localStorage.setItem('agapalert_theme', dark ? 'dark' : 'light'); } catch (e) {}
  }

  var saved = localStorage.getItem('agapalert_theme');
  applyTheme(saved ? saved === 'dark' : true);
  themeBtn.addEventListener('click', function () {
    applyTheme(root.getAttribute('data-theme') !== 'dark');
  });

  /* ── Header chip ─────────────────────────────────────────────────────────── */
  var chip = document.getElementById('user-chip');
  document.getElementById('user-role').textContent =
    user.role === 'admin'    ? 'Administrador' :
    user.role === 'official' ? (user.status === 'pending' ? 'Opisyal (naghihintay)' : 'Opisyal ng Barangay') :
    'Residente';
  chip.style.display = 'flex';

  document.getElementById('signout-btn').addEventListener('click', function () {
    fetch('api/logout.php', { method: 'POST', credentials: 'same-origin' })
      .finally(function () {
        localStorage.removeItem('agapalert_user');
        window.location.href = 'login.html';
      });
  });

  /* ── Settings dropdown ───────────────────────────────────────────────────── */
  var settingsBtn      = document.getElementById('settings-btn');
  var settingsDropdown = document.getElementById('settings-dropdown');

  settingsBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = settingsDropdown.classList.toggle('open');
    settingsBtn.setAttribute('aria-expanded', String(open));
  });

  document.addEventListener('click', function (e) {
    if (!settingsDropdown.contains(e.target) && e.target !== settingsBtn) {
      settingsDropdown.classList.remove('open');
      settingsBtn.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      settingsDropdown.classList.remove('open');
      settingsBtn.setAttribute('aria-expanded', 'false');
    }
  });

  /* ── Chip avatar ─────────────────────────────────────────────────────────── */
  function updateChipAvatar() {
    var nameEl    = document.getElementById('user-name');
    var avatarSrc = user.avatar_path || user.google_avatar_url || null;
    if (avatarSrc) {
      nameEl.innerHTML = '<img src="' + avatarSrc + '" alt="" style="width:26px;height:26px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:4px">' + (user.full_name || '');
    } else {
      nameEl.textContent = user.full_name || '';
    }
  }
  updateChipAvatar();

  /* ── Populate fields ─────────────────────────────────────────────────────── */
  function populate() {
    var initial   = (user.fname || user.full_name || '?').charAt(0).toUpperCase();
    var avatarEl  = document.getElementById('avatar-el');
    var initialEl = document.getElementById('avatar-initial');
    var removeBtn = document.getElementById('avatar-remove');

    // Priority: uploaded photo → Google photo → initial letter
    var avatarSrc = user.avatar_path || user.google_avatar_url || null;
    var isGoogle  = !user.avatar_path && !!user.google_avatar_url;

    if (avatarSrc) {
      // Hide the initial letter
      if (initialEl) initialEl.style.display = 'none';
      // Find or create the img element
      var img = avatarEl.querySelector('img');
      if (!img) {
        img = document.createElement('img');
        img.alt = user.full_name || '';
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;';
        avatarEl.appendChild(img);
      }
      img.src = avatarSrc;
      img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;';
      // Show remove button only for uploaded photos, not Google photos
      if (removeBtn) removeBtn.style.display = isGoogle ? 'none' : 'block';
    } else {
      // No photo — show initial letter, remove any img
      var oldImg = avatarEl.querySelector('img');
      if (oldImg) oldImg.remove();
      if (initialEl) {
        initialEl.style.display = '';
        initialEl.textContent   = initial;
      }
      if (removeBtn) removeBtn.style.display = 'none';
    }

    document.getElementById('display-name').textContent = user.full_name || '—';
    document.getElementById('display-sub').textContent  =
      (user.barangay_name ? user.barangay_name + (user.barangay_city ? ', ' + user.barangay_city : '') : '')
      || (user.email || user.phone_number || '');

    var badge   = document.getElementById('role-badge');
    var isAdmin = user.role === 'admin';
    var isOff   = user.role === 'official';
    var isPend  = isOff && user.status === 'pending';
    badge.textContent = isAdmin ? 'Administrador' : isPend ? 'Opisyal — naghihintay' : isOff ? 'Opisyal ng Barangay' : 'Residente';
    badge.className   = 'role-badge ' + (isAdmin ? 'role-admin' : isPend ? 'role-pending' : isOff ? 'role-official' : 'role-resident');

    document.getElementById('pending-notice').style.display  = isPend             ? '' : 'none';
    document.getElementById('official-notice').style.display = (isOff && !isPend) ? '' : 'none';

    document.getElementById('pf-fname').value = user.fname        || '';
    document.getElementById('pf-mname').value = user.mname        || '';
    document.getElementById('pf-lname').value = user.lname        || '';
    document.getElementById('pf-phone').value = user.phone_number || '';
    document.getElementById('pf-email').value = user.email        || '';
  }

  populate();

  // If localStorage has stale data (no google_avatar_url despite having google_id),
  // fetch fresh user data immediately so the Google profile picture shows right away.
  if (user.google_id && !user.google_avatar_url) {
    fetch('api/me.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok && data.user && data.user.google_avatar_url) {
          user = data.user;
          localStorage.setItem('agapalert_user', JSON.stringify(user));
          populate();
          updateChipAvatar();
        }
      })
      .catch(function () {});
  }

  /* ── Avatar upload ───────────────────────────────────────────────────────── */
  document.getElementById('avatar-input').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    if (file.size > 4 * 1024 * 1024) { alert('Ang larawan ay dapat wala pang 4 MB.'); return; }

    var formData = new FormData();
    formData.append('avatar', file);

    fetch('api/profile/avatar.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) { alert(data.error || 'Nabigo ang pag-upload.'); return; }
        user.avatar_path = data.avatar_path;
        localStorage.setItem('agapalert_user', JSON.stringify(user));
        populate();
        updateChipAvatar();
      })
      .catch(function () { alert('Nabigo ang pag-upload. Suriin ang iyong koneksyon.'); });

    this.value = '';
  });

  document.getElementById('avatar-remove').addEventListener('click', function () {
    confirm({
      title: 'Remove profile picture?',
      body:  'Your profile picture will be deleted. You can upload a new one any time.',
      okLabel: 'Remove', okClass: 'o',
      onOk: function () {
        fetch('api/profile/avatar.php', { method: 'DELETE', credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.ok) return;
            user.avatar_path = null;
            localStorage.setItem('agapalert_user', JSON.stringify(user));
            populate();
            updateChipAvatar();
          });
      }
    });
  });

  document.getElementById('avatar-el').addEventListener('click', function () {
    document.getElementById('avatar-input').click();
  });

  /* ── Validation helpers ──────────────────────────────────────────────────── */
  function showErr(id, msg) {
    var el = document.getElementById('err-' + id);
    if (!el) return;
    el.textContent   = msg;
    el.style.display = msg ? 'block' : 'none';
    var inp = document.getElementById('pf-' + id);
    if (inp) inp.classList.toggle('error', !!msg);
  }
  function clearErrs(ids) { ids.forEach(function (id) { showErr(id, ''); }); }

  function flashStatus(id) {
    var el = document.getElementById(id);
    el.classList.add('on');
    setTimeout(function () { el.classList.remove('on'); }, 2500);
  }

  /* ── Save personal info ──────────────────────────────────────────────────── */
  document.getElementById('save-info').addEventListener('click', function () {
    clearErrs(['fname', 'mname', 'lname', 'phone', 'email']);

    var fname = document.getElementById('pf-fname').value.trim();
    var mname = document.getElementById('pf-mname').value.trim();
    var lname = document.getElementById('pf-lname').value.trim();
    var phone = document.getElementById('pf-phone').value.trim();
    var email = document.getElementById('pf-email').value.trim().toLowerCase();
    var ok    = true;

    if (fname.length < 1 || fname.length > 50) { showErr('fname', 'Ilagay ang iyong pangalan.'); ok = false; }
    if (mname.length > 50) { showErr('mname', 'Masyadong mahaba ang gitnang pangalan.'); ok = false; }
    if (lname.length < 1 || lname.length > 50) { showErr('lname', 'Ilagay ang iyong apelyido.'); ok = false; }
    if (phone && !/^[0-9+()\-\s]{7,40}$/.test(phone)) { showErr('phone', 'Mukhang mali ang numero ng mobile.'); ok = false; }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showErr('email', 'Mukhang mali ang email address.'); ok = false; }
    if (!ok) return;

    var btn  = document.getElementById('save-info');
    btn.disabled = true;

    fetch('api/profile/update.php', {
      method:      'POST',
      credentials: 'same-origin',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ fname: fname, mname: mname, lname: lname, phone_number: phone || null, email: email || null }),
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        btn.disabled = false;
        if (!data.ok) {
          if (data.errors) Object.keys(data.errors).forEach(function (f) { showErr(f === 'phone_number' ? 'phone' : f, data.errors[f]); });
          return;
        }
        user = data.user;
        localStorage.setItem('agapalert_user', JSON.stringify(user));
        populate();
        updateChipAvatar();
        flashStatus('info-saved');
      })
      .catch(function () { btn.disabled = false; showErr('fname', 'Hindi ma-reach ang server.'); });
  });

  /* ── Force-password-change guard ────────────────────────────────────────── */
  var _mustChange = new URLSearchParams(window.location.search).get('force_password') === '1'
    || user.must_change_password == 1
    || user.must_change_password === true;

  function applyForcedChangeUI() {
    if (!_mustChange) return;

    var notice = document.getElementById('force-pw-notice');
    if (notice) notice.style.display = '';

    var curField = document.getElementById('current-pw-field');
    if (curField) curField.style.display = 'none';

    var sub = document.getElementById('pw-card-sub');
    if (sub) sub.textContent = 'Itakda ang iyong bagong password para makapagpatuloy.';

    var card = document.getElementById('pw-card');
    if (card) setTimeout(function () { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
  }

  applyForcedChangeUI();

  // Re-check the server flag so a stale URL/localStorage value cannot skip
  // current-password verification when the account is not actually forced.
  fetch('api/me.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok || !data.user) {
        localStorage.removeItem('agapalert_user');
        window.location.href = 'login.html';
        return;
      }
      user = data.user;
      localStorage.setItem('agapalert_user', JSON.stringify(user));

      // ── Google account adjustments ────────────────────────────────────────
      var isGoogleOnly = !!user.google_id && !user.phone_number;
      var hasGoogle    = !!user.google_id;

      // Lock the email field — Google email should not be changed
      if (hasGoogle) {
        var emailEl = document.getElementById('pf-email');
        if (emailEl) {
          emailEl.readOnly = true;
          emailEl.style.opacity = '0.65';
          emailEl.style.cursor  = 'default';
          emailEl.title = 'Email is linked to your Google account and cannot be changed here.';
        }
        var emailLabel = document.querySelector('label[for="pf-email"]');
        if (emailLabel && !emailLabel.querySelector('.google-lock')) {
          var lock = document.createElement('span');
          lock.className = 'google-lock';
          lock.style.cssText = 'margin-left:4px;font-size:11px;color:var(--mute);font-weight:400;text-transform:none;letter-spacing:0';
          lock.textContent = '(linked to Google)';
          emailLabel.appendChild(lock);
        }
      }

      // Hide password card for Google-only accounts (no password set)
      var pwCard = document.getElementById('pw-card');
      if (pwCard && hasGoogle && !data.user.has_password) {
        pwCard.innerHTML = '<div class="form-card-head"><h3>Password</h3>'
          + '<p>You sign in with Google. No password is set for this account.</p></div>';
      }

      _mustChange = user.must_change_password == 1 || user.must_change_password === true;
      if (_mustChange) {
        applyForcedChangeUI();
      } else {
        var notice = document.getElementById('force-pw-notice');
        if (notice) notice.style.display = 'none';
        var curField = document.getElementById('current-pw-field');
        if (curField) curField.style.display = '';
      }
      populate();
      updateChipAvatar();
    })
    .catch(function () { /* offline — keep going with cached user */ });

  /* ── Save password ───────────────────────────────────────────────────────── */
  document.getElementById('save-pw').addEventListener('click', function () {
    clearErrs(['pw0', 'pw1', 'pw2']);
    var pw0 = document.getElementById('pf-pw0') ? document.getElementById('pf-pw0').value : '';
    var pw1 = document.getElementById('pf-pw1').value;
    var pw2 = document.getElementById('pf-pw2').value;
    var ok  = true;

    // Require current password unless this is a forced change
    if (!_mustChange && (!pw0 || pw0.length < 1)) {
      showErr('pw0', 'Ilagay ang iyong kasalukuyang password.');
      ok = false;
    }
    if (!pw1 || pw1.length < 8) { showErr('pw1', 'Hindi bababa sa 8 na character.'); ok = false; }
    if (pw1 !== pw2)             { showErr('pw2', 'Hindi magkatugma ang mga password.'); ok = false; }
    if (!ok) return;

    function doPwChange() {
      var btn = document.getElementById('save-pw');
      btn.disabled = true;
      var payload = { password: pw1, password_confirm: pw2 };
      if (!_mustChange) payload.current_password = pw0;

      fetch('api/profile/password.php', {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(payload),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          btn.disabled = false;
          if (!data.ok) {
            if (data.errors) Object.keys(data.errors).forEach(function (f) {
              var fieldId = f === 'current_password' ? 'pw0' : f === 'password' ? 'pw1' : f === 'password_confirm' ? 'pw2' : f;
              showErr(fieldId, data.errors[f]);
            });
            return;
          }
          document.getElementById('pf-pw1').value = '';
          document.getElementById('pf-pw2').value = '';
          if (document.getElementById('pf-pw0')) document.getElementById('pf-pw0').value = '';
          if (_mustChange) {
            user.must_change_password = 0;
            localStorage.setItem('agapalert_user', JSON.stringify(user));
            localStorage.setItem('agapalert_flash', JSON.stringify({ type: 'success', message: 'Password updated. Welcome to AgapAlert.' }));
            window.location.href = 'index.html';
            return;
          }
          flashStatus('pw-saved');
        })
        .catch(function () { btn.disabled = false; showErr('pw1', 'Hindi ma-reach ang server.'); });
    }

    if (_mustChange) {
      // Skip confirmation dialog for forced change — just do it
      doPwChange();
    } else {
      confirm({
        title: 'Baguhin ang password?',
        body:  'Papalitan ang iyong kasalukuyang password. Kailangan mong gamitin ang bagong password sa susunod na pag-sign in.',
        okLabel: 'Baguhin', okClass: 'ac',
        onOk: doPwChange
      });
    }
  });

  /* ── Sign out ────────────────────────────────────────────────────────────── */
  document.getElementById('signout-page').addEventListener('click', function () {
    confirm({
      title: 'Mag-sign out?',
      body:  'Aalis ka sa iyong account. Kailangan mong mag-sign in ulit.',
      okLabel: 'Sign out', okClass: 'o',
      onOk: function () {
        fetch('api/logout.php', { method: 'POST', credentials: 'same-origin' })
          .finally(function () {
            localStorage.removeItem('agapalert_user');
            window.location.href = 'login.html';
          });
      }
    });
  });

  /* ── Delete account (disabled — no delete API yet) ───────────────────────── */
  // Intentionally no click handler: the button is disabled in profile.html
  // because window.confirm is shadowed by the custom confirm() helper above.

})();
