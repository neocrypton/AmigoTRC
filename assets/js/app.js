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
  var THEMES = ['crt', 'dmg', 'c64', 'win98', 'superm'];
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

  /* ---------- Fehlende Cover still ausblenden statt kaputtem Bild-Symbol ---------- */
  document.addEventListener('error', function (e) {
    var img = e.target;
    if (img && img.tagName === 'IMG' && img.src.indexOf('r=cover') >= 0) img.classList.add('img-missing');
  }, true);

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
    var lastQuery = '', page = 1, more = false, loadingMore = false;
    var statusDefault = status ? status.textContent : '';

    function safeThumb(url) {
      return typeof url === 'string' && url.indexOf('index.php?r=cover&') === 0 ? url : null;
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
      var buttons = $$('button[role="option"]', list);
      buttons.forEach(function (b, k) { b.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      if (buttons[i]) buttons[i].scrollIntoView({ block: 'nearest' });
      active = i;
    }
    function option(r) {
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
      return b;
    }
    /* Liste aufbauen; append = weitere Seite von TheGamesDB unten anhängen */
    function render(data, append) {
      var incoming = data.results || [];
      more = !!data.more;
      if (append) {
        var known = {};
        results.forEach(function (r) { if (r.tgdb_id) known[r.tgdb_id] = true; });
        incoming = incoming.filter(function (r) { return !r.tgdb_id || !known[r.tgdb_id]; });
        results = results.concat(incoming);
      } else {
        results = incoming;
        list.textContent = '';
        list.scrollTop = 0;
        active = -1;
      }
      if (status) status.textContent = data.error ? data.error : (results.length ? results.length + ' Treffer' : 'Keine Treffer – Titel einfach frei eingeben.');
      if (!results.length) { close(); return; }
      var head = $('.picker-head', list);
      if (!head) { head = el('div', 'picker-head'); list.appendChild(head); }
      head.textContent = results.length + ' Treffer';
      var oldMore = $('.picker-more', list);
      if (oldMore) list.removeChild(oldMore);
      incoming.forEach(function (r) { list.appendChild(option(r)); });
      if (more) {
        var m = el('button', 'picker-more', '▼ Weitere Treffer von TheGamesDB laden');
        m.type = 'button';
        m.addEventListener('mousedown', function (e) { e.preventDefault(); });
        m.addEventListener('click', loadMore);
        list.appendChild(m);
      }
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }
    function fetchPage(q, p) {
      var url = api + '&q=' + encodeURIComponent(q) + (platform && platform.value ? '&platform=' + encodeURIComponent(platform.value) : '') + (p > 1 ? '&page=' + p : '');
      return fetch(url, { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined, headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { results: [], error: 'Suche fehlgeschlagen.' }; });
    }
    function search() {
      var q = input.value.trim();
      if (q.length < 2) { close(); if (status) status.textContent = statusDefault; return; }
      if (ctrl) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      lastQuery = q; page = 1; loadingMore = false;
      if (status) status.textContent = 'Suche… LOADING';
      fetchPage(q, 1)
        .then(function (d) { render(d, false); })
        .catch(function (err) { if (err && err.name !== 'AbortError' && status) status.textContent = 'Suche nicht möglich.'; });
    }
    function loadMore() {
      if (loadingMore || !more) return;
      loadingMore = true;
      var btn = $('.picker-more', list);
      if (btn) { btn.disabled = true; btn.textContent = 'Lade… LOADING'; }
      var q = lastQuery, next = page + 1;
      fetchPage(q, next)
        .then(function (d) {
          loadingMore = false;
          if (q !== lastQuery) return;
          if (d.error) { if (btn) { btn.disabled = false; btn.textContent = d.error; } return; }
          page = next;
          render(d, true);
        })
        .catch(function (err) {
          loadingMore = false;
          if (btn && err && err.name !== 'AbortError') { btn.disabled = false; btn.textContent = 'Laden fehlgeschlagen – nochmal versuchen'; }
        });
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

  /* ---------- Foto-Auswahl: Fotos sammeln, Vorschau + Beschreibung ----------
     Browser ersetzen bei jeder neuen Auswahl den Inhalt eines Datei-Felds. Darum
     merken wir uns alle gewählten/aufgenommenen Fotos selbst und übergeben sie
     erst beim Speichern gesammelt an das Formular. */
  var canDT = (function () { try { return !!new DataTransfer().items; } catch (e) { return false; } })();

  $$('[data-photo-input]').forEach(function (input) {
    var form = input.form;
    var preview = $('[data-photo-preview]', input.parentNode);
    var zone = input.parentNode.querySelector('.dropzone');
    var zoneText = zone ? $('[data-dropzone-text]', zone) : null;
    var maxFiles = parseInt(input.getAttribute('data-max-files'), 10) || 20;
    var status = $('[data-upload-status]', form);
    var pending = [];

    function fileItem(file) {
      var li = el('li');
      var img = el('img');
      img.alt = '';
      if (window.URL && URL.createObjectURL) img.src = URL.createObjectURL(file);
      li.appendChild(img);
      var box = el('div', 'photo-edit-fields');
      box.appendChild(el('small', 'muted', file.name + ' · ' + (file.size / 1048576).toFixed(1) + ' MB'));
      var cap = el('input');
      cap.name = 'new_captions[]';
      cap.maxLength = 500;
      cap.placeholder = 'Beschreibung zum Foto';
      cap.setAttribute('aria-label', 'Beschreibung für ' + file.name);
      box.appendChild(cap);
      li.appendChild(box);
      return li;
    }

    function updateUi() {
      if (zoneText) zoneText.textContent = pending.length ? 'Weiteres Foto hinzufügen' : 'Fotos auswählen oder aufnehmen';
      if (status) status.textContent = pending.length ? pending.length + ' neue(s) Foto(s) – wird beim Speichern hochgeladen' : '';
    }

    if (!canDT) {
      // Sehr alte Browser: Auswahl ersetzt die vorherige (Standardverhalten).
      input.addEventListener('change', function () {
        preview.textContent = '';
        Array.prototype.forEach.call(input.files, function (f) { preview.appendChild(fileItem(f)); });
      });
      return;
    }

    input.removeAttribute('name'); // Dateien werden beim Speichern gesammelt übergeben
    input.setAttribute('data-photo-collector', '');

    function add(files) {
      Array.prototype.forEach.call(files, function (file) {
        if (!/^image\//.test(file.type)) return;
        if (pending.length >= maxFiles) {
          if (status) status.textContent = 'Maximal ' + maxFiles + ' Fotos pro Speichern – bitte zuerst speichern.';
          return;
        }
        var entry = { file: file, li: fileItem(file) };
        var rm = el('button', 'btn btn-sm btn-ghost photo-remove', '✕');
        rm.type = 'button';
        rm.title = 'Foto entfernen';
        rm.setAttribute('aria-label', 'Foto ' + file.name + ' entfernen');
        rm.addEventListener('click', function () {
          pending.splice(pending.indexOf(entry), 1);
          preview.removeChild(entry.li);
          updateUi();
        });
        entry.li.appendChild(rm);
        pending.push(entry);
        preview.appendChild(entry.li);
      });
      input.value = ''; // gleiche Datei/Kamera erneut auswählbar
      updateUi();
    }

    input.addEventListener('change', function () { add(input.files); });
    if (zone) {
      ['dragenter', 'dragover'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        zone.addEventListener(ev, function () { zone.classList.remove('is-over'); });
      });
      zone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files);
      });
    }

    /* Beim Speichern: Fotos verkleinern (spart bei EDGE/3G Minuten) und gesammelt anhängen. */
    var busy = false;
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented || busy || !pending.length) return;
      e.preventDefault();
      busy = true;
      var btn = $('button[type=submit]', form);
      if (btn) btn.disabled = true;
      if (status) status.textContent = 'Fotos werden verkleinert…';
      Promise.all(pending.map(function (p) { return shrink(p.file); })).then(function (files) {
        var dt = new DataTransfer();
        files.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
        input.name = 'photos[]';
        var total = files.reduce(function (sum, f) { return sum + f.size; }, 0);
        if (status) status.textContent = 'Lade hoch (' + (total / 1048576).toFixed(1) + ' MB)…';
        HTMLFormElement.prototype.submit.call(form);
      });
    });
  });

  /* ---------- Fotos vor dem Upload verkleinern ---------- */
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

  /* ---------- QR-Code für 2FA ---------- */
  $$('[data-qr]').forEach(function (box) {
    if (typeof window.qrcode !== 'function') return;
    var qr = window.qrcode(0, 'M');
    qr.addData(box.getAttribute('data-qr'));
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  });

  /* ---------- Smilies per Klick an der Cursorposition einfügen ---------- */
  $$('[data-smiley-target]').forEach(function (bar) {
    var ta = $(bar.getAttribute('data-smiley-target'));
    if (!ta) return;
    bar.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-code]');
      if (!btn) return;
      var code = btn.getAttribute('data-code');
      var start = ta.selectionStart || 0, end = ta.selectionEnd || 0, v = ta.value;
      var before = v.slice(0, start), after = v.slice(end);
      var ins = (before && !/\s$/.test(before) ? ' ' : '') + code + (/^\s/.test(after) ? '' : ' ');
      if (v.length + ins.length > (ta.maxLength > 0 ? ta.maxLength : 1e9)) return;
      ta.value = before + ins + after;
      var pos = before.length + ins.length;
      ta.focus();
      ta.setSelectionRange(pos, pos);
    });
  });

  /* ---------- Lange Auswahl schrittweise einblenden (Badges: erst 15, dann je 10) ---------- */
  $$('[data-reveal]').forEach(function (grid) {
    var btn = $('[data-reveal-more]', grid.form || grid.parentNode);
    var items = $$('.badge-pick', grid);
    var shown = parseInt(grid.getAttribute('data-reveal'), 10) || 15;
    var step = parseInt(grid.getAttribute('data-reveal-step'), 10) || 10;
    if (!btn || items.length <= shown) return;
    function update() {
      var hidden = 0;
      items.forEach(function (it, i) {
        // Der gewählte Badge bleibt immer sichtbar, auch wenn er weiter hinten liegt.
        var keep = i < shown || $('input', it).checked;
        it.hidden = !keep;
        if (!keep) hidden++;
      });
      btn.hidden = hidden === 0;
      btn.textContent = 'Mehr… (' + hidden + ' weitere)';
    }
    btn.addEventListener('click', function () {
      var first = items[shown];
      shown += step;
      update();
      if (first) { var inp = $('input', first); if (inp) inp.focus({ preventScroll: true }); }
    });
    update();
  });

  /* ---------- „Alle markieren“ ---------- */
  $$('[data-check-all]').forEach(function (box) {
    box.addEventListener('change', function () {
      $$('input[type=checkbox][name="' + box.getAttribute('data-check-all') + '"]', box.form).forEach(function (c) { c.checked = box.checked; });
    });
  });

  /* ---------- Benutzernamen vorschlagen (Empfängerfeld, kommagetrennt) ---------- */
  $$('[data-user-picker]').forEach(function (root) {
    var input = $('input', root), list = $('[data-user-list]', root), api = root.getAttribute('data-api'), timer = null;
    function lastToken() { var parts = input.value.split(','); return parts[parts.length - 1].trim(); }
    function close() { list.hidden = true; list.textContent = ''; }
    function choose(name) {
      var parts = input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      parts[Math.max(0, parts.length - (lastToken() ? 1 : 0))] = name;
      input.value = parts.join(', ') + ', ';
      close();
      input.focus();
    }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = lastToken();
      if (!q) { close(); return; }
      timer = setTimeout(function () {
        fetch(api + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : { users: [] }; })
          .then(function (d) {
            list.textContent = '';
            (d.users || []).forEach(function (name) {
              var b = el('button', null, name);
              b.type = 'button';
              b.addEventListener('mousedown', function (e) { e.preventDefault(); });
              b.addEventListener('click', function () { choose(name); });
              list.appendChild(b);
            });
            list.hidden = !(d.users || []).length;
          })
          .catch(close);
      }, 250);
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
  });

  /* ---------- Cover ergänzen: Stapel nacheinander abarbeiten, mit Fortschritt ---------- */
  $$('form[data-scrape-run]').forEach(function (form) {
    var box = $('[data-scrape-progress]');
    var bar = box ? $('progress', box) : null;
    var status = box ? $('[data-scrape-status]', box) : null;
    var total = { processed: 0, linked: 0, review: 0, none: 0 };
    form.addEventListener('submit', function (e) {
      if (!window.fetch || !box) return;
      e.preventDefault();
      form.hidden = true;
      box.hidden = false;
      var step = function () {
        fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.error) { status.textContent = d.error; return; }
            ['processed', 'linked', 'review', 'none'].forEach(function (k) { total[k] += d[k]; });
            bar.value = total.processed;
            status.textContent = total.processed + ' geprüft · ' + total.linked + ' verknüpft · ' + total.review + ' zum Prüfen · ' + total.none + ' ohne Treffer';
            if (d.stopped) { status.textContent += ' – ' + d.stopped; setTimeout(function () { location.reload(); }, 4000); return; }
            if (d.remaining > 0 && d.processed > 0) { step(); } else { status.textContent += ' – fertig!'; setTimeout(function () { location.reload(); }, 1500); }
          })
          .catch(function () { status.textContent = 'Verbindung unterbrochen – Seite neu laden, um fortzusetzen.'; });
      };
      step();
    });
  });

  /* ---------- CSV-Import: jedes Feld nur einer Spalte zuordnen ---------- */
  var mapSelects = $$('[data-map-select]');
  mapSelects.forEach(function (sel) {
    sel.addEventListener('change', function () {
      if (sel.value) {
        mapSelects.forEach(function (other) {
          if (other !== sel && other.value === sel.value) {
            other.value = '';
            other.closest('.mapcol').classList.remove('is-mapped');
          }
        });
      }
      sel.closest('.mapcol').classList.toggle('is-mapped', !!sel.value);
    });
  });

  /* ---------- Felder nur bei passender Auswahl zeigen (z. B. SMTP-Daten) ---------- */
  $$('[data-toggle-target]').forEach(function (sel) {
    var target = $(sel.getAttribute('data-toggle-target'));
    if (!target) return;
    var sync = function () { target.hidden = sel.value !== sel.getAttribute('data-toggle-value'); };
    sel.addEventListener('change', sync);
    sync();
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

  /* ---------- Bot-Schutz (Proof-of-Work, läuft komplett lokal) ---------- */
  // SHA-256 für kurze ASCII-Texte – eigene Implementierung, funktioniert auch ohne HTTPS (kein crypto.subtle nötig).
  var K256 = [0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2];
  function sha256hex(str) {
    var len = str.length, blocks = ((len + 8) >> 6) + 1, w = new Array(blocks * 16), i, j;
    for (i = 0; i < w.length; i++) w[i] = 0;
    for (i = 0; i < len; i++) w[i >> 2] |= str.charCodeAt(i) << (24 - (i % 4) * 8);
    w[len >> 2] |= 0x80 << (24 - (len % 4) * 8);
    w[w.length - 1] = len * 8;
    var H = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19], W = new Array(64);
    for (j = 0; j < w.length; j += 16) {
      var a = H[0], b = H[1], c = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
      for (i = 0; i < 64; i++) {
        if (i < 16) {
          W[i] = w[j + i] | 0;
        } else {
          var x = W[i - 15], y = W[i - 2];
          W[i] = (W[i - 16] + (((x >>> 7) | (x << 25)) ^ ((x >>> 18) | (x << 14)) ^ (x >>> 3)) + W[i - 7]
            + (((y >>> 17) | (y << 15)) ^ ((y >>> 19) | (y << 13)) ^ (y >>> 10))) | 0;
        }
        var t1 = (h + (((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7))) + ((e & f) ^ (~e & g)) + K256[i] + W[i]) | 0;
        var t2 = ((((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10))) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
        h = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
      }
      H[0] = (H[0] + a) | 0; H[1] = (H[1] + b) | 0; H[2] = (H[2] + c) | 0; H[3] = (H[3] + d) | 0;
      H[4] = (H[4] + e) | 0; H[5] = (H[5] + f) | 0; H[6] = (H[6] + g) | 0; H[7] = (H[7] + h) | 0;
    }
    var out = '';
    for (i = 0; i < 8; i++) out += ('0000000' + (H[i] >>> 0).toString(16)).slice(-8);
    return out;
  }

  $$('[data-botguard]').forEach(function (box) {
    var field = $('[data-botguard-field]', box), status = $('[data-botguard-status]', box);
    var form = box.closest('form'), submit = form && $('[data-botguard-submit]', form);
    var say = function (t, cls) { status.textContent = t; status.className = 'botguard-status' + (cls ? ' ' + cls : ''); };
    if (submit) submit.disabled = true;
    say('🤖 Bot-Schutz läuft … 0 %');
    fetch(box.getAttribute('data-api'), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (ch) {
        var n = 0, max = ch.maxnumber, started = Date.now();
        (function step() {
          var stop = Math.min(n + 4000, max);
          for (; n <= stop; n++) {
            if (sha256hex(ch.salt + n) === ch.challenge) {
              field.value = btoa(JSON.stringify({ algorithm: ch.algorithm, challenge: ch.challenge, number: n, salt: ch.salt, signature: ch.signature }));
              // Kurz sichtbar lassen, damit klar ist, dass etwas passiert ist
              setTimeout(function () {
                say('✓ Bot-Schutz bestanden', 'is-ok');
                if (submit) submit.disabled = false;
              }, Math.max(0, 600 - (Date.now() - started)));
              return;
            }
          }
          if (n > max) { say('Bot-Schutz fehlgeschlagen – bitte Seite neu laden.', 'is-error'); return; }
          say('🤖 Bot-Schutz läuft … ' + Math.min(99, Math.round(n / max * 100)) + ' %');
          setTimeout(step, 0);
        })();
      })
      .catch(function () { say('Bot-Schutz konnte nicht geladen werden – bitte Seite neu laden.', 'is-error'); });
  });
})();
