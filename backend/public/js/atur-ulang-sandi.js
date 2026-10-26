(function () {
  var form = document.getElementById('form');
  var msg = document.getElementById('msg');
  var btn = document.getElementById('submit');
  var params = new URLSearchParams(window.location.search);
  var token = params.get('token');
  var email = params.get('email');
  function show(text, isErr) {
    msg.textContent = text;
    msg.className = isErr ? 'msg err' : 'msg';
    msg.hidden = false;
  }
  function firstError(j) {
    if (j && j.errors) { var k = Object.keys(j.errors)[0]; if (k) { return j.errors[k][0]; } }
    return (j && j.message) || 'Terjadi kesalahan.';
  }
  if (!token || !email) {
    show('Tautan tidak lengkap. Minta tautan baru dari halaman lupa kata sandi.', true);
    return;
  }
  form.hidden = false;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    msg.hidden = true;
    var p1 = document.getElementById('password').value;
    var p2 = document.getElementById('password2').value;
    if (p1 !== p2) { show('Konfirmasi kata sandi tidak sama.', true); return; }
    btn.disabled = true;
    fetch('/api/v1/auth/reset-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ email: email, token: token, password: p1, password_confirmation: p2 })
    }).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, j: j }; });
    }).then(function (x) {
      if (x.ok) { form.hidden = true; }
      show(x.ok ? x.j.message : firstError(x.j), !x.ok);
    }).catch(function () {
      show('Tidak dapat terhubung ke server.', true);
    }).then(function () { btn.disabled = false; });
  });
})();
