/* Science Slam Presenter Map: one dot per presenter at their institution, coloured by slam; several from one
   institution sit in a small ring around it. Kerala's districts and Tamil Nadu are drawn underneath. */
(function () {
  'use strict';
  var esc = function (v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  };
  document.querySelectorAll('[data-spm]').forEach(function (box) {
    var L = window.L; if (!L) return;
    var root = box.closest('.spm') || document;
    var cfg = JSON.parse(box.getAttribute('data-spm'));
    var colour = {}; var slamName = {};
    cfg.slams.forEach(function (s) { colour[s.slug] = s.colour; slamName[s.slug] = s.name; });
    var KERALA = L.latLngBounds([8.15, 74.85], [12.85, 77.45]);
    var map = L.map(box, { scrollWheelZoom: false, zoomSnap: 0.5 }).fitBounds(KERALA);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      referrerPolicy: 'strict-origin-when-cross-origin' // OpenStreetMap's tile policy needs the site as Referer
    }).addTo(map);
    map.on('focus', function () { map.scrollWheelZoom.enable(); });
    map.on('blur', function () { map.scrollWheelZoom.disable(); });

    // Boundaries: Tamil Nadu, Kerala's districts and the Kerala outline (DataMeet, CC BY 2.5 India, via Wikimedia Commons).
    var SHADES = ['#fde7c8', '#e3f0d4', '#dbe9f6', '#f6dde6', '#ece3f5', '#fff1b8', '#d9f0ec'];
    fetch(box.getAttribute('data-spm-boundaries'), { credentials: 'omit' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (geo) {
      if (!geo) return;
      var pick = function (fn) { return { type: 'FeatureCollection', features: geo.features.filter(fn) }; };
      var tn = pick(function (f) { return f.properties.kind === 'state' && f.properties.name === 'Tamil Nadu'; });
      var kl = pick(function (f) { return f.properties.kind === 'state' && f.properties.name === 'Kerala'; });
      var overlays = {};
      var tnLayer = L.layerGroup([L.geoJSON(tn, { interactive: false, style: { color: '#3f6d1f', weight: 2.2, fillColor: '#9cc46a', fillOpacity: 0.32, dashArray: '6 4' } })]);
      tn.features.forEach(function (f) { L.tooltip({ permanent: true, direction: 'center', className: 'spm-label spm-state', interactive: false }).setLatLng(f.properties.label).setContent('Tamil Nadu').addTo(tnLayer); });
      overlays['Tamil Nadu'] = tnLayer.addTo(map);
      var districts = L.layerGroup().addTo(map);
      pick(function (f) { return f.properties.kind === 'district'; }).features.forEach(function (f, i) {
        L.geoJSON(f, { interactive: false, style: { color: '#8a6d3b', weight: 1, fillColor: SHADES[i % SHADES.length], fillOpacity: 0.45 } }).addTo(districts);
        L.tooltip({ permanent: true, direction: 'center', className: 'spm-label', interactive: false }).setLatLng(f.properties.label).setContent(esc(f.properties.name)).addTo(districts);
      });
      overlays['Kerala districts'] = districts;
      overlays['Kerala'] = L.geoJSON(kl, { interactive: false, style: { color: '#7b1a1a', weight: 2.5, fill: false } }).addTo(map);
      L.control.layers(null, overlays, { collapsed: true, position: 'topright' }).addTo(map);
      map.attributionControl.addAttribution('Boundaries © <a href="https://github.com/datameet/maps">DataMeet</a> (CC BY 2.5 IN) via Wikimedia Commons');
      var labels = function () { box.classList.toggle('spm-small', map.getZoom() < 7); };
      map.on('zoomend', labels); labels();
    }).catch(function () {});

    // Dot k of n around the place: the first in the centre, then rings of 6, 12, 18 ... (pixels, so they never merge).
    var D = 14;
    var offset = function (k, n) {
      if (n === 1 || k === 0) return [0, 0];
      var ring = 1; var start = 1;
      while (k >= start + 6 * ring) { start += 6 * ring; ring += 1; }
      var inRing = Math.min(6 * ring, n - start); var a = (2 * Math.PI * (k - start)) / inRing - Math.PI / 2;
      return [Math.round(Math.cos(a) * ring * (D + 1)), Math.round(Math.sin(a) * ring * (D + 1))];
    };
    var layers = {}; var everything = [];
    cfg.slams.forEach(function (s) { layers[s.slug] = L.layerGroup().addTo(map); });
    cfg.places.forEach(function (p) {
      everything.push([p.lat, p.lng]);
      p.people.forEach(function (person, k) {
        var o = offset(k, p.people.length);
        var icon = L.divIcon({ className: 'spm-pin', iconSize: [D, D], iconAnchor: [D / 2 - o[0], D / 2 - o[1]], html: '<span style="background:' + (colour[person.slam] || '#555') + '"></span>' });
        var popup = '<div class="spm-pop">' + (person.thumb ? '<img src="' + esc(person.thumb) + '" alt="" width="56" height="56" loading="lazy">' : '') +
          '<div><a class="spm-pop-name" href="' + esc(person.url) + '">' + esc(person.name) + '</a>' + (person.name_en ? '<div class="spm-muted">' + esc(person.name_en) + '</div>' : '') +
          '<div>' + esc(person.topic) + '</div><div class="spm-muted">' + esc(p.name) + '</div><div style="color:' + (colour[person.slam] || '#555') + '">' + esc(slamName[person.slam] || '') + '</div></div></div>';
        L.marker([p.lat, p.lng], { icon: icon, title: (person.name_en || person.name) + ' · ' + p.name, keyboard: true })
          .bindPopup(popup, { maxWidth: 280 }).bindTooltip(esc(p.name) + ' (' + p.people.length + ')', { direction: 'top', offset: [0, -10] })
          .addTo(layers[person.slam] || map);
      });
    });
    root.querySelectorAll('[data-spm-slam]').forEach(function (cb) {
      cb.addEventListener('change', function () { var g = layers[cb.getAttribute('data-spm-slam')]; if (g) { if (cb.checked) g.addTo(map); else g.remove(); } });
    });
    var allBtn = root.querySelector('[data-spm-all]'); var keralaBtn = root.querySelector('[data-spm-kerala]');
    if (allBtn && everything.length) allBtn.addEventListener('click', function () { map.fitBounds(L.latLngBounds(everything).pad(0.1)); allBtn.hidden = true; keralaBtn.hidden = false; });
    if (keralaBtn) keralaBtn.addEventListener('click', function () { map.fitBounds(KERALA); allBtn.hidden = false; keralaBtn.hidden = true; });
  });
})();
