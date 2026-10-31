/* Presenter Table: instant search, filters, sorting, paging, quick edit and bulk actions. */
(function () {
	'use strict';

	var cfg = window.sspTable;
	if (!cfg) {
		return;
	}
	var t = cfg.i18n;
	var rows = cfg.rows;
	var eventNames = {};
	cfg.events.forEach(function (e) {
		eventNames[e.id] = e.name;
	});

	var state = { q: '', event: '', photo: '', status: '', sort: 'title', dir: 1, page: 1, per: 50, editing: 0, selected: {} };
	var $ = function (id) {
		return document.getElementById(id);
	};
	var body = $('ssp-dt-body');

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	function fmt(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return str.replace(/%(\d\$)?d/g, function (m, pos) {
			return pos ? args[parseInt(pos, 10) - 1] : args[i++];
		});
	}

	function completeness(r) {
		var checks = [r.thumb, r.name_en, r.institution, r.talk_title, r.hasBio, r.links, r.papers, r.events.length];
		return checks.filter(Boolean).length / checks.length;
	}

	function haystack(r) {
		return [r.title, r.name_en, r.institution, r.designation, r.talk_title, r.award, r.events.map(function (id) {
			return eventNames[id];
		}).join(' ')].join(' ').toLowerCase();
	}

	function sortValue(r) {
		switch (state.sort) {
			case 'events':
				return r.events.map(function (id) {
					return eventNames[id] || '';
				}).join(',');
			case 'slot':
				return r.slots.length ? r.slots[0].sort : 99999;
			case 'completeness':
				return completeness(r);
			default:
				return (r[state.sort] || '').toString().toLowerCase();
		}
	}

	function filtered() {
		var terms = state.q.toLowerCase().split(/\s+/).filter(Boolean);
		var list = rows.filter(function (r) {
			if (state.event && r.events.indexOf(parseInt(state.event, 10)) === -1) {
				return false;
			}
			if (state.photo === 'yes' && !r.thumb) {
				return false;
			}
			if (state.photo === 'no' && r.thumb) {
				return false;
			}
			if (state.status && r.status !== state.status) {
				return false;
			}
			var hay = haystack(r);
			return terms.every(function (term) {
				return hay.indexOf(term) !== -1;
			});
		});
		list.sort(function (a, b) {
			var va = sortValue(a);
			var vb = sortValue(b);
			if (va < vb) {
				return -state.dir;
			}
			return va > vb ? state.dir : 0;
		});
		return list;
	}

	function photoCell(r) {
		if (r.thumb) {
			return '<img class="ssp-list-thumb" src="' + esc(r.thumb) + '" alt="">';
		}
		var title = r.photoError || (r.drive ? t.driveOnly : t.noPhoto);
		return '<span class="ssp-list-thumb ssp-no-thumb' + (r.drive ? ' has-drive' : '') + '" title="' + esc(title) + '">' + (r.drive ? '⇣' : '?') + '</span>';
	}

	function viewRow(r) {
		var pct = Math.round(completeness(r) * 100);
		var events = r.events.map(function (id) {
			return '<span class="ssp-tag">' + esc(eventNames[id] || id) + '</span>';
		}).join(' ');
		var slots = r.slots.map(function (s) {
			return '<span class="ssp-slot-badge" title="' + esc(eventNames[s.event]) + '">#' + s.no + ' · ' + esc(s.time) + '</span>';
		}).join(' ') || '<span class="ssp-muted">' + esc(t.notSched) + '</span>';
		return '<tr data-id="' + r.id + '" class="' + (state.selected[r.id] ? 'is-selected' : '') + '">' +
			'<th class="check-column"><input type="checkbox" class="ssp-dt-check"' + (state.selected[r.id] ? ' checked' : '') + '></th>' +
			'<td class="ssp-dt-photo-col">' + photoCell(r) + '</td>' +
			'<td class="ssp-dt-name"><a class="row-title" href="' + esc(r.editUrl) + '">' + esc(r.title) + '</a>' +
				(r.status !== 'publish' ? ' <span class="ssp-status">— ' + esc(t.draft) + '</span>' : '') +
				'<div class="ssp-muted">' + esc(r.name_en) + '</div>' +
				'<div class="row-actions"><a href="' + esc(r.editUrl) + '">' + esc(t.edit) + '</a> | ' +
				'<button type="button" class="button-link ssp-dt-quick">' + esc(t.quickEdit) + '</button> | ' +
				'<a href="' + esc(r.viewUrl) + '" target="_blank">' + esc(t.view) + '</a></div></td>' +
			'<td>' + esc(r.institution) + (r.designation ? '<div class="ssp-muted">' + esc(r.designation) + '</div>' : '') + '</td>' +
			'<td>' + esc(r.talk_title) + (r.award ? ' <span class="ssp-award-tag">' + esc(r.award) + '</span>' : '') + '</td>' +
			'<td>' + events + '</td>' +
			'<td>' + slots + '</td>' +
			'<td><span class="ssp-meter" title="' + pct + '%"><span style="width:' + pct + '%"></span></span>' +
				'<div class="ssp-muted ssp-dt-counts">' + r.links + ' ' + esc(t.links) + ' · ' + r.papers + ' ' + esc(t.papers) + (r.hasBio ? ' · ' + esc(t.bio) : '') + '</div></td>' +
			'</tr>';
	}

	function input(name, label, value) {
		return '<label><span>' + esc(label) + '</span><input type="text" name="' + name + '" value="' + esc(value) + '"></label>';
	}

	function editRow(r) {
		var events = cfg.events.map(function (e) {
			return '<label class="ssp-inline-check"><input type="checkbox" name="events" value="' + e.id + '"' + (r.events.indexOf(e.id) !== -1 ? ' checked' : '') + '> ' + esc(e.name) + '</label>';
		}).join('');
		return '<tr data-id="' + r.id + '" class="ssp-dt-editing"><td colspan="8"><form class="ssp-quick-form">' +
			'<div class="ssp-quick-grid">' +
			input('title', t.displayName, r.title) +
			input('name_en', t.nameEn, r.name_en) +
			input('institution', t.institution, r.institution) +
			input('designation', t.designation, r.designation) +
			'<label class="ssp-quick-wide"><span>' + esc(t.talkTitle) + '</span><input type="text" name="talk_title" value="' + esc(r.talk_title) + '"></label>' +
			input('award', t.award, r.award) +
			'<label><span>' + esc(t.status) + '</span><select name="status"><option value="publish"' + (r.status === 'publish' ? ' selected' : '') + '>' + esc(t.published) + '</option><option value="draft"' + (r.status !== 'publish' ? ' selected' : '') + '>' + esc(t.draft) + '</option></select></label>' +
			'<fieldset class="ssp-quick-wide"><legend>' + esc(t.events) + '</legend>' + events + '</fieldset>' +
			'</div><p><button type="submit" class="button button-primary">' + esc(t.save) + '</button> <button type="button" class="button ssp-quick-cancel">' + esc(t.cancel) + '</button> <span class="ssp-quick-msg"></span></p>' +
			'</form></td></tr>';
	}

	function renderStats() {
		var withPhoto = rows.filter(function (r) {
			return r.thumb;
		}).length;
		var scheduled = rows.filter(function (r) {
			return r.slots.length;
		}).length;
		var stats = [
			[rows.length, 'Presenters'],
			[cfg.events.length, 'Events'],
			[withPhoto + '/' + rows.length, 'With photo'],
			[scheduled + '/' + rows.length, 'Scheduled']
		];
		$('ssp-stats').innerHTML = stats.map(function (s) {
			return '<div><strong>' + esc(s[0]) + '</strong><span>' + esc(s[1]) + '</span></div>';
		}).join('');
	}

	function render() {
		var list = filtered();
		var per = state.per || list.length || 1;
		var pages = Math.max(1, Math.ceil(list.length / per));
		state.page = Math.min(state.page, pages);
		var from = (state.page - 1) * per;
		var pageRows = list.slice(from, from + per);

		body.innerHTML = pageRows.length ? pageRows.map(function (r) {
			return r.id === state.editing ? editRow(r) : viewRow(r);
		}).join('') : '<tr><td colspan="8" class="ssp-dt-empty">' + esc(t.noResults) + '</td></tr>';

		$('ssp-dt-info').textContent = list.length ? fmt(t.showing, from + 1, from + pageRows.length, list.length) : '';
		var pager = '';
		if (pages > 1) {
			for (var p = 1; p <= pages; p++) {
				pager += '<button type="button" class="button' + (p === state.page ? ' button-primary' : '') + '" data-page="' + p + '">' + p + '</button>';
			}
		}
		$('ssp-dt-pager').innerHTML = pager;

		document.querySelectorAll('#ssp-dt th[data-sort]').forEach(function (th) {
			th.classList.toggle('is-sorted', th.dataset.sort === state.sort);
			th.classList.toggle('is-desc', th.dataset.sort === state.sort && state.dir < 0);
		});
		var count = Object.keys(state.selected).length;
		$('ssp-dt-bulk').hidden = !count;
		$('ssp-dt-selected').textContent = fmt(t.selected, count);
		$('ssp-dt-all').checked = pageRows.length > 0 && pageRows.every(function (r) {
			return state.selected[r.id];
		});
		renderStats();
	}

	function ajax(data) {
		var form = new FormData();
		form.append('nonce', cfg.nonce);
		Object.keys(data).forEach(function (k) {
			form.append(k, data[k]);
		});
		return fetch(cfg.ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) {
			return r.json();
		});
	}

	function replaceRows(updated) {
		updated.forEach(function (u) {
			for (var i = 0; i < rows.length; i++) {
				if (rows[i].id === u.id) {
					rows[i] = u;
				}
			}
		});
	}

	// Toolbar.
	cfg.events.forEach(function (e) {
		var opt = document.createElement('option');
		opt.value = e.id;
		opt.textContent = e.name;
		$('ssp-dt-event').appendChild(opt);
		$('ssp-dt-bulk-event').appendChild(opt.cloneNode(true));
	});

	try {
		var saved = JSON.parse(window.localStorage.getItem('sspTableState') || '{}');
		['event', 'photo', 'status', 'sort', 'dir', 'per'].forEach(function (k) {
			if (saved[k] !== undefined) {
				state[k] = saved[k];
			}
		});
	} catch (e) {}
	$('ssp-dt-event').value = state.event;
	$('ssp-dt-photo').value = state.photo;
	$('ssp-dt-status').value = state.status;
	$('ssp-dt-per').value = String(state.per);

	function remember() {
		try {
			window.localStorage.setItem('sspTableState', JSON.stringify({ event: state.event, photo: state.photo, status: state.status, sort: state.sort, dir: state.dir, per: state.per }));
		} catch (e) {}
	}

	$('ssp-dt-search').addEventListener('input', function () {
		state.q = this.value;
		state.page = 1;
		render();
	});
	[['ssp-dt-event', 'event'], ['ssp-dt-photo', 'photo'], ['ssp-dt-status', 'status']].forEach(function (pair) {
		$(pair[0]).addEventListener('change', function () {
			state[pair[1]] = this.value;
			state.page = 1;
			remember();
			render();
		});
	});
	$('ssp-dt-per').addEventListener('change', function () {
		state.per = parseInt(this.value, 10);
		state.page = 1;
		remember();
		render();
	});

	document.querySelector('#ssp-dt thead').addEventListener('click', function (e) {
		var th = e.target.closest('th[data-sort]');
		if (!th) {
			return;
		}
		state.dir = state.sort === th.dataset.sort ? -state.dir : 1;
		state.sort = th.dataset.sort;
		remember();
		render();
	});

	$('ssp-dt-pager').addEventListener('click', function (e) {
		if (e.target.dataset.page) {
			state.page = parseInt(e.target.dataset.page, 10);
			render();
		}
	});

	$('ssp-dt-all').addEventListener('change', function () {
		var checked = this.checked;
		body.querySelectorAll('tr[data-id]').forEach(function (tr) {
			var id = parseInt(tr.dataset.id, 10);
			if (checked) {
				state.selected[id] = true;
			} else {
				delete state.selected[id];
			}
		});
		render();
	});

	body.addEventListener('change', function (e) {
		if (e.target.classList.contains('ssp-dt-check')) {
			var id = parseInt(e.target.closest('tr').dataset.id, 10);
			if (e.target.checked) {
				state.selected[id] = true;
			} else {
				delete state.selected[id];
			}
			render();
		}
	});

	body.addEventListener('click', function (e) {
		if (e.target.classList.contains('ssp-dt-quick')) {
			state.editing = parseInt(e.target.closest('tr').dataset.id, 10);
			render();
			var first = body.querySelector('.ssp-quick-form input');
			if (first) {
				first.focus();
			}
		}
		if (e.target.classList.contains('ssp-quick-cancel')) {
			state.editing = 0;
			render();
		}
	});

	body.addEventListener('submit', function (e) {
		e.preventDefault();
		var form = e.target;
		var data = { action: 'ssp_quick_save', id: state.editing };
		['title', 'name_en', 'institution', 'designation', 'talk_title', 'award', 'status'].forEach(function (k) {
			data[k] = form.elements[k].value;
		});
		data.events = Array.prototype.map.call(form.querySelectorAll('input[name=events]:checked'), function (c) {
			return c.value;
		}).join(',');
		form.querySelector('.ssp-quick-msg').textContent = t.saving;
		ajax(data).then(function (res) {
			if (res.success) {
				replaceRows([res.data.row]);
				state.editing = 0;
				render();
			} else {
				form.querySelector('.ssp-quick-msg').textContent = res.data.message;
			}
		});
	});

	body.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && state.editing) {
			state.editing = 0;
			render();
		}
	});

	$('ssp-dt-bulk-action').addEventListener('change', function () {
		$('ssp-dt-bulk-event').hidden = ['add_event', 'remove_event'].indexOf(this.value) === -1;
	});

	$('ssp-dt-bulk-apply').addEventListener('click', function () {
		var action = $('ssp-dt-bulk-action').value;
		var ids = Object.keys(state.selected);
		if (!action || !ids.length || (action === 'trash' && !window.confirm(t.confirmDel))) {
			return;
		}
		var btn = this;
		btn.disabled = true;
		ajax({ action: 'ssp_bulk', bulk: action, ids: ids.join(','), event: $('ssp-dt-bulk-event').value }).then(function (res) {
			btn.disabled = false;
			if (!res.success) {
				return;
			}
			if (action === 'trash') {
				rows = rows.filter(function (r) {
					return !state.selected[r.id];
				});
			} else {
				replaceRows(res.data.rows);
			}
			state.selected = {};
			render();
		});
	});

	render();
})();
