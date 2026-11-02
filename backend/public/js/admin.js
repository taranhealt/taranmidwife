(function () {
  'use strict';
  var API = '/api/v1';
  var KEY = 'tm_admin_token';
  var STAFF = ['ops_admin', 'reviewer', 'sysadmin'];
  var ROLE_LABEL = { ops_admin: 'Admin operasional', reviewer: 'Reviewer', sysadmin: 'Admin sistem' };
  var BSTATUS = {
    REQUESTED: 'Menunggu pemeriksaan', NEEDS_INFO: 'Perlu informasi', WAITING_MIDWIFE: 'Mencari bidan', OFFERED: 'Ditawarkan ke mitra',
    CONFIRMED: 'Dikonfirmasi', IN_PROGRESS: 'Berlangsung', COMPLETED: 'Selesai', CANCELLED: 'Dibatalkan', UNFULFILLED: 'Tidak terpenuhi'
  };
  var VSTATUS = {
    DRAFT: 'Draf', SUBMITTED: 'Diajukan', IN_REVIEW: 'Sedang ditinjau', NEEDS_CORRECTION: 'Perlu perbaikan',
    APPROVED: 'Disetujui, menunggu aktivasi', ACTIVE: 'Aktif', SUSPENDED: 'Ditangguhkan', EXPIRED: 'Kedaluwarsa', REJECTED: 'Ditolak'
  };
  var CANCELABLE = ['REQUESTED', 'NEEDS_INFO', 'WAITING_MIDWIFE', 'OFFERED', 'CONFIRMED'];
  var ITEMS = [['IDENTITY', 'Identitas'], ['PROFESSIONAL_STATUS', 'Status profesional'], ['IHS', 'IHS'], ['SIP', 'SIP'], ['FACE_PHOTO', 'Foto wajah']];
  var RESULTS = [['verified', 'Terverifikasi'], ['unverified_limited', 'Tidak dapat diverifikasi penuh (keterbatasan)'], ['failed', 'Gagal']];
  var TABS = {
    ops_admin: [['orders', 'Pesanan'], ['complaints', 'Pengaduan']],
    reviewer: [['partners', 'Mitra'], ['complaints', 'Pengaduan']],
    sysadmin: [['partners', 'Mitra'], ['orders', 'Pesanan'], ['complaints', 'Pengaduan']]
  };
  var PACTIONS = [
    { s: ['SUBMITTED'], a: 'start-review', t: 'Mulai tinjau', r: ['reviewer', 'sysadmin'], reason: false, ok: 'Peninjauan dimulai.' },
    { s: ['IN_REVIEW'], a: 'request-correction', t: 'Minta perbaikan', r: ['reviewer', 'sysadmin'], reason: true, ok: 'Perbaikan diminta.' },
    { s: ['IN_REVIEW'], a: 'approve', t: 'Setujui', r: ['sysadmin'], reason: false, ok: 'Mitra disetujui.' },
    { s: ['IN_REVIEW', 'APPROVED'], a: 'reject', t: 'Tolak', r: ['sysadmin'], reason: true, ok: 'Mitra ditolak.' },
    { s: ['APPROVED', 'SUSPENDED'], a: 'activate', t: 'Aktifkan', r: ['sysadmin'], reason: false, ok: 'Mitra diaktifkan.' },
    { s: ['ACTIVE'], a: 'suspend', t: 'Tangguhkan', r: ['sysadmin'], reason: true, ok: 'Mitra ditangguhkan.' }
  ];
  var token = null, role = null, pcache = [], docUrl = null;
  try { token = sessionStorage.getItem(KEY); } catch (e) { token = null; }

  function $(id) { return document.getElementById(id); }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
  function mkBtn(text, cls, fn) {
    var b = el('button', cls || '', text);
    b.type = 'button';
    b.addEventListener('click', fn);
    return b;
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

  function leaveApp(text) {
    saveToken(null);
    role = null;
    $('app').hidden = true;
    $('auth').hidden = false;
    $('logout').hidden = true;
    $('who').textContent = '';
    msg(text || '', text ? 'err' : '');
  }
  function enterApp(user) {
    role = user.role;
    $('auth').hidden = true;
    $('app').hidden = false;
    $('logout').hidden = false;
    $('who').textContent = user.name + ' - ' + (ROLE_LABEL[role] || role);
    var box = $('tabs');
    box.textContent = '';
    TABS[role].forEach(function (t) {
      var b = mkBtn(t[1], 'secondary', function () { showTab(t[0]); });
      b.dataset.tab = t[0];
      box.appendChild(b);
    });
    loadMetrics();
    showTab(TABS[role][0][0]);
  }
  function loadMetrics() {
    var box = $('metrics');
    box.textContent = 'Memuat ringkasan...';
    api('/admin/metrics').then(function (r) {
      box.textContent = '';
      if (!r.ok || !r.json || !r.json.data) {
        box.appendChild(el('p', 'note', 'Ringkasan tidak tersedia saat ini.'));
        return;
      }
      var d = r.json.data;
      [
        ['Total pesanan', d.bookings_total],
        ['Pesanan berjalan', d.bookings_open],
        ['Mitra aktif', d.active_partners],
        ['Pengaduan terbuka', d.complaints_open]
      ].forEach(function (item) {
        var card = el('div', 'card');
        card.appendChild(el('div', 'small', item[0]));
        card.appendChild(el('strong', null, String(item[1])));
        box.appendChild(card);
      });
    });
  }

  function showTab(t) {
    msg('');
    [].forEach.call($('tabs').children, function (b) { b.className = b.dataset.tab === t ? 'active' : 'secondary'; });
    ['orders', 'partners', 'complaints'].forEach(function (p) { $('p-' + p).hidden = (p !== t); });
    if (t === 'orders') { loadOrders(); }
    if (t === 'partners') { loadPartners(); }
    if (t === 'complaints') { loadComplaints(); }
  }

  /* ---------- Pesanan ---------- */
  function post(path, body, okText) {
    return api(path, { method: 'POST', body: body || {} }).then(function (r) {
      if (r.ok) { msg(okText, 'ok'); } else { msg(errText(r), 'err'); }
      return r;
    });
  }
  function loadOrders() {
    var st = $('o-status').value;
    var box = $('orders');
    box.textContent = 'Memuat...';
    api('/bookings?per_page=50' + (st ? '&status=' + encodeURIComponent(st) : '')).then(function (r) {
      box.textContent = '';
      if (!r.ok) { msg(errText(r), 'err'); return; }
      var items = listOf(r.json);
      if (!items.length) { box.appendChild(el('p', 'note', 'Tidak ada pesanan pada filter ini.')); return; }
      items.forEach(function (b) { box.appendChild(orderCard(b)); });
    });
  }
  function orderCard(b) {
    var c = el('div', 'card');
    var top = el('div', 'top');
    top.appendChild(el('strong', null, b.code));
    top.appendChild(el('span', 'chip', BSTATUS[b.status] || b.status));
    c.appendChild(top);
    c.appendChild(el('div', 'small', (b.service || '-') + ' - ' + (b.area || '-')));
    c.appendChild(el('div', 'small', wib(b.scheduled_start) + ' - ' + rupiah(b.price_amount)));
    if (role !== 'ops_admin') {
      c.appendChild(el('div', 'note', 'Hanya lihat. Tindakan pesanan ada pada admin operasional.'));
      return c;
    }
    if (b.address_line) {
      c.appendChild(el('div', 'small', 'Alamat: ' + b.address_line + (b.landmark ? ' (' + b.landmark + ')' : '')));
    }
    if (b.contact_phone) { c.appendChild(el('div', 'small', 'Telepon: ' + b.contact_phone)); }
    var area = el('div');
    var row = el('div', 'row');
    if (b.status === 'REQUESTED') {
      row.appendChild(mkBtn('Saring', '', function () {
        post('/admin/bookings/' + b.id + '/screen', {}, 'Pesanan disaring.').then(function (r) { if (r.ok) { loadOrders(); } });
      }));
    }
    if (b.status === 'REQUESTED' || b.status === 'WAITING_MIDWIFE') {
      row.appendChild(mkBtn('Lihat kandidat', 'secondary', function () { showCandidates(b, area); }));
    }
    if (CANCELABLE.indexOf(b.status) >= 0) {
      row.appendChild(mkBtn('Batalkan', 'secondary', function () { cancelForm(b, area); }));
    }
    c.appendChild(row);
    c.appendChild(area);
    return c;
  }
  function showCandidates(b, area) {
    area.textContent = 'Memuat kandidat...';
    api('/admin/bookings/' + b.id + '/candidates').then(function (r) {
      area.textContent = '';
      if (!r.ok) { msg(errText(r), 'err'); return; }
      var list = listOf(r.json);
      if (!list.length) {
        area.appendChild(el('p', 'note', 'Belum ada mitra layak (layanan, wilayah, jadwal, jarak, atau masa berlaku SIP belum cocok).'));
        return;
      }
      list.forEach(function (p) {
        var k = el('div', 'card');
        k.appendChild(el('strong', null, p.name));
        k.appendChild(el('div', 'small', (p.distance_km !== null && p.distance_km !== undefined ? p.distance_km + ' km dari pasien' : 'Jarak tidak diketahui')
          + (p.last_offered_at ? ' - terakhir ditawari ' + wib(p.last_offered_at) : ' - belum pernah ditawari')));
        k.appendChild(mkBtn('Tugaskan', '', function () {
          post('/admin/bookings/' + b.id + '/assign', { partner_id: p.partner_id }, 'Tawaran dikirim ke ' + p.name + '.')
            .then(function (r2) { if (r2.ok) { loadOrders(); } });
        }));
        area.appendChild(k);
      });
    });
  }
  function cancelForm(b, area) {
    area.textContent = '';
    var inp = el('input');
    inp.type = 'text';
    inp.maxLength = 200;
    inp.placeholder = 'Alasan pembatalan (wajib)';
    area.appendChild(inp);
    area.appendChild(mkBtn('Konfirmasi batal', '', function () {
      var v = inp.value.trim();
      if (!v) { msg('Alasan pembatalan wajib diisi.', 'err'); return; }
      post('/admin/bookings/' + b.id + '/cancel', { reason: v }, 'Pesanan dibatalkan.').then(function (r) { if (r.ok) { loadOrders(); } });
    }));
  }

  /* ---------- Mitra ---------- */
  function loadPartners() {
    var box = $('partners');
    box.textContent = 'Memuat...';
    api('/admin/partners?per_page=50').then(function (r) {
      box.textContent = '';
      if (!r.ok) { msg(errText(r), 'err'); return; }
      pcache = listOf(r.json);
      renderPartnerList();
    });
  }
  function renderPartnerList() {
    var f = $('pa-status').value;
    var box = $('partners');
    box.textContent = '';
    var items = pcache.filter(function (p) { return !f || p.verification_status === f; });
    if (!items.length) { box.appendChild(el('p', 'note', 'Tidak ada mitra pada filter ini.')); return; }
    items.forEach(function (p) {
      var c = el('div', 'card');
      var top = el('div', 'top');
      top.appendChild(el('strong', null, p.name));
      top.appendChild(el('span', 'chip', VSTATUS[p.verification_status] || p.verification_status));
      c.appendChild(top);
      var row = el('div', 'row');
      row.appendChild(mkBtn('Buka', 'secondary', function () { openPartner(p.id); }));
      c.appendChild(row);
      box.appendChild(c);
    });
  }
  function openPartner(id) {
    api('/admin/partners/' + id).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      renderDetail(r.json.data);
      $('partner-detail').scrollIntoView({ block: 'start' });
    });
  }
  function partnerAct(id, action, body, okText) {
    return api('/admin/partners/' + id + '/' + action, { method: 'POST', body: body || {} }).then(function (r) {
      if (r.ok) { msg(okText, 'ok'); renderDetail(r.json.data); loadPartners(); } else { msg(errText(r), 'err'); }
    });
  }
  function openDoc(pid, doc, holder) {
    holder.textContent = 'Membuka...';
    var h = { 'Accept': '*/*' };
    if (token) { h['Authorization'] = 'Bearer ' + token; }
    fetch(API + '/admin/partners/' + pid + '/documents/' + doc.id, { headers: h }).then(function (res) {
      if (!res.ok) { throw new Error('kode ' + res.status); }
      return res.blob();
    }).then(function (blob) {
      if (docUrl) { URL.revokeObjectURL(docUrl); }
      docUrl = URL.createObjectURL(blob);
      holder.textContent = '';
      if (doc.mime && doc.mime.indexOf('image/') === 0) {
        var img = el('img', 'doc');
        img.src = docUrl;
        img.alt = 'Dokumen ' + doc.type;
        holder.appendChild(img);
      } else {
        var a = el('a', null, 'Unduh ' + doc.type);
        a.href = docUrl;
        a.download = doc.type.toLowerCase() + '.pdf';
        holder.appendChild(a);
      }
    }).catch(function (e) {
      holder.textContent = '';
      msg('Dokumen tidak dapat dibuka (' + e.message + ').', 'err');
    });
  }
  function renderDetail(d) {
    var box = $('partner-detail');
    box.textContent = '';
    box.hidden = false;
    box.appendChild(el('h3', null, d.name + ' - ' + (VSTATUS[d.verification_status] || d.verification_status)));
    var dl = el('dl', 'kv');
    [['NIK', d.nik], ['No. SIP', d.sip_number], ['Penerbit SIP', d.sip_issuer], ['SIP berlaku sampai', d.sip_expires_on],
     ['Wilayah praktik', d.practice_area], ['Diajukan', d.submitted_at ? wib(d.submitted_at) : null], ['Catatan peninjau', d.review_reason]
    ].forEach(function (kv) {
      dl.appendChild(el('dt', null, kv[0]));
      dl.appendChild(el('dd', null, kv[1] || '-'));
    });
    box.appendChild(dl);

    box.appendChild(el('h3', null, 'Dokumen'));
    if (!d.documents || !d.documents.length) { box.appendChild(el('p', 'note', 'Belum ada dokumen.')); }
    (d.documents || []).forEach(function (doc) {
      var row = el('div', 'card');
      row.appendChild(el('div', 'small', doc.type + ' - ' + doc.mime + ' - ' + Math.round(doc.size_bytes / 1024) + ' KB'));
      var holder = el('div');
      row.appendChild(mkBtn('Buka dokumen', 'secondary', function () { openDoc(d.id, doc, holder); }));
      row.appendChild(holder);
      box.appendChild(row);
    });

    box.appendChild(el('h3', null, 'Pemeriksaan'));
    var existing = {};
    (d.checks || []).forEach(function (c) { existing[c.item_type] = c; });
    var canCheck = d.verification_status === 'IN_REVIEW' && (role === 'reviewer' || role === 'sysadmin');
    if (canCheck) {
      var form = el('form');
      var fields = {};
      ITEMS.forEach(function (it) {
        var wrap = el('div', 'chk-row');
        wrap.appendChild(el('label', null, it[1]));
        var sel = el('select');
        var ph = el('option', null, '-- pilih hasil --');
        ph.value = '';
        sel.appendChild(ph);
        RESULTS.forEach(function (rs) { var o = el('option', null, rs[1]); o.value = rs[0]; sel.appendChild(o); });
        var m = el('input');
        m.type = 'text';
        m.maxLength = 120;
        m.placeholder = 'Cara pemeriksaan (wajib)';
        if (existing[it[0]]) { sel.value = existing[it[0]].result; m.value = existing[it[0]].method || ''; }
        fields[it[0]] = { sel: sel, m: m };
        wrap.appendChild(sel);
        wrap.appendChild(m);
        form.appendChild(wrap);
      });
      var save = el('button', null, 'Simpan pemeriksaan');
      save.type = 'submit';
      form.appendChild(save);
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var checks = {}, ok = true;
        ITEMS.forEach(function (it) {
          var v = fields[it[0]].m.value.trim();
          var res = fields[it[0]].sel.value;
          if (!v || !res) { ok = false; }
          checks[it[0]] = { result: res, method: v };
        });
        if (!ok) { msg('Isi hasil dan cara pemeriksaan untuk kelima butir.', 'err'); return; }
        partnerAct(d.id, 'checks', { checks: checks }, 'Pemeriksaan disimpan.');
      });
      box.appendChild(form);
    } else if ((d.checks || []).length) {
      ITEMS.forEach(function (it) {
        var c = existing[it[0]];
        if (!c) { return; }
        var label = RESULTS.filter(function (x) { return x[0] === c.result; })[0];
        box.appendChild(el('div', 'small', it[1] + ': ' + (label ? label[1] : c.result) + ' (' + (c.method || '-') + ')'));
      });
    } else {
      box.appendChild(el('p', 'note', 'Belum ada pemeriksaan tercatat.'));
    }
    if (d.limitations && d.limitations.length) {
      box.appendChild(el('div', 'warnbox', 'Keterbatasan tercatat: ' + d.limitations.join(', ') + '. Butir ini tidak terverifikasi penuh.'));
    }

    var acts = PACTIONS.filter(function (a) { return a.s.indexOf(d.verification_status) >= 0 && a.r.indexOf(role) >= 0; });
    if (acts.length) {
      box.appendChild(el('h3', null, 'Tindakan'));
      var reason = el('input');
      reason.type = 'text';
      reason.maxLength = 200;
      reason.placeholder = 'Alasan (wajib untuk perbaikan, tolak, dan tangguhkan)';
      box.appendChild(reason);
      var row2 = el('div', 'row');
      acts.forEach(function (a) {
        row2.appendChild(mkBtn(a.t, a.reason || a.a === 'reject' ? 'secondary' : '', function () {
          var v = reason.value.trim();
          if (a.reason && !v) { msg('Alasan wajib diisi untuk tindakan ini.', 'err'); return; }
          if (a.a !== 'start-review' && a.a !== 'request-correction' && !window.confirm(a.t + ' mitra ' + d.name + '?')) { return; }
          partnerAct(d.id, a.a, a.reason ? { reason: v } : {}, a.ok);
        }));
      });
      box.appendChild(row2);
      box.appendChild(el('p', 'note', 'Server memeriksa apakah perubahan status diizinkan; bila tidak, pesan penolakannya tampil di atas.'));
    }
  }

  /* ---------- Pengaduan ---------- */
  var CSTATUS = { OPEN: 'Baru', IN_REVIEW: 'Sedang ditinjau', RESOLVED: 'Diselesaikan', CLOSED: 'Ditutup' };
  function loadComplaints() {
    var box = $('complaints');
    box.textContent = 'Memuat...';
    var st = $('c-status').value;
    api('/admin/complaints?per_page=50' + (st ? '&status=' + encodeURIComponent(st) : '')).then(function (r) {
      box.textContent = '';
      if (!r.ok) { msg(errText(r), 'err'); return; }
      var items = listOf(r.json);
      if (!items.length) { box.appendChild(el('p', 'note', 'Tidak ada pengaduan pada filter ini.')); return; }
      items.forEach(function (item) { box.appendChild(complaintCard(item)); });
    });
  }
  function complaintAction(id, action, body, okText) {
    return api('/admin/complaints/' + id + '/' + action, { method: 'POST', body: body || {} }).then(function (r) {
      if (r.ok) { msg(okText, 'ok'); loadComplaints(); } else { msg(errText(r), 'err'); }
      return r;
    });
  }
  function complaintCard(item) {
    var card = el('div', 'card');
    var top = el('div', 'top');
    top.appendChild(el('strong', null, item.category || 'Pengaduan'));
    top.appendChild(el('span', 'chip', CSTATUS[item.status] || item.status));
    card.appendChild(top);
    card.appendChild(el('div', 'small', 'Pesanan: ' + (item.booking_code || '-') + ' · ' + wib(item.created_at)));
    card.appendChild(el('p', null, item.description || '[DIHAPUS]'));
    if (item.safety_flag) { card.appendChild(el('p', 'warnbox', 'Ditandai sebagai pengaduan keselamatan. Prioritaskan peninjauan.')); }
    if (item.resolution_note) { card.appendChild(el('p', 'note', 'Catatan penyelesaian: ' + item.resolution_note)); }
    if (role === 'ops_admin' || role === 'reviewer') {
      var actions = el('div', 'row');
      if (item.status === 'OPEN') {
        actions.appendChild(mkBtn('Mulai tinjau', '', function () {
          complaintAction(item.id, 'start-review', {}, 'Peninjauan dimulai.');
        }));
      }
      if (item.status === 'IN_REVIEW') {
        var note = el('textarea');
        note.maxLength = 1000;
        note.required = true;
        note.placeholder = 'Catatan penyelesaian (wajib)';
        card.appendChild(note);
        actions.appendChild(mkBtn('Tandai selesai', '', function () {
          var value = note.value.trim();
          if (!value) { msg('Catatan penyelesaian wajib diisi.', 'err'); return; }
          complaintAction(item.id, 'resolve', { note: value }, 'Pengaduan diselesaikan.');
        }));
      }
      if (item.status === 'RESOLVED') {
        actions.appendChild(mkBtn('Tutup', 'secondary', function () {
          if (window.confirm('Tutup pengaduan ini?')) { complaintAction(item.id, 'close', {}, 'Pengaduan ditutup.'); }
        }));
        actions.appendChild(mkBtn('Buka kembali', 'secondary', function () {
          if (window.confirm('Buka kembali pengaduan ini?')) { complaintAction(item.id, 'reopen', {}, 'Pengaduan dibuka kembali.'); }
        }));
      }
      if (actions.children.length) { card.appendChild(actions); }
    } else {
      card.appendChild(el('p', 'note', 'Akun ini hanya memiliki akses baca untuk pengaduan.'));
    }
    return card;
  }

  /* ---------- Pasang ---------- */
  $('login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    msg('');
    api('/auth/login', { method: 'POST', body: { email: $('l-email').value.trim(), password: $('l-pass').value } }).then(function (r) {
      if (!r.ok) { msg(errText(r), 'err'); return; }
      saveToken(r.json.token);
      var u = r.json.user;
      $('l-pass').value = '';
      if (STAFF.indexOf(u.role) < 0) {
        api('/auth/logout', { method: 'POST' }).then(function () {
          leaveApp('Akun ini bukan akun staf. Pasien memakai /app dan mitra memakai /mitra.');
        });
        return;
      }
      enterApp(u);
    });
  });
  $('logout').addEventListener('click', function () {
    api('/auth/logout', { method: 'POST' }).then(function () { leaveApp(''); });
  });
  $('o-load').addEventListener('click', loadOrders);
  $('o-status').addEventListener('change', loadOrders);
  $('pa-load').addEventListener('click', loadPartners);
  $('pa-status').addEventListener('change', renderPartnerList);
  $('c-load').addEventListener('click', loadComplaints);
  $('c-status').addEventListener('change', loadComplaints);

  if (token) {
    api('/me').then(function (r) {
      if (r.ok && r.json.user && STAFF.indexOf(r.json.user.role) >= 0) { enterApp(r.json.user); } else { leaveApp(''); }
    });
  }
})();
