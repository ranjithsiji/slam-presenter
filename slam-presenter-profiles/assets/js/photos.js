/* Photos page: fetch Drive photos one presenter at a time with a progress bar. */
(function ($) {
	'use strict';

	var $grid = $('.ssp-photo-grid');
	var running = false;

	function fetchOne(postId, force, driveUrl) {
		var $card = $grid.find('.ssp-photo-card[data-post="' + postId + '"]');
		var $status = $card.find('.ssp-photo-status').removeClass('is-error').text('…');
		$card.addClass('is-busy');
		return $.post(sspPhotos.ajaxUrl, {
			action: 'ssp_fetch_photo',
			nonce: sspPhotos.nonce,
			post_id: postId,
			force: force ? 1 : 0,
			drive_url: driveUrl
		}).then(function (res) {
			$card.removeClass('is-busy');
			if (res.success) {
				$card.removeClass('no-photo').addClass('has-photo');
				$card.find('.ssp-photo-frame').html(res.data.html);
				$card.data('crop', res.data.crop).find('.ssp-adjust').prop('hidden', false);
				$status.text('✓');
				return true;
			}
			$status.addClass('is-error').text(res.data.message);
			return false;
		}, function () {
			$card.removeClass('is-busy');
			$status.addClass('is-error').text('Request failed.');
			return $.Deferred().resolve(false);
		});
	}

	function runQueue(ids, force) {
		if (running || !ids.length) {
			return;
		}
		running = true;
		$('.ssp-fetch-all').prop('disabled', true);
		var $progress = $('.ssp-progress').prop('hidden', false);
		var $bar = $progress.find('.ssp-progress-bar');
		var $text = $progress.find('.ssp-progress-text');
		var done = 0;
		var ok = 0;

		function next() {
			if (done >= ids.length) {
				running = false;
				$('.ssp-fetch-all').prop('disabled', false);
				$text.text(ok + ' / ' + ids.length + ' fetched' + (ok < ids.length ? ' – see the red notes below' : ''));
				return;
			}
			$text.text((done + 1) + ' / ' + ids.length);
			fetchOne(ids[done], force).then(function (success) {
				done++;
				if (success) {
					ok++;
				}
				$bar.css('width', (done / ids.length * 100) + '%');
				next();
			});
		}
		next();
	}

	$('.ssp-fetch-all').on('click', function () {
		var all = $(this).data('mode') === 'all';
		var ids = $grid.find('.ssp-photo-card[data-drive="1"]' + (all ? '' : '.no-photo')).map(function () {
			return $(this).data('post');
		}).get();
		runQueue(ids, all);
	});

	$grid.on('click', '.ssp-fetch-one', function () {
		fetchOne($(this).closest('.ssp-photo-card').data('post'), true);
	});

	// Edit a presenter's Drive link in place, then fetch from it.
	$grid.on('click', '.ssp-edit-drive', function () {
		var $card = $(this).closest('.ssp-photo-card');
		$card.find('.ssp-drive-edit').prop('hidden', false).find('input').trigger('focus');
	});
	$grid.on('click', '.ssp-drive-cancel', function () {
		$(this).closest('.ssp-drive-edit').prop('hidden', true);
	});
	$grid.on('click', '.ssp-drive-save', function () {
		var $card = $(this).closest('.ssp-photo-card');
		var url = $.trim($card.find('.ssp-drive-input').val());
		if (!url) {
			return;
		}
		$card.attr('data-drive', '1').data('drive', 1);
		$card.find('.ssp-drive-edit').prop('hidden', true);
		fetchOne($card.data('post'), true, url);
	});
	$grid.on('keydown', '.ssp-drive-input', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			$(this).siblings('.ssp-drive-save').trigger('click');
		}
	});

	// Adjust: crop a photo in a dialog and save it straight away.
	var dialog = document.querySelector('.ssp-crop-dialog');
	var cropper = null;
	var $current = null;

	$grid.on('click', '.ssp-adjust', function () {
		$current = $(this).closest('.ssp-photo-card');
		var crop = $current.data('crop');
		if (!crop || !dialog) {
			return;
		}
		$(dialog).find('h2').text($current.data('name'));
		$(dialog).find('.ssp-crop-dialog-status').text('');
		var host = dialog.querySelector('.ssp-crop-dialog-host');
		host.innerHTML = '';
		cropper = new window.SSPCropper(host, $.extend({}, crop, { labels: sspPhotos.cropper }));
		dialog.showModal();
		host.querySelector('.ssp-cropper-view').focus();
	});

	$(dialog).on('click', '.ssp-crop-cancel', function () {
		dialog.close();
	});

	$(dialog).on('click', '.ssp-crop-save', function () {
		var $btn = $(this).prop('disabled', true);
		var $status = $(dialog).find('.ssp-crop-dialog-status').text(sspPhotos.i18n.saving);
		var focus = cropper.focus();
		var zoom = cropper.zoomValue();
		$.post(sspPhotos.ajaxUrl, {
			action: 'ssp_save_crop',
			nonce: sspPhotos.nonce,
			post_id: $current.data('post'),
			focus: focus,
			zoom: zoom > 1 ? zoom : ''
		}).done(function (res) {
			if (res.success) {
				$current.find('.ssp-photo-frame').html(res.data.html);
				$current.data('crop', $.extend({}, $current.data('crop'), { focus: focus, zoom: zoom }));
				dialog.close();
			} else {
				$status.text(res.data.message);
			}
		}).fail(function () {
			$status.text('Request failed.');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	// Queue handed over by the CSV import.
	var queue = $grid.data('queue') || [];
	if ($grid.data('autostart') === 1 && queue.length) {
		runQueue(queue, false);
	}
})(jQuery);
