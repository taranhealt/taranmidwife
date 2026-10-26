(function () {
  var form = document.getElementById('form');
  var msg = document.getElementById('msg');
  var btn = document.getElementById('submit');
  function show(text, isErr) {
    msg.textContent = text;
    msg.className = isErr ? 'msg err' : 'msg';
    msg.hidden = false;
  }
  function firstError(j) {
    if (j && j.errors) { var k = Object.keys(j.errors)[0]; if (k) { return j.errors[k][0]; } }
    return (j && j.message) || 'Terjadi kesalahan.';
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    msg.hidden = true;
    btn.disabled = true;
    fetch('/api/v1/auth/forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ email: document.getElementById('email').value })
    }).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, j: j }; });
    }).then(function (x) {
      show(x.ok ? x.j.message : firstError(x.j), !x.ok);
    }).catch(function () {
      show('Tidak dapat terhubung ke server.', true);
    }).then(function () { btn.disabled = false; });
  });
})();
