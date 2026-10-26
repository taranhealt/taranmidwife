(function () {
  'use strict';
  var API = '/api/v1';
  var token = null;

  function $(id) { return document.getElementById(id); }
  function msg(text, kind) {
    var m = $('msg');
    m.textContent = text || '';
    m.className = 'msg ' + (kind || '');
    m.hidden = !text;
    if (text) { m.scrollIntoView({ block: 'nearest' }); }
  }
  function errText(r) {
    var j = r.json || {};
    var parts = [];
    if (j.errors) {
      Object.keys(j.errors).forEach(function (k) {
        [].concat(j.errors[k]).forEach(function (x) { parts.push(x); });
      });
    }
    if (!parts.length && j.message) { parts.push(j.message); }
    if (!parts.length) { parts.push('Terjadi kesalahan (kode ' + r.status + ').'); }
    return parts.join(' ');
  }
  function api(path, opt) {
    opt = opt || {};
    var h = { 'Accept': 'application/json' };
    if (opt.body !== undefined) { h['Content-Type'] = 'application/json'; }
    if (token) { h['Authorization'] = 'Bearer ' + token; }
    return fetch(API + path, {
      method: opt.method || 'GET', headers: h,
      body: opt.body !== undefined ? JSON.stringify(opt.body) : undefined
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (j) {
        return { ok: res.ok, status: res.status, json: j };
      });
    }, function () {
      return { ok: false, status: 0, json: { message: 'Tidak dapat terhubung ke server.' } };
    });
  }
  function reset() {
    token = null;
    $('confirm').hidden = true;
    $('auth').hidden = false;
    $('logout').hidden = true;
    $('d-pass').value = '';
    $('d-ok').checked = false;
    $('d-btn').disabled = true;
  }

  $('login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    api('/auth/login', { method: 'POST', body: { email: $('l-email').value.trim(), password: $('l-pass').value } }).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      $('l-pass').value = '';
      var t = r.json && r.json.token;
      var role = r.json && r.json.user && r.json.user.role;
      if (!t) { msg('Respons tidak berisi token. Hubungi pengembang.', 'err'); return; }
      token = t;
      if (role !== 'patient' && role !== 'partner') {
        api('/auth/logout', { method: 'POST', body: {} }).then(function () {
          token = null;
          msg('Akun staf tidak dapat dihapus dari halaman ini. Hubungi administrator sistem.', 'err');
        });
        return;
      }
      msg('');
      $('who').textContent = 'Akun: ' + ((r.json.user && r.json.user.email) || '') + ' (' + (role === 'partner' ? 'mitra' : 'pasien') + ')';
      $('auth').hidden = true;
      $('confirm').hidden = false;
      $('logout').hidden = false;
    });
  });

  $('d-ok').addEventListener('change', function () { $('d-btn').disabled = !$('d-ok').checked; });

  $('delete-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!$('d-ok').checked) { return; }
    if (!window.confirm('Hapus akun secara permanen? Tindakan ini tidak dapat dibatalkan.')) { return; }
    $('d-btn').disabled = true;
    api('/me', { method: 'DELETE', body: { password: $('d-pass').value } }).then(function (r) {
      $('d-pass').value = '';
      if (r.ok) {
        token = null;
        msg('');
        $('confirm').hidden = true;
        $('info').hidden = true;
        $('logout').hidden = true;
        $('done').hidden = false;
        return;
      }
      $('d-btn').disabled = !$('d-ok').checked;
      if (r.status === 409) {
        msg('Akun belum dapat dihapus karena masih ada pesanan yang belum selesai atau dibatalkan. Selesaikan atau batalkan pesanan tersebut lebih dulu.', 'err');
      } else if (r.status === 429) {
        msg('Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.', 'err');
      } else {
        msg(errText(r), 'err');
      }
    });
  });

  $('logout').addEventListener('click', function () {
    api('/auth/logout', { method: 'POST', body: {} }).then(function () { reset(); msg('Anda sudah keluar. Akun belum dihapus.', 'ok'); });
  });
})();
