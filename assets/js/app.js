/* AMIGO! The Retro Collector – kleines Vanilla-JS, alles ist optional (Progressive Enhancement). */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var el = function (tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  };

  /* ---------- Theme ---------- */
  var THEMES = ['crt', 'dmg', 'c64'];
  function setTheme(t) {
    if (THEMES.indexOf(t) < 0) return;
    document.documentElement.setAttribute('data-theme', t);
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = 'amigo_theme=' + t + '; Max-Age=31536000; Path=/; SameSite=Lax' + secure;
  }
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-theme-toggle]');
    if (toggle) {
      var cur = document.documentElement.getAttribute('data-theme');
      setTheme(THEMES[(THEMES.indexOf(cur) + 1) % THEMES.length]);
    }
    var set = e.target.closest('[data-theme-set]');
    if (set) setTheme(set.getAttribute('data-theme-set'));

    var copy = e.target.closest('[data-copy]');
    if (copy && navigator.clipboard) {
      navigator.clipboard.writeText(copy.getAttribute('data-copy')).then(function () {
        copy.textContent = '✓ Kopiert';
      });
    }
    if (e.target.closest('[data-print]')) window.print();
  });

  /* ---------- Sicherheitsabfragen ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var btn = e.submitter;
    var msg = (btn && btn.getAttribute('data-confirm')) || form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  }, true);

  /* ---------- Liste filtern (Plattform-Import) ---------- */
  $$('[data-filter-list]').forEach(function (input) {
    var list = $(input.getAttribute('data-filter-list'));
    if (!list) return;
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      $$('[data-filter-text]', list).forEach(function (li) {
        li.hidden = q !== '' && li.getAttribute('data-filter-text').indexOf(q) < 0;
      });
    });
  });

  /* ---------- Spielsuche (TheGamesDB + lokal) ---------- */
  $$('[data-picker]').forEach(function (root) {
    var api = root.getAttribute('data-api');
    var input = $('[data-picker-input]', root);
    var list = $('[data-picker-list]', root);
    var status = $('[data-picker-status]', root);
    var gameField = $('[data-picker-game]', root);
    var tgdbField = $('[data-picker-tgdb]', root);
    var linked = $('[data-picker-linked]', root);
    var linkedName = $('[data-picker-name]', root);
    var thumb = $('[data-picker-thumb]', root);
    var platform = $('[data-picker-platform]', root);
    var timer = null, ctrl = null, results = [], active = -1;
    var statusDefault = status ? status.textContent : '';

    function safeThumb(url) {
      return typeof url === 'string' && url.indexOf('https://cdn.thegamesdb.net/') === 0 ? url : null;
    }
    function close() { list.hidden = true; active = -1; input.setAttribute('aria-expanded', 'false'); }
    function unlink() {
      gameField.value = '';
      tgdbField.value = '';
      linked.classList.add('is-empty');
    }
    function choose(r) {
      input.value = r.title;
      gameField.value = r.game_id || '';
      tgdbField.value = r.game_id ? '' : (r.tgdb_id || '');
      if (r.platform_id && platform) platform.value = String(r.platform_id);
      linkedName.textContent = r.title + (r.platform ? ' (' + r.platform + ')' : '');
      var t = safeThumb(r.thumb);
      if (t) { thumb.src = t; thumb.hidden = false; } else { thumb.hidden = true; thumb.removeAttribute('src'); }
      linked.classList.remove('is-empty');
      close();
    }
    function highlight(i) {
      var buttons = $$('button', list);
      buttons.forEach(function (b, k) { b.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      if (buttons[i]) buttons[i].scrollIntoView({ block: 'nearest' });
      active = i;
    }
    function render(data) {
      results = data.results || [];
      list.textContent = '';
      if (status) status.textContent = data.error ? data.error : (results.length ? results.length + ' Treffer' : 'Keine Treffer – Titel einfach frei eingeben.');
      if (!results.length) { close(); return; }
      results.forEach(function (r, i) {
        var b = el('button');
        b.type = 'button';
        b.setAttribute('role', 'option');
        var t = safeThumb(r.thumb);
        if (t) {
          var img = el('img'); img.src = t; img.alt = ''; img.loading = 'lazy'; b.appendChild(img);
        } else {
          b.appendChild(el('span', 'noimg', '?'));
        }
        var txt = el('span');
        txt.appendChild(el('strong', null, r.title));
        txt.appendChild(el('small', null, [r.platform, r.year].filter(Boolean).join(' · ')));
        b.appendChild(txt);
        b.appendChild(el('span', 'src', r.game_id ? 'Katalog' : 'TGDB'));
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () { choose(r); });
        list.appendChild(b);
        if (i === 0) active = -1;
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }
    function search() {
      var q = input.value.trim();
      if (q.length < 2) { close(); if (status) status.textContent = statusDefault; return; }
      if (ctrl) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      var url = api + '&q=' + encodeURIComponent(q) + (platform && platform.value ? '&platform=' + encodeURIComponent(platform.value) : '');
      if (status) status.textContent = 'Suche… LOADING';
      fetch(url, { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined, headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { results: [], error: 'Suche fehlgeschlagen.' }; })
        .then(render)
        .catch(function (err) { if (err && err.name !== 'AbortError' && status) status.textContent = 'Suche nicht möglich.'; });
    }

    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.addEventListener('input', function () {
      if (!linked.classList.contains('is-empty')) unlink();
      clearTimeout(timer);
      timer = setTimeout(search, 350);
    });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, results.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(results[active]); }
      else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    $('[data-picker-clear]', root).addEventListener('click', function () { unlink(); input.focus(); });
  });

  /* ---------- Foto-Auswahl mit Vorschau + Beschreibung ---------- */
  $$('[data-photo-input]').forEach(function (input) {
    var preview = $('[data-photo-preview]', input.parentNode);
    var zone = input.parentNode.querySelector('.dropzone');
    function show() {
      preview.textContent = '';
      Array.prototype.forEach.call(input.files, function (file, i) {
        var li = el('li');
        var img = el('img');
        img.alt = '';
        if (window.URL && URL.createObjectURL) img.src = URL.createObjectURL(file);
        li.appendChild(img);
        var box = el('div', 'photo-edit-fields');
        box.appendChild(el('small', 'muted', file.name + ' · ' + (file.size / 1048576).toFixed(1) + ' MB'));
        var cap = el('input');
        cap.name = 'new_captions[' + i + ']';
        cap.maxLength = 500;
        cap.placeholder = 'Beschreibung zum Foto';
        cap.setAttribute('aria-label', 'Beschreibung für ' + file.name);
        box.appendChild(cap);
        li.appendChild(box);
        preview.appendChild(li);
      });
    }
    input.addEventListener('change', show);
    if (zone) {
      ['dragenter', 'dragover'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        zone.addEventListener(ev, function () { zone.classList.remove('is-over'); });
      });
      zone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); }
      });
    }
  });

  /* ---------- Fotos vor dem Upload verkleinern (spart bei EDGE/3G Minuten) ---------- */
  var MAX_EDGE = 2000, QUALITY = 0.85, MIN_BYTES = 900 * 1024;

  function shrink(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.createImageBitmap || !HTMLCanvasElement.prototype.toBlob) {
      return Promise.resolve(file);
    }
    return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
      var scale = Math.min(1, MAX_EDGE / Math.max(bmp.width, bmp.height));
      if (scale === 1 && file.size < MIN_BYTES) return file;
      var c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale);
      c.height = Math.round(bmp.height * scale);
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#181428';
      ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (resolve) {
        c.toBlob(function (blob) {
          if (!blob || blob.size >= file.size) { resolve(file); return; }
          resolve(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', QUALITY);
      });
    }).catch(function () { return file; });
  }

  $$('form[data-resize-images]').forEach(function (form) {
    var busy = false;
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented || busy) return;
      var input = $('[data-photo-input]', form);
      var status = $('[data-upload-status]', form);
      if (!input || !input.files.length || typeof DataTransfer === 'undefined') return;
      e.preventDefault();
      busy = true;
      var btn = $('button[type=submit]', form);
      if (btn) btn.disabled = true;
      if (status) status.textContent = 'Fotos werden verkleinert…';
      Promise.all(Array.prototype.map.call(input.files, shrink)).then(function (files) {
        try {
          var dt = new DataTransfer();
          files.forEach(function (f) { dt.items.add(f); });
          input.files = dt.files;
        } catch (err) { /* Original-Dateien senden */ }
        var total = files.reduce(function (s, f) { return s + f.size; }, 0);
        if (status) status.textContent = 'Lade hoch (' + (total / 1048576).toFixed(1) + ' MB)…';
        HTMLFormElement.prototype.submit.call(form);
      });
    });
  });

  /* ---------- QR-Code für 2FA ---------- */
  $$('[data-qr]').forEach(function (box) {
    if (typeof window.qrcode !== 'function') return;
    var qr = window.qrcode(0, 'M');
    qr.addData(box.getAttribute('data-qr'));
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  });

  /* ---------- Installer: MySQL-Felder nur bei Bedarf ---------- */
  var dbFields = $('[data-db-fields]');
  if (dbFields) {
    var sync = function () {
      var c = $('[data-db-switch]:checked');
      dbFields.hidden = !c || c.value !== dbFields.getAttribute('data-db-fields');
    };
    $$('[data-db-switch]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }

  /* ---------- Prüfen, ob data/ öffentlich erreichbar ist ---------- */
  $$('[data-probe]').forEach(function (box) {
    fetch(box.getAttribute('data-probe') + '?' + Date.now(), { credentials: 'omit', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .catch(function () { return ''; })
      .then(function (body) {
        var exposed = body.indexOf('AMIGO-PROBE') >= 0;
        box.className = 'flash ' + (exposed ? 'flash-error' : 'flash-ok');
        box.textContent = box.getAttribute(exposed ? 'data-probe-fail' : 'data-probe-ok');
      });
  });
})();
