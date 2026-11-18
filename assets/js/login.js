/**
 * login.js — signs the user in via api/login.php
 */

function showError(msg) {
  var el = document.getElementById('login-error');
  el.textContent = msg;
  el.style.display = msg ? '' : 'none';
}

function setLoading(loading) {
  var btn = document.querySelector('#login-form button[type="submit"]');
  btn.disabled    = loading;
  btn.textContent = loading ? 'Signing in…' : 'Sign in';
}

document.getElementById('login-form').addEventListener('submit', function (e) {
  e.preventDefault();
  showError('');

  var email    = document.getElementById('email').value.trim().toLowerCase();
  var password = document.getElementById('password').value;

  if (!email || !password) {
    showError('Email and password are required.');
    return;
  }

  setLoading(true);

  fetch('api/login.php', {
    method:      'POST',
    credentials: 'same-origin',
    headers:     { 'Content-Type': 'application/json' },
    body:        JSON.stringify({ email: email, password: password }),
  })
    .then(function (res) {
      return res.text().then(function (text) {
        try {
          var d = JSON.parse(text);
          d._status = res.status;
          return d;
        } catch (err) {
          throw new Error('HTTP ' + res.status + ': ' + text.slice(0, 300));
        }
      });
    })
    .then(function (data) {
      if (!data.ok) {
        showError(data.error || 'Incorrect email or password.');
        setLoading(false);
        return;
      }

      localStorage.setItem('agapalert_user', JSON.stringify(data.user));

      if (data.user.must_change_password == 1 || data.user.must_change_password === true) {
        localStorage.setItem('agapalert_flash', JSON.stringify({
          type:    'warning',
          message: 'Your password was reset by an administrator. Please set a new password now.',
        }));
        window.location.href = 'profile.html?force_password=1';
        return;
      }

      var msg = data.user.role === 'admin'
        ? 'Signed in as administrator. Welcome back.'
        : 'Signed in. Welcome back to AgapAlert.';
      localStorage.setItem('agapalert_flash', JSON.stringify({ type: 'success', message: msg }));

      window.location.href = 'index.html';
    })
    .catch(function (err) {
      showError(err.message || 'Could not reach the server. Check your connection.');
      setLoading(false);
    });
});
