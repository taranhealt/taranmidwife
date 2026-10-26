(function () {
  'use strict';
  var API = '/api/v1';
  var cfg = document.getElementById('cfg').dataset;
  var PILOT = { lat: parseFloat(cfg.lat), lng: parseFloat(cfg.lng), name: cfg.name };
  var KEY = 'tm_partner_token';
  var HOURS = [8, 10, 12, 14, 16];
  var DAYS = [{ d: 1, n: 'Sen' }, { d: 2, n: 'Sel' }, { d: 3, n: 'Rab' }, { d: 4, n: 'Kam' }, { d: 5, n: 'Jum' }, { d: 6, n: 'Sab' }, { d: 0, n: 'Min' }];
  var VSTATUS = {
    DRAFT: 'Draf (belum diajukan)', SUBMITTED: 'Diajukan', IN_REVIEW: 'Sedang ditinjau', NEEDS_CORRECTION: 'Perlu perbaikan',
    APPROVED: 'Disetujui, menunggu aktivasi', ACTIVE: 'Aktif', SUSPENDED: 'Ditangguhkan', EXPIRED: 'Kedaluwarsa', REJECTED: 'Ditolak'
  };
  var BSTATUS = {
    REQUESTED: 'Menunggu pemeriksaan', NEEDS_INFO: 'Perlu informasi', WAITING_MIDWIFE: 'Mencari bidan', OFFERED: 'Menunggu jawaban Anda',
    CONFIRMED: 'Dikonfirmasi', IN_PROGRESS: 'Berlangsung', COMPLETED: 'Selesai', CANCELLED: 'Dibatalkan', UNFULFILLED: 'Tidak terpenuhi'
  };
  var token = null;
  try { token = sessionStorage.getItem(KEY); } catch (e) { token = null; }
  var map = null, marker = null, circle = null, point = null, poll = null, settingsReady = false;

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
    try { if (t) { sessionStorage.setItem(KEY, t); } else { sessionStorage.removeItem(KEY); } } catch (e) { /* abaikan */ }
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
        if (res.status === 401 && token) { leaveApp('Sesi berakhir. Silakan masuk lagi.'); }
        return { ok: res.ok, status: res.status, json: j };
      });
    }, function () {
      return { ok: false, status: 0, json: { message: 'Tidak dapat terhubung ke server.' } };
    });
  }
  function listOf(j) {
    if (Array.isArray(j)) { return j; }
    var d = j && j.data;
    if (Array.isArray(d)) { return d; }
    if (d && Array.isArray(d.data)) { return d.data; }
    return [];
  }
  function rupiah(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }
  function wib(s) {
    try {
      return new Date(s).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short' }) + ' WIB';
    } catch (e) { return s; }
  }
  function wibTime(s) {
    try {
      return new Date(s).toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit' }) + ' WIB';
    } catch (e) { return s; }
  }

  function enterApp() {
    $('auth').hidden = true;
    $('app').hidden = false;
    $('logout').hidden = false;
    msg('');
    loadStatus();
    loadSettings();
    loadBookings();
    initMap();
    startPoll();
  }
  function leaveApp(text) {
    saveToken(null);
    stopPoll();
    settingsReady = false;
    $('save').disabled = true;
    $('app').hidden = true;
    $('auth').hidden = false;
    $('logout').hidden = true;
    msg(text || '', text ? 'err' : '');
  }
  function startPoll() {
    stopPoll();
    poll = setInterval(function () {
      if (document.visibilityState === 'visible' && token) { loadBookings(); }
    }, 20000);
  }
  function stopPoll() { if (poll) { clearInterval(poll); poll = null; } }

  function loadStatus() {
    api('/midwife/profile').then(function (r) {
      if (!token) { return; }
      var box = $('status');
      box.textContent = '';
      if (!r.ok) { box.appendChild(el('p', 'small', errText(r))); return; }
      var d = r.json.data || {};
      var s = d.verification_status;
      box.appendChild(el('p', '', 'Status verifikasi: ' + (VSTATUS[s] || s)));
      if (s === 'NEEDS_CORRECTION' && d.review_reason) {
        box.appendChild(el('p', 'small', 'Catatan peninjau: ' + d.review_reason));
      }
      if (s !== 'ACTIVE') {
        box.appendChild(el('p', 'note', 'Anda belum dapat menerima tawaran sebelum akun aktif. Pengajuan dan unggah dokumen verifikasi belum tersedia di halaman ini.'));
      }
    });
  }

  function checkboxList(box, items, selected, label) {
    box.textContent = '';
    if (!items.length) { box.appendChild(el('p', 'small', 'Belum ada data.')); return; }
    items.forEach(function (it) {
      var l = el('label', 'chk');
      var i = document.createElement('input');
      i.type = 'checkbox';
      i.value = it.id;
      i.checked = selected.indexOf(it.id) !== -1;
      l.appendChild(i);
      l.appendChild(el('span', '', label(it)));
      box.appendChild(l);
    });
  }
  function checkedValues(box) {
    return [].map.call(box.querySelectorAll('input:checked'), function (i) { return i.value; });
  }
  function renderAvail(avail) {
    var t = $('avail');
    t.textContent = '';
    var head = el('tr');
    head.appendChild(el('th', '', 'Hari'));
    HOURS.forEach(function (h) { head.appendChild(el('th', '', ('0' + h).slice(-2) + ':00')); });
    t.appendChild(head);
    DAYS.forEach(function (day) {
      var row = el('tr');
      row.appendChild(el('th', '', day.n));
      var entry = null;
      (avail || []).forEach(function (a) { if (a.weekday === day.d) { entry = a; } });
      HOURS.forEach(function (h) {
        var td = el('td');
        var i = document.createElement('input');
        i.type = 'checkbox';
        i.setAttribute('data-d', String(day.d));
        i.setAttribute('data-h', String(h));
        i.setAttribute('aria-label', day.n + ' ' + h + ':00');
        i.checked = !!(entry && entry.hours.indexOf(h) !== -1);
        td.appendChild(i);
        row.appendChild(td);
      });
      t.appendChild(row);
    });
  }
  function collectAvail() {
    var out = [];
    DAYS.forEach(function (day) {
      var hours = HOURS.filter(function (h) {
        var i = $('avail').querySelector('input[data-d="' + day.d + '"][data-h="' + h + '"]');
        return i && i.checked;
      });
      if (hours.length) { out.push({ weekday: day.d, hours: hours }); }
    });
    return out;
  }

  function loadSettings() {
    settingsReady = false;
    $('save').disabled = true;
    Promise.all([api('/services'), api('/areas'), api('/midwife/offerings')]).then(function (res) {
      if (!token) { return; }
      if (!res[0].ok || !res[1].ok || !res[2].ok) {
        msg('Pengaturan gagal dimuat. Muat ulang halaman sebelum menyimpan.', 'err');
        return;
      }
      var o = res[2].json.data || {};
      $('accepting').checked = !!o.accepting_requests;
      checkboxList($('services'), listOf(res[0].json), o.service_ids || [], function (s) { return s.name; });
      checkboxList($('areas'), listOf(res[1].json), o.area_ids || [], function (a) { return a.name; });
      renderAvail(o.availability || []);
      if (o.service_point) {
        $('radius').value = o.service_point.radius_km;
        setPoint(o.service_point.lat, o.service_point.lng);
        if (map) { map.setView([o.service_point.lat, o.service_point.lng], 12); }
      } else {
        clearPoint();
      }
      settingsReady = true;
      $('save').disabled = false;
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
    if (point) { setPoint(point.lat, point.lng); }
  }
  function drawCircle() {
    if (!map) { return; }
    if (circle) { map.removeLayer(circle); circle = null; }
    if (!point) { return; }
    var r = parseInt($('radius').value, 10);
    if (isNaN(r) || r < 1 || r > 100) { return; }
    circle = window.L.circle([point.lat, point.lng], { radius: r * 1000, weight: 1 }).addTo(map);
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
      drawCircle();
    }
  }
  function clearPoint() {
    point = null;
    $('lat').value = '';
    $('lng').value = '';
    if (map) {
      if (marker) { map.removeLayer(marker); marker = null; }
      if (circle) { map.removeLayer(circle); circle = null; }
    }
  }
  function useGps() {
    if (!navigator.geolocation) {
      msg('Peramban tidak mendukung GPS. Ketuk peta atau isi koordinat manual.', 'err');
      return;
    }
    msg('Mengambil lokasi...', '');
    navigator.geolocation.getCurrentPosition(function (p) {
      msg('');
      setPoint(p.coords.latitude, p.coords.longitude);
      if (map) { map.setView([point.lat, point.lng], 14); }
    }, function (err) {
      msg('Lokasi tidak dapat diambil (' + err.message + '). Ketuk peta atau isi koordinat manual.', 'err');
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
    if (map) { map.setView([la, ln], 14); }
  }

  $('offer-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!settingsReady) { msg('Pengaturan belum selesai dimuat. Muat ulang halaman.', 'err'); return; }
    var body = {
      service_ids: checkedValues($('services')),
      area_ids: checkedValues($('areas')),
      availability: collectAvail(),
      accepting_requests: $('accepting').checked
    };
    if (point) {
      var rad = parseInt($('radius').value, 10);
      if (isNaN(rad) || rad < 1 || rad > 100) { msg('Radius harus 1 sampai 100 km.', 'err'); return; }
      body.service_point = { lat: point.lat, lng: point.lng, radius_km: rad };
    } else {
      body.service_point = null;
    }
    $('save').disabled = true;
    api('/midwife/offerings', { method: 'PUT', body: body }).then(function (r) {
      $('save').disabled = false;
      if (r.ok) { msg('Pengaturan disimpan.', 'ok'); } else { msg(errText(r), 'err'); }
    });
  });

  function card(b) {
    var c = el('div', 'card');
    var top = el('div', 'top');
    top.appendChild(el('strong', '', b.code));
    top.appendChild(el('span', 'chip', BSTATUS[b.status] || b.status));
    c.appendChild(top);
    c.appendChild(el('div', '', (b.service || '') + (b.area ? ' - ' + b.area : '')));
    c.appendChild(el('div', 'small', wib(b.scheduled_start) + ' | ' + rupiah(b.price_amount)));
    if (typeof b.distance_km === 'number') {
      c.appendChild(el('div', 'small', 'Jarak perkiraan dari titik layanan Anda: ' + b.distance_km + ' km'));
    }
    return c;
  }
  function button(label, cls, fn) {
    var btn = el('button', cls, label);
    btn.type = 'button';
    btn.addEventListener('click', function () { fn(btn); });
    return btn;
  }
  function actions(c, list) {
    var row = el('div', 'row');
    list.forEach(function (b) { row.appendChild(b); });
    c.appendChild(row);
  }
  function act(id, action, okText, btn) {
    if (btn) { btn.disabled = true; }
    api('/midwife/bookings/' + encodeURIComponent(id) + '/' + action, { method: 'POST', body: {} }).then(function (r) {
      if (r.ok) { msg(okText, 'ok'); } else { msg(errText(r), 'err'); }
      loadBookings();
    });
  }

  function loadBookings() {
    api('/bookings?per_page=50').then(function (r) {
      if (!token) { return; }
      var offers = $('offers'), activeBox = $('active'), history = $('history');
      if (!r.ok) {
        offers.textContent = '';
        offers.appendChild(el('p', 'small', errText(r)));
        return;
      }
      offers.textContent = '';
      activeBox.textContent = '';
      history.textContent = '';
      var items = listOf(r.json);
      var nOff = 0, nAct = 0, nHis = 0;
      items.forEach(function (b) {
        var c = card(b);
        if (b.status === 'OFFERED') {
          nOff++;
          var expired = false;
          if (b.offer_expires_at) {
            expired = new Date(b.offer_expires_at).getTime() < Date.now();
            c.appendChild(el('div', 'small', (expired ? 'Batas waktu terlewati: ' : 'Jawab sebelum ') + wibTime(b.offer_expires_at)));
          }
          var acc = button('Terima', '', function (btn) { act(b.id, 'accept', 'Tawaran diterima.', btn); });
          acc.disabled = expired;
          var dec = button('Tolak', 'secondary', function (btn) {
            if (window.confirm('Tolak tawaran ini?')) { act(b.id, 'decline', 'Tawaran ditolak.', btn); }
          });
          actions(c, [acc, dec]);
          offers.appendChild(c);
        } else if (b.status === 'CONFIRMED' || b.status === 'IN_PROGRESS') {
          nAct++;
          if (b.address_line) { c.appendChild(el('div', '', b.address_line)); }
          if (b.landmark) { c.appendChild(el('div', 'small', 'Patokan: ' + b.landmark)); }
          if (b.contact_phone) {
            var p = el('div', 'small', 'Telepon: ');
            if (/^\+?[0-9]{8,15}$/.test(b.contact_phone)) {
              var a = el('a', 'tel', b.contact_phone);
              a.href = 'tel:' + b.contact_phone;
              p.appendChild(a);
            } else {
              p.appendChild(document.createTextNode(b.contact_phone));
            }
            c.appendChild(p);
          }
          if (b.status === 'CONFIRMED') {
            actions(c, [button('Mulai kunjungan', '', function (btn) { act(b.id, 'start', 'Kunjungan dimulai.', btn); })]);
          } else {
            actions(c, [button('Selesaikan kunjungan', '', function (btn) {
              if (window.confirm('Tandai kunjungan ini selesai?')) { act(b.id, 'complete', 'Kunjungan selesai.', btn); }
            })]);
          }
          activeBox.appendChild(c);
        } else {
          nHis++;
          history.appendChild(c);
        }
      });
      if (!nOff) { offers.appendChild(el('p', 'small', 'Tidak ada tawaran.')); }
      if (!nAct) { activeBox.appendChild(el('p', 'small', 'Tidak ada kunjungan aktif.')); }
      if (!nHis) { history.appendChild(el('p', 'small', 'Belum ada riwayat.')); }
    });
  }

  function afterAuth(r) {
    var t = r.json && r.json.token;
    var role = r.json && r.json.user && r.json.user.role;
    if (!t) { msg('Respons tidak berisi token. Hubungi pengembang.', 'err'); return; }
    if (role !== 'partner') {
      token = t;
      api('/auth/logout', { method: 'POST', body: {} }).then(function () {
        saveToken(null);
        msg('Akun ini bukan akun mitra. Pasien memakai halaman /app.', 'err');
      });
      return;
    }
    saveToken(t);
    enterApp();
  }
  $('login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    api('/auth/login', { method: 'POST', body: { email: $('l-email').value.trim(), password: $('l-pass').value } }).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      $('l-pass').value = '';
      afterAuth(r);
    });
  });
  $('register-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var body = { name: $('r-name').value.trim(), email: $('r-email').value.trim(), password: $('r-pass').value, password_confirmation: $('r-pass').value, role: 'partner' };
    api('/auth/register', { method: 'POST', body: body }).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      $('r-pass').value = '';
      afterAuth(r);
    });
  });
  $('show-register').addEventListener('click', function () { $('login-form').hidden = true; $('register-form').hidden = false; msg(''); });
  $('show-login').addEventListener('click', function () { $('register-form').hidden = true; $('login-form').hidden = false; msg(''); });
  $('logout').addEventListener('click', function () {
    api('/auth/logout', { method: 'POST', body: {} }).then(function () { leaveApp(''); });
  });
  $('gps').addEventListener('click', useGps);
  $('clear-point').addEventListener('click', clearPoint);
  $('apply-coords').addEventListener('click', applyManual);
  $('radius').addEventListener('input', drawCircle);
  $('refresh').addEventListener('click', function () { loadStatus(); loadBookings(); });

  if (token) {
    api('/me').then(function (r) {
      if (r.ok && r.json.user && r.json.user.role === 'partner') { enterApp(); } else { saveToken(null); }
    });
  }
})();
