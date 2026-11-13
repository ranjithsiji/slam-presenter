/* Slam result editor: selected presenters list (sortable) and a popup to tick presenters. */
(function ($) {
	'use strict';

	var cfg = window.sspResults;
	var t = cfg.i18n;
	var byId = {};
	cfg.pool.forEach(function (p) {
		byId[p.id] = p;
	});
	var selected = cfg.selected.filter(function (id) {
		return byId[id];
	});
	var $list = $('.ssp-result-list');

	function esc(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function avatar(p) {
		return p.thumb ? '<img src="' + esc(p.thumb) + '" alt="">' : '<span class="ssp-tt-noimg"></span>';
	}

	function dirty() {
		$('.ssp-result-dirty').prop('hidden', false).text(t.unsaved);
	}

	function render() {
		$list.html(selected.length ? selected.map(function (id) {
			var p = byId[id];
			return '<li data-id="' + id + '"><span class="ssp-handle dashicons dashicons-menu"></span>' + avatar(p) +
				'<span class="ssp-select-text"><strong>' + esc(p.name) + '</strong><small>' + esc(p.inst) + '</small><small class="ssp-picker-title">' + esc(p.title) + '</small></span>' +
				'<input type="hidden" name="ids[]" value="' + id + '">' +
				'<button type="button" class="button-link ssp-result-remove" aria-label="' + esc(t.remove) + '"><span class="dashicons dashicons-no-alt"></span></button></li>';
		}).join('') : '<li class="ssp-result-empty">' + esc(t.empty) + '</li>');
		$('.ssp-result-count').text(selected.length ? '(' + selected.length + ')' : '');
	}

	$list.sortable({
		handle: '.ssp-handle',
		axis: 'y',
		items: 'li[data-id]',
		update: function () {
			selected = $list.children('[data-id]').map(function () {
				return parseInt($(this).attr('data-id'), 10);
			}).get();
			dirty();
		}
	});

	$list.on('click', '.ssp-result-remove', function () {
		var id = parseInt($(this).closest('li').attr('data-id'), 10);
		selected = selected.filter(function (x) {
			return x !== id;
		});
		render();
		dirty();
	});

	// Popup.
	var dialog = document.createElement('dialog');
	dialog.className = 'ssp-select-dialog ssp-result-dialog';
	dialog.innerHTML = '<form method="dialog" class="ssp-select-form">' +
		'<header><h2>' + esc(t.title) + '</h2><input type="search" class="ssp-select-search" placeholder="' + esc(t.search) + '">' +
		'<span class="ssp-picker-scopes"><button type="button" data-scope="own" class="is-active">' + esc(t.thisSlam) + '</button><button type="button" data-scope="all">' + esc(t.edition) + '</button></span></header>' +
		'<div class="ssp-result-pool"><ul></ul></div>' +
		'<footer><span class="ssp-select-count"></span><button type="button" class="button ssp-select-cancel">' + esc(t.cancel) + '</button>' +
		'<button type="button" class="button button-primary ssp-select-add" disabled></button></footer></form>';
	document.body.appendChild(dialog);
	var $d = $(dialog);
	var scope = 'own';

	function renderPool() {
		var terms = $d.find('.ssp-select-search').val().toLowerCase().split(/\s+/).filter(Boolean);
		var rows = cfg.pool.filter(function (p) {
			if (scope === 'own' && !p.own) {
				return false;
			}
			var hay = [p.name, p.nameEn, p.inst, p.title, p.events].join(' ').toLowerCase();
			return terms.every(function (term) {
				return hay.indexOf(term) !== -1;
			});
		});
		$d.find('.ssp-result-pool ul').html(rows.length ? rows.map(function (p) {
			var inResult = selected.indexOf(p.id) !== -1;
			return '<li><label class="' + (inResult ? 'is-in' : '') + '"><input type="checkbox" value="' + p.id + '"' + (inResult ? ' checked disabled' : '') + '>' + avatar(p) +
				'<span class="ssp-select-text"><strong>' + esc(p.name) + '</strong><small>' + esc([p.nameEn, p.inst].filter(Boolean).join(' · ')) + '</small><small class="ssp-picker-title">' + esc(p.title) + '</small></span>' +
				(inResult ? '<span class="ssp-picker-badge">' + esc(t.inResult) + '</span>' : (p.no ? '<span class="ssp-picker-badge">' + esc(t.talkNo.replace('%d', p.no)) + '</span>' : '')) +
				'</label></li>';
		}).join('') : '<li class="ssp-picker-empty">' + esc(t.noMatch) + '</li>');
		count();
	}

	function count() {
		var n = $d.find('li input:checked:not(:disabled)').length;
		$d.find('.ssp-select-count').text(n ? n + ' ✓' : '');
		$d.find('.ssp-select-add').prop('disabled', !n).text(t.add.replace('%d', n));
	}

	$('.ssp-result-select').on('click', function () {
		$d.find('.ssp-select-search').val('');
		renderPool();
		dialog.showModal();
		$d.find('.ssp-select-search').trigger('focus');
	});
	$d.on('input', '.ssp-select-search', renderPool);
	$d.on('change', 'li input', count);
	$d.on('click', '[data-scope]', function () {
		scope = $(this).data('scope');
		$d.find('[data-scope]').removeClass('is-active');
		$(this).addClass('is-active');
		renderPool();
	});
	$d.on('click', '.ssp-select-cancel', function () {
		dialog.close();
	});
	$d.on('click', '.ssp-select-add', function () {
		$d.find('li input:checked:not(:disabled)').each(function () {
			selected.push(parseInt(this.value, 10));
		});
		dialog.close();
		render();
		dirty();
	});

	render();
})(jQuery);
