(function () {
  'use strict';
  var API = '/api/v1';
  var cfg = document.getElementById('cfg').dataset;
  var PILOT = { lat: parseFloat(cfg.lat), lng: parseFloat(cfg.lng), name: cfg.name };
  var STATUS = {
    REQUESTED: 'Menunggu pemeriksaan', NEEDS_INFO: 'Perlu informasi tambahan', WAITING_MIDWIFE: 'Mencari bidan',
    OFFERED: 'Menunggu jawaban bidan', CONFIRMED: 'Dikonfirmasi', IN_PROGRESS: 'Berlangsung', COMPLETED: 'Selesai',
    CANCELLED: 'Dibatalkan', UNFULFILLED: 'Belum ada bidan tersedia'
  };
  var CANCELABLE = ['REQUESTED', 'NEEDS_INFO', 'WAITING_MIDWIFE', 'OFFERED', 'CONFIRMED'];
  var token = null;
  try { token = sessionStorage.getItem('tm_token'); } catch (e) { token = null; }
  var map = null, marker = null, point = null, pendingKey = null, covTimer = null, covSeq = 0, poll = null;

  function $(id) { return document.getElementById(id); }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
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
  function saveToken(t) {
    token = t;
    try { if (t) { sessionStorage.setItem('tm_token', t); } else { sessionStorage.removeItem('tm_token'); } } catch (e) { /* abaikan */ }
  }
  function api(path, opt) {
    opt = opt || {};
    var h = { 'Accept': 'application/json' };
    if (opt.body !== undefined) { h['Content-Type'] = 'application/json'; }
    if (token) { h['Authorization'] = 'Bearer ' + token; }
    if (opt.headers) { Object.keys(opt.headers).forEach(function (k) { h[k] = opt.headers[k]; }); }
    return fetch(API + path, {
      method: opt.method || 'GET', headers: h,
      body: opt.body !== undefined ? JSON.stringify(opt.body) : undefined
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (j) {
        if (res.status === 401 && token) { leaveApp('Sesi berakhir. Silakan masuk lagi.'); }
        return { ok: res.ok, status: res.status, json: j };
      });
    }, function () {
      return { ok: false, status: 0, json: { message: 'Tidak dapat terhubung ke server.' } };
    });
  }
  function listOf(j) {
    var d = j && j.data;
    if (Array.isArray(d)) { return d; }
    if (d && Array.isArray(d.data)) { return d.data; }
    return [];
  }
  function pickToken(j) {
    return (j && (j.token || (j.data && (j.data.token || j.data.access_token)) || j.access_token)) || null;
  }
  function newKey() {
    if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
    return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
  }
  function rupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
  function wib(s) {
    try {
      return new Date(s).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short' }) + ' WIB';
    } catch (e) { return s; }
  }

  function enterApp() {
    $('auth').hidden = true;
    $('app').hidden = false;
    $('logout').hidden = false;
    msg('');
    loadCatalog();
    loadBookings();
    initMap();
    startPoll();
  }
  function leaveApp(text) {
    saveToken(null);
    stopPoll();
    $('app').hidden = true;
    $('auth').hidden = false;
    $('logout').hidden = true;
    msg(text || '', text ? 'err' : '');
  }
  function startPoll() {
    stopPoll();
    poll = setInterval(function () {
      if (document.visibilityState === 'visible' && token) { loadBookings(); }
    }, 30000);
  }
  function stopPoll() { if (poll) { clearInterval(poll); poll = null; } }

  function fillSelect(sel, items, label) {
    sel.textContent = '';
    items.forEach(function (it) {
      var o = el('option', '', label(it));
      o.value = it.id;
      sel.appendChild(o);
    });
  }
  function loadCatalog() {
    Promise.all([api('/services'), api('/areas')]).then(function (res) {
      if (!res[0].ok || !res[1].ok) { msg('Daftar layanan atau wilayah gagal dimuat.', 'err'); return; }
      fillSelect($('service'), listOf(res[0].json), function (s) { return s.name + ' - ' + rupiah(s.price_amount); });
      fillSelect($('area'), listOf(res[1].json), function (a) { return a.name; });
      checkCoverage();
    });
  }

  function initMap() {
    if (map) { setTimeout(function () { map.invalidateSize(); }, 100); return; }
    if (!window.L) { $('map-note').hidden = false; return; }
    map = window.L.map('map').setView([PILOT.lat, PILOT.lng], 11);
    window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    map.on('click', function (e) { setPoint(e.latlng.lat, e.latlng.lng); });
    setTimeout(function () { map.invalidateSize(); }, 200);
  }
  function setPoint(lat, lng) {
    lat = Math.round(lat * 1e6) / 1e6;
    lng = Math.round(lng * 1e6) / 1e6;
    point = { lat: lat, lng: lng };
    $('lat').value = lat;
    $('lng').value = lng;
    if (map) {
      if (!marker) {
        marker = window.L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function () { var p = marker.getLatLng(); setPoint(p.lat, p.lng); });
      } else {
        marker.setLatLng([lat, lng]);
      }
      map.panTo([lat, lng]);
    }
    scheduleCoverage();
  }
  function useGps() {
    if (!navigator.geolocation) {
      msg('Peramban tidak mendukung GPS. Geser pin di peta atau isi koordinat manual.', 'err');
      return;
    }
    msg('Mengambil lokasi...', '');
    navigator.geolocation.getCurrentPosition(function (p) {
      msg('');
      setPoint(p.coords.latitude, p.coords.longitude);
      if (map) { map.setView([point.lat, point.lng], 15); }
    }, function (err) {
      msg('Lokasi tidak dapat diambil (' + err.message + '). Geser pin di peta atau isi koordinat manual.', 'err');
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  }
  function applyManual() {
    var la = parseFloat($('lat').value), ln = parseFloat($('lng').value);
    if (isNaN(la) || isNaN(ln) || la < -90 || la > 90 || ln < -180 || ln > 180) {
      msg('Koordinat tidak valid.', 'err');
      return;
    }
    msg('');
    setPoint(la, ln);
    if (map) { map.setView([la, ln], 15); }
  }

  function scheduleCoverage() { clearTimeout(covTimer); covTimer = setTimeout(checkCoverage, 400); }
  function checkCoverage() {
    var box = $('coverage');
    if (!point || !token) { box.hidden = true; return; }
    var seq = ++covSeq;
    var q = '?lat=' + point.lat + '&lng=' + point.lng;
    var sid = $('service').value;
    if (sid) { q += '&service_id=' + encodeURIComponent(sid); }
    api('/coverage' + q).then(function (r) {
      if (seq !== covSeq) { return; }
      box.hidden = false;
      box.className = 'cov';
      if (!r.ok) { box.className = 'cov warn'; box.textContent = errText(r); return; }
      var d = r.json.data || {};
      if (!d.in_pilot_area) {
        box.className = 'cov warn';
        box.textContent = 'Lokasi ini di luar area layanan pilot (' + (d.pilot_name || PILOT.name) + '). Pesanan tidak dapat dibuat.';
      } else if (d.serviceable) {
        box.textContent = 'Lokasi dalam area pilot. Ada ' + d.partners_nearby + ' bidan aktif yang menjangkau lokasi ini. Ketersediaan jadwal dicek saat pesanan diproses.';
      } else {
        box.className = 'cov warn';
        box.textContent = 'Lokasi dalam area pilot, tetapi belum ada bidan aktif yang menjangkau lokasi ini. Pesanan tetap dapat diajukan, namun belum tentu dapat dipenuhi.';
      }
    });
  }

  function loadBookings() {
    api('/bookings').then(function (r) {
      var box = $('bookings');
      if (!r.ok) { if (r.status !== 401) { box.textContent = ''; box.appendChild(el('p', 'small', errText(r))); } return; }
      var items = listOf(r.json);
      box.textContent = '';
      if (!items.length) { box.appendChild(el('p', 'small', 'Belum ada pesanan.')); return; }
      items.forEach(function (b) {
        var c = el('div', 'card');
        var top = el('div', 'top');
        top.appendChild(el('strong', '', b.code));
        top.appendChild(el('span', 'chip', STATUS[b.status] || b.status));
        c.appendChild(top);
        c.appendChild(el('div', '', (b.service || '') + (b.area ? ' - ' + b.area : '')));
        c.appendChild(el('div', 'small', wib(b.scheduled_start) + ' | ' + rupiah(b.price_amount)));
        if (b.status === 'REQUESTED') {
          c.appendChild(el('div', 'small', 'Jadwal belum final sampai dikonfirmasi.'));
        }
        if (CANCELABLE.indexOf(b.status) !== -1) {
          var btn = el('button', 'secondary', 'Batalkan');
          btn.type = 'button';
          btn.style.marginTop = '8px';
          btn.addEventListener('click', function () { cancelBooking(b.id); });
          c.appendChild(btn);
        }
        box.appendChild(c);
      });
    });
  }
  function cancelBooking(id) {
    if (!window.confirm('Batalkan pesanan ini?')) { return; }
    api('/bookings/' + encodeURIComponent(id) + '/cancel', { method: 'POST', body: {} }).then(function (r) {
      if (r.ok) { msg('Pesanan dibatalkan.', 'ok'); loadBookings(); } else { msg(errText(r), 'err'); }
    });
  }

  $('login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    api('/auth/login', { method: 'POST', body: { email: $('l-email').value.trim(), password: $('l-pass').value } }).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      var t = pickToken(r.json);
      if (!t) { msg('Respons masuk tidak berisi token. Hubungi pengembang.', 'err'); return; }
      $('l-pass').value = '';
      saveToken(t);
      enterApp();
    });
  });
  $('register-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var body = { name: $('r-name').value.trim(), email: $('r-email').value.trim(), password: $('r-pass').value, password_confirmation: $('r-pass').value };
    api('/auth/register', { method: 'POST', body: body }).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      $('r-pass').value = '';
      var t = pickToken(r.json);
      if (t) { saveToken(t); enterApp(); return; }
      msg('Pendaftaran berhasil. Silakan masuk.', 'ok');
      $('register-form').hidden = true;
      $('login-form').hidden = false;
    });
  });
  $('show-register').addEventListener('click', function () { $('login-form').hidden = true; $('register-form').hidden = false; msg(''); });
  $('show-login').addEventListener('click', function () { $('register-form').hidden = true; $('login-form').hidden = false; msg(''); });
  $('logout').addEventListener('click', function () {
    api('/auth/logout', { method: 'POST', body: {} }).then(function () { leaveApp(''); });
  });
  $('gps').addEventListener('click', useGps);
  $('apply-coords').addEventListener('click', applyManual);
  $('service').addEventListener('change', checkCoverage);
  $('refresh').addEventListener('click', loadBookings);

  $('booking-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var date = $('date').value;
    if (!date) { msg('Pilih tanggal kunjungan.', 'err'); return; }
    var hh = ('0' + $('hour').value).slice(-2);
    var body = {
      service_id: $('service').value, area_id: $('area').value,
      scheduled_start: date + 'T' + hh + ':00:00+07:00',
      address_line: $('address').value.trim(), contact_phone: $('phone').value.trim()
    };
    var lm = $('landmark').value.trim();
    if (lm) { body.landmark = lm; }
    if (point) { body.latitude = point.lat; body.longitude = point.lng; }
    if (!pendingKey) { pendingKey = newKey(); }
    var btn = $('submit');
    btn.disabled = true;
    api('/bookings', { method: 'POST', body: body, headers: { 'Idempotency-Key': pendingKey } }).then(function (r) {
      btn.disabled = false;
      if (r.ok) {
        pendingKey = null;
        msg(r.status === 200 ? 'Permintaan ini sudah tercatat sebelumnya.' : (r.json.notice || 'Permintaan diterima.'), 'ok');
        loadBookings();
      } else {
        if (r.status >= 400 && r.status < 500) { pendingKey = null; }
        msg(errText(r), 'err');
      }
    });
  });

  (function initDate() {
    var d = new Date();
    var m = ('0' + (d.getMonth() + 1)).slice(-2), day = ('0' + d.getDate()).slice(-2);
    $('date').min = d.getFullYear() + '-' + m + '-' + day;
    [8, 10, 12, 14, 16].forEach(function (h) {
      var o = el('option', '', ('0' + h).slice(-2) + ':00 WIB');
      o.value = h;
      $('hour').appendChild(o);
    });
  })();

  if (token) {
    api('/me').then(function (r) { if (r.ok) { enterApp(); } else { saveToken(null); } });
  }
})();
