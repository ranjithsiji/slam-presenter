/* Timetable builder: drag-and-drop schedule with live start times. */
(function ($) {
	'use strict';

	var cfg = window.sspTimetable;
	if (!cfg) {
		return;
	}
	var t = cfg.i18n;
	var uid = 0;
	var dirty = false;
	var schedule = normalise(cfg.schedule);
	var byId = {};
	cfg.presenters.forEach(function (p) {
		byId[p.id] = p;
	});

	var $list = $('#ssp-tt-list');
	var $pool = $('#ssp-tt-pool');
	var $start = $('#ssp-tt-start');
	var $slots = $('#ssp-tt-slots');

	function normalise(s) {
		s = s || {};
		return {
			start: s.start || '09:30',
			items: (s.items || []).map(function (it) {
				return $.extend({ type: 'item', label: '', note: '', duration: 0, presenter_id: 0, fixed: '' }, it, { uid: ++uid });
			})
		};
	}

	function toMin(hm) {
		var p = (hm || '0:0').split(':');
		return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
	}

	function fmt(min) {
		var h = Math.floor(min / 60) % 24;
		var m = min % 60;
		var suffix = h >= 12 ? 'PM' : 'AM';
		var h12 = h % 12 || 12;
		return (h12 < 10 ? '0' : '') + h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + suffix;
	}

	function esc(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	// Same rules as SSP_Timetable::computed().
	function compute() {
		var clock = toMin(schedule.start);
		var no = 0;
		var session = null;
		var out = [];
		schedule.items.forEach(function (it, i) {
			var row = { start: 0, end: 0, no: 0 };
			if (it.type === 'session') {
				if (session !== null) {
					out[session].end = clock;
				}
				session = i;
				row.start = it.fixed ? toMin(it.fixed) : clock;
				clock = row.start;
				out.push(row);
				return;
			}
			if (it.fixed) {
				clock = toMin(it.fixed);
			}
			row.start = clock;
			clock += parseInt(it.duration, 10) || 0;
			row.end = clock;
			if (it.type === 'talk') {
				row.no = ++no;
			}
			if (it.type === 'break' && session !== null) {
				out[session].end = row.start;
				session = null;
			}
			out.push(row);
		});
		if (session !== null) {
			out[session].end = clock;
		}
		return { rows: out, end: clock, talks: no };
	}

	function initials(p) {
		var name = (p.nameEn || p.name || '').replace(/^(dr|prof)\.?\s+/i, '');
		return name.split(/\s+/).slice(0, 2).map(function (w) {
			return w.charAt(0).toUpperCase();
		}).join('');
	}

	// Small round photo framed on the face (same crop as the website), or initials.
	function avatar(p) {
		if (p && p.crop && window.SSPCropper) {
			var pt = window.SSPCropper.parseFocus(p.crop.focus);
			var css = window.SSPCropper.frame(p.crop.width, p.crop.height, pt[0], pt[1], p.crop.zoom).css;
			return '<span class="ssp-tt-avatar ssp-frame"><img class="ssp-frame-img" alt="" loading="lazy" src="' + esc(p.crop.src) + '" style="width:' + css.width + ';height:' + css.height + ';left:' + css.left + ';top:' + css.top + '"></span>';
		}
		return p && p.thumb ? '<img src="' + esc(p.thumb) + '" alt="">' : '<span class="ssp-tt-noimg">' + esc(p ? initials(p) : '') + '</span>';
	}

	// Searchable presenter picker: one popover shared by all talk rows.
	var picker = { $el: null, uid: 0, scope: 'unscheduled', active: 0, results: [] };

	function scheduledMap() {
		var c = compute();
		var map = {};
		schedule.items.forEach(function (it, i) {
			if (it.type === 'talk' && it.presenter_id) {
				map[it.presenter_id] = { uid: it.uid, no: c.rows[i].no };
			}
		});
		return map;
	}

	function pickerResults() {
		var map = scheduledMap();
		var terms = picker.$el.find('.ssp-picker-search').val().toLowerCase().split(/\s+/).filter(Boolean);
		return cfg.presenters.filter(function (p) {
			if (picker.scope === 'unscheduled' && (!p.inEvent || map[p.id])) {
				return false;
			}
			if (picker.scope === 'event' && !p.inEvent) {
				return false;
			}
			var hay = [p.name, p.nameEn, p.title, p.events].join(' ').toLowerCase();
			return terms.every(function (term) {
				return hay.indexOf(term) !== -1;
			});
		}).slice(0, 150).map(function (p) {
			var at = map[p.id];
			return { p: p, at: at && at.uid !== picker.uid ? at : null, current: at && at.uid === picker.uid };
		});
	}

	function renderPicker() {
		picker.results = pickerResults();
		picker.active = Math.min(picker.active, Math.max(0, picker.results.length - 1));
		picker.$el.find('[data-scope]').each(function () {
			$(this).toggleClass('is-active', $(this).data('scope') === picker.scope).attr('aria-pressed', $(this).data('scope') === picker.scope);
		});
		var html = picker.results.map(function (r, i) {
			return '<li role="option" data-i="' + i + '" class="' + (i === picker.active ? 'is-active' : '') + (r.current ? ' is-current' : '') + '">' +
				avatar(r.p) +
				'<span class="ssp-picker-text"><strong>' + esc(r.p.name) + '</strong>' +
				'<small>' + esc([r.p.nameEn !== r.p.name ? r.p.nameEn : '', r.p.events].filter(Boolean).join(' · ')) + '</small>' +
				'<small class="ssp-picker-title">' + esc(r.p.title) + '</small></span>' +
				(r.at ? '<span class="ssp-picker-badge" title="' + esc(t.swapHelp) + '">' + esc(t.scheduledAt.replace('%d', r.at.no)) + '</span>' : '') +
				'</li>';
		}).join('');
		picker.$el.find('.ssp-picker-list').html(html || '<li class="ssp-picker-empty">' + esc(t.noMatch) + '</li>');
	}

	function closePicker() {
		if (picker.$el) {
			picker.$el.remove();
			picker.$el = null;
			$(document).off('.sspPicker');
		}
	}

	function choose(index) {
		var r = picker.results[index];
		var it = find(picker.uid);
		if (!r || !it) {
			return;
		}
		// Picking someone already in another slot swaps the two presenters.
		if (r.at) {
			find(r.at.uid).presenter_id = it.presenter_id;
		}
		it.presenter_id = r.p.id;
		it.label = '';
		closePicker();
		render();
		markDirty();
	}

	function openPicker($btn) {
		closePicker();
		picker.uid = parseInt($btn.closest('li').attr('data-uid'), 10);
		picker.active = 0;
		picker.$el = $('<div class="ssp-picker" role="dialog">' +
			'<div class="ssp-picker-scopes">' +
			'<button type="button" data-scope="unscheduled">' + esc(t.unscheduled) + '</button>' +
			'<button type="button" data-scope="event">' + esc(t.thisEvent) + '</button>' +
			'<button type="button" data-scope="all">' + esc(t.allPresenter) + '</button>' +
			'</div>' +
			'<input type="search" class="ssp-picker-search" placeholder="' + esc(t.search) + '" aria-label="' + esc(t.search) + '">' +
			'<ul class="ssp-picker-list" role="listbox"></ul>' +
			'<div class="ssp-picker-foot"><button type="button" class="button-link ssp-picker-clear">' + esc(t.clear) + '</button></div>' +
			'</div>').appendTo('body');
		var off = $btn.offset();
		picker.$el.css({ top: off.top + $btn.outerHeight() + 4, left: off.left, width: Math.max($btn.outerWidth(), 420) });
		picker.scope = 'unscheduled';
		renderPicker();
		// Nothing left to schedule: show the event, and for a new event (eg. the final) everyone.
		['event', 'all'].forEach(function (scope) {
			if (!picker.results.length) {
				picker.scope = scope;
				renderPicker();
			}
		});
		picker.$el.find('.ssp-picker-search').trigger('focus');

		picker.$el.on('click', '[data-scope]', function () {
			picker.scope = $(this).data('scope');
			picker.active = 0;
			renderPicker();
			picker.$el.find('.ssp-picker-search').trigger('focus');
		});
		picker.$el.on('input', '.ssp-picker-search', function () {
			picker.active = 0;
			renderPicker();
		});
		picker.$el.on('click', 'li[data-i]', function () {
			choose(parseInt($(this).data('i'), 10));
		});
		picker.$el.on('click', '.ssp-picker-clear', function () {
			find(picker.uid).presenter_id = 0;
			closePicker();
			render();
			markDirty();
		});
		picker.$el.on('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				picker.active = Math.max(0, Math.min(picker.results.length - 1, picker.active + (e.key === 'ArrowDown' ? 1 : -1)));
				renderPicker();
				var $a = picker.$el.find('li.is-active');
				if ($a.length) {
					$a[0].scrollIntoView({ block: 'nearest' });
				}
			} else if (e.key === 'Enter') {
				e.preventDefault();
				choose(picker.active);
			} else if (e.key === 'Escape') {
				closePicker();
				$list.find('[data-uid="' + picker.uid + '"] .ssp-tt-pick').trigger('focus');
			}
		});
		setTimeout(function () {
			$(document).on('mousedown.sspPicker', function (e) {
				if (picker.$el && !$(e.target).closest('.ssp-picker, .ssp-tt-pick').length) {
					closePicker();
				}
			});
		}, 0);
	}

	function rowHtml(it) {
		var main;
		if (it.type === 'talk') {
			var p = byId[it.presenter_id];
			main = '<div class="ssp-tt-talk-main">' +
				avatar(p) +
				'<div><button type="button" class="ssp-tt-pick" aria-haspopup="dialog">' +
				(p ? '<strong>' + esc(p.name) + '</strong>' + (p.nameEn && p.nameEn !== p.name ? ' <span>' + esc(p.nameEn) + '</span>' : '') : '<em>' + esc(t.choose) + '</em>') +
				'<span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
				'<span class="ssp-tt-talk-title">' + esc(p ? p.title : it.label) + '</span>' +
				(p && !p.inEvent ? '<span class="ssp-tt-warn">' + esc(t.notInEvent) + '</span>' : '') +
				'</div></div>';
		} else if (it.type === 'session') {
			main = '<input type="text" class="ssp-tt-label" value="' + esc(it.label) + '" placeholder="' + esc(t.labelPh) + '">' +
				'<input type="text" class="ssp-tt-note" value="' + esc(it.note) + '" placeholder="' + esc(t.notePh) + '">';
		} else {
			main = '<input type="text" class="ssp-tt-label" value="' + esc(it.label) + '" placeholder="' + esc(t.labelPh) + '">';
		}
		return '<li class="ssp-tt-row type-' + it.type + (it.type === 'talk' && !it.presenter_id ? ' is-unmatched' : '') + '" data-uid="' + it.uid + '">' +
			'<span class="ssp-handle dashicons dashicons-menu"></span>' +
			'<span class="ssp-tt-clock"></span>' +
			'<span class="ssp-tt-badge">' + esc(t[it.type]) + '</span>' +
			'<div class="ssp-tt-main">' + main + '</div>' +
			(it.type === 'session' ? '<span></span>' : '<label class="ssp-tt-dur"><input type="number" min="0" value="' + (parseInt(it.duration, 10) || 0) + '"> ' + esc(t.min) + '</label>') +
			'<input type="time" class="ssp-tt-fixed" value="' + esc(it.fixed) + '" title="' + esc(t.fixedHelp) + '">' +
			'<button type="button" class="button-link ssp-tt-remove" aria-label="' + esc(t.remove) + '"><span class="dashicons dashicons-no-alt"></span></button>' +
			'</li>';
	}

	function find(uidValue) {
		for (var i = 0; i < schedule.items.length; i++) {
			if (schedule.items[i].uid === uidValue) {
				return schedule.items[i];
			}
		}
		return null;
	}

	function render() {
		closePicker();
		$start.val(schedule.start);
		$list.html(schedule.items.map(rowHtml).join(''));
		renderPool();
		updateTimes();
	}

	function updateTimes() {
		var c = compute();
		$list.children().each(function (i) {
			var r = c.rows[i];
			var it = schedule.items[i];
			var text = fmt(r.start) + (it.type === 'session' || r.end !== r.start ? ' – ' + fmt(r.end) : '');
			$(this).find('.ssp-tt-clock').text(text);
			$(this).find('.ssp-tt-badge').text(it.type === 'talk' ? '#' + r.no : t[it.type]);
		});
		$('.ssp-tt-summary').text(c.talks + ' ' + t.talks + ' · ' + t.ends + ' ' + fmt(c.end));
		if (document.activeElement !== $slots[0]) {
			$slots.val(c.talks);
		}
	}

	function renderPool() {
		var used = {};
		schedule.items.forEach(function (it) {
			if (it.type === 'talk') {
				used[it.presenter_id] = true;
			}
		});
		var rows = cfg.presenters.filter(function (p) {
			return p.inEvent && !used[p.id];
		}).map(function (p) {
			return '<li data-id="' + p.id + '">' +
				avatar(p) +
				'<span><strong>' + esc(p.name) + '</strong><small>' + esc(p.title) + '</small></span></li>';
		});
		var anyInEvent = cfg.presenters.some(function (p) {
			return p.inEvent;
		});
		$pool.html(rows.length ? rows.join('') : '<li class="ssp-tt-pool-empty' + (anyInEvent ? '' : ' is-none') + '">' + esc(anyInEvent ? t.allIn : t.noneInEvent) + '</li>');
		$pool.children('[data-id]').draggable({
			connectToSortable: '#ssp-tt-list',
			helper: 'clone',
			revert: 'invalid',
			appendTo: 'body',
			zIndex: 1000
		});
	}

	function markDirty() {
		dirty = true;
		$('.ssp-tt-dirty').prop('hidden', false).text(t.unsaved);
	}

	function syncOrderFromDom() {
		var order = $list.children().map(function () {
			return parseInt($(this).attr('data-uid'), 10);
		}).get();
		schedule.items = order.map(find).filter(Boolean);
	}

	function lastTalkIndex() {
		for (var i = schedule.items.length - 1; i >= 0; i--) {
			if (schedule.items[i].type === 'talk') {
				return i;
			}
		}
		return schedule.items.length - 1;
	}

	function newItem(type, extra) {
		var defaults = { session: 0, talk: cfg.talkMinutes, break: 15, item: 10 };
		return $.extend({ type: type, label: '', note: '', duration: defaults[type], presenter_id: 0, fixed: '', uid: ++uid }, extra || {});
	}

	$list.sortable({
		handle: '.ssp-handle',
		axis: 'y',
		placeholder: 'ssp-tt-placeholder',
		receive: function (e, ui) {
			// A presenter dragged in from the pool becomes a talk at the drop position.
			var it = newItem('talk', { presenter_id: parseInt(ui.item.data('id'), 10) });
			var $placed = $list.children().not('[data-uid]').first();
			$placed.replaceWith($(rowHtml(it)));
			schedule.items.push(it);
		},
		update: function () {
			setTimeout(function () {
				syncOrderFromDom();
				render();
				markDirty();
			}, 0);
		}
	});

	$list.on('input change', '.ssp-tt-label, .ssp-tt-note', function () {
		var it = find(parseInt($(this).closest('li').attr('data-uid'), 10));
		it[$(this).hasClass('ssp-tt-note') ? 'note' : 'label'] = this.value;
		markDirty();
	});

	$list.on('input change', '.ssp-tt-dur input', function () {
		find(parseInt($(this).closest('li').attr('data-uid'), 10)).duration = parseInt(this.value, 10) || 0;
		updateTimes();
		markDirty();
	});

	$list.on('change', '.ssp-tt-fixed', function () {
		find(parseInt($(this).closest('li').attr('data-uid'), 10)).fixed = this.value;
		updateTimes();
		markDirty();
	});

	$list.on('click', '.ssp-tt-pick', function () {
		if (picker.$el && picker.uid === parseInt($(this).closest('li').attr('data-uid'), 10)) {
			closePicker();
			return;
		}
		openPicker($(this));
	});

	$list.on('click', '.ssp-tt-remove', function () {
		var id = parseInt($(this).closest('li').attr('data-uid'), 10);
		schedule.items = schedule.items.filter(function (it) {
			return it.uid !== id;
		});
		render();
		markDirty();
	});

	$pool.on('click', 'li[data-id]', function () {
		schedule.items.splice(lastTalkIndex() + 1, 0, newItem('talk', { presenter_id: parseInt($(this).data('id'), 10) }));
		render();
		markDirty();
	});

	$('[data-add]').on('click', function () {
		var type = $(this).data('add');
		var it = newItem(type);
		schedule.items.push(it);
		render();
		markDirty();
		$list.find('[data-uid="' + it.uid + '"] input[type=text], [data-uid="' + it.uid + '"] select').first().trigger('focus');
	});

	// Talk slots: more adds empty slots after the last talk, fewer removes empty ones from the end.
	$slots.on('change', function () {
		var want = Math.max(0, Math.min(200, parseInt(this.value, 10) || 0));
		var talks = schedule.items.filter(function (it) {
			return it.type === 'talk';
		});
		var have = talks.length;
		if (want > have) {
			var at = lastTalkIndex() + 1;
			var add = [];
			for (var n = have; n < want; n++) {
				add.push(newItem('talk'));
			}
			if (!talks.length) {
				add.unshift(newItem('session', { label: t.session + ' 1' }));
				at = schedule.items.length;
			}
			Array.prototype.splice.apply(schedule.items, [at, 0].concat(add));
		} else if (want < have) {
			for (var i = schedule.items.length - 1; i >= 0 && have > want; i--) {
				if (schedule.items[i].type === 'talk' && !schedule.items[i].presenter_id) {
					schedule.items.splice(i, 1);
					have--;
				}
			}
			if (have > want) {
				window.alert(t.slotsFilled);
			}
		}
		render();
		markDirty();
	});

	$start.on('change', function () {
		schedule.start = this.value || '09:30';
		updateTimes();
		markDirty();
	});

	$('#ssp-tt-clear').on('click', function () {
		if (window.confirm(t.confirmClear)) {
			schedule.items = [];
			render();
			markDirty();
		}
	});

	function post(action, data) {
		return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce, event: cfg.event }, data));
	}

	function loadSchedule(s) {
		schedule = normalise(s);
		render();
		markDirty();
		if (s.unmatched && s.unmatched.length) {
			window.alert(t.unmatched + '\n\n' + s.unmatched.join('\n'));
		}
	}

	$('#ssp-tt-generate').on('click', function () {
		if (schedule.items.length && !window.confirm(t.confirmGen)) {
			return;
		}
		post('ssp_generate_timetable', {
			start: $('#ssp-gen-start').val(),
			talk_minutes: $('#ssp-gen-talk').val(),
			per_session: $('#ssp-gen-per').val(),
			tea_minutes: $('#ssp-gen-tea').val(),
			lunch_at: $('#ssp-gen-lunch-at').val(),
			lunch: $('#ssp-gen-lunch').val(),
			order: $('#ssp-gen-order').val()
		}).done(function (res) {
			if (res.success) {
				loadSchedule(res.data.schedule);
			}
		});
	});

	$('#ssp-tt-parse').on('click', function () {
		var text = $('#ssp-tt-text').val();
		if (!text.trim() || (schedule.items.length && !window.confirm(t.confirmGen))) {
			return;
		}
		post('ssp_parse_timetable', { text: text }).done(function (res) {
			if (res.success) {
				loadSchedule(res.data.schedule);
			}
		});
	});

	$('#ssp-tt-save').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		var payload = {
			start: schedule.start,
			items: schedule.items.map(function (it) {
				return { type: it.type, label: it.label, note: it.note, duration: it.duration, presenter_id: it.presenter_id, fixed: it.fixed };
			})
		};
		post('ssp_save_timetable', { schedule: JSON.stringify(payload) }).done(function (res) {
			if (res.success) {
				dirty = false;
				// Saving adds the scheduled presenters to the event.
				schedule.items.forEach(function (it) {
					if (it.type === 'talk' && byId[it.presenter_id]) {
						byId[it.presenter_id].inEvent = true;
					}
				});
				render();
				$('.ssp-tt-dirty').prop('hidden', false).text(t.saved);
			} else {
				window.alert(res.data.message);
			}
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	// "Select presenters": tick presenters from each slam's list and add them in one go (eg. finalists).
	var selectDialog = null;

	function fmtN(str, n) {
		return str.replace('%d', n);
	}

	function buildSelectDialog() {
		var map = scheduledMap();
		var groups = cfg.events.filter(function (e) {
			return e.id !== cfg.event;
		}).sort(function (a, b) {
			return (a.type === 'final') - (b.type === 'final');
		}).map(function (e) {
			var members = cfg.presenters.filter(function (p) {
				return (p.eventIds || []).indexOf(e.id) !== -1;
			});
			// That slam's timetable order first, then the rest by name.
			members.sort(function (a, b) {
				var ia = e.order.indexOf(a.id);
				var ib = e.order.indexOf(b.id);
				return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib) || a.name.localeCompare(b.name);
			});
			return { title: e.name, sub: e.date, members: members };
		});
		var loose = cfg.presenters.filter(function (p) {
			return !(p.eventIds || []).length;
		});
		if (loose.length) {
			groups.push({ title: t.noEvent, sub: '', members: loose });
		}

		var html = '<form method="dialog" class="ssp-select-form">' +
			'<header><h2>' + esc(cfg.isFinal ? t.finalTitle : t.selectTitle) + '</h2>' +
			'<input type="search" class="ssp-select-search" placeholder="' + esc(t.search) + '">' +
			'<p class="description">' + esc(t.selectHelp) + '</p></header>' +
			'<div class="ssp-select-groups">' +
			groups.map(function (g, gi) {
				return '<section class="ssp-select-group" data-g="' + gi + '"><h3>' + esc(g.title) + ' <span>' + esc(g.sub) + '</span>' +
					'<label class="ssp-select-all"><input type="checkbox"> ' + esc(t.selectAll) + '</label></h3><ul>' +
					g.members.map(function (p) {
						var at = map[p.id];
						var hay = [p.name, p.nameEn, p.title, p.inst].join(' ').toLowerCase();
						return '<li data-hay="' + esc(hay) + '"><label class="' + (at ? 'is-in' : '') + '">' +
							'<input type="checkbox" value="' + p.id + '"' + (at ? ' checked disabled' : '') + '>' +
							avatar(p) +
							'<span class="ssp-select-text"><strong>' + esc(p.name) + '</strong>' +
							'<small>' + esc(p.nameEn) + '</small>' +
							'<small class="ssp-picker-title">' + esc(p.title) + '</small></span>' +
							(at ? '<span class="ssp-picker-badge">' + esc(fmtN(t.inTimetable, at.no)) + '</span>' : '') +
							'</label></li>';
					}).join('') + '</ul></section>';
			}).join('') +
			'</div>' +
			'<footer><span class="ssp-select-count"></span>' +
			'<button type="button" class="button ssp-select-cancel">' + esc(t.cancel || 'Cancel') + '</button>' +
			'<button type="button" class="button button-primary ssp-select-add" disabled></button></footer></form>';

		var dlg = document.createElement('dialog');
		dlg.className = 'ssp-select-dialog';
		dlg.innerHTML = html;
		document.body.appendChild(dlg);
		return dlg;
	}

	function updateSelectCount() {
		var n = $(selectDialog).find('li input:checked:not(:disabled)').length;
		$(selectDialog).find('.ssp-select-count').text(fmtN(t.nSelected, n));
		$(selectDialog).find('.ssp-select-add').prop('disabled', !n).text(fmtN(t.addN, n));
	}

	function openSelect() {
		closePicker();
		if (selectDialog) {
			selectDialog.remove();
		}
		selectDialog = buildSelectDialog();
		var $d = $(selectDialog);
		updateSelectCount();

		$d.on('change', 'li input', updateSelectCount);
		$d.on('change', '.ssp-select-all input', function () {
			$(this).closest('section').find('li:not([hidden]) input:not(:disabled)').prop('checked', this.checked);
			updateSelectCount();
		});
		$d.on('input', '.ssp-select-search', function () {
			var terms = this.value.toLowerCase().split(/\s+/).filter(Boolean);
			$d.find('li').each(function () {
				var hay = this.getAttribute('data-hay');
				this.hidden = !terms.every(function (term) {
					return hay.indexOf(term) !== -1;
				});
			});
		});
		$d.on('click', '.ssp-select-cancel', function () {
			selectDialog.close();
		});
		$d.on('click', '.ssp-select-add', function () {
			var ids = $d.find('li input:checked:not(:disabled)').map(function () {
				return parseInt(this.value, 10);
			}).get();
			// Fill empty talk slots first, then add slots after the last talk.
			ids.forEach(function (id) {
				var empty = schedule.items.filter(function (it) {
					return it.type === 'talk' && !it.presenter_id;
				})[0];
				if (empty) {
					empty.presenter_id = id;
					empty.label = '';
				} else {
					schedule.items.splice(lastTalkIndex() + 1, 0, newItem('talk', { presenter_id: id }));
				}
			});
			selectDialog.close();
			render();
			markDirty();
			$('.ssp-tt-dirty').text(fmtN(t.added, ids.length));
		});
		selectDialog.showModal();
		$d.find('.ssp-select-search').trigger('focus');
	}

	$(document).on('click', '.ssp-open-select', openSelect);

	$(window).on('beforeunload', function () {
		return dirty ? t.unsaved : undefined;
	});

	render();
})(jQuery);
