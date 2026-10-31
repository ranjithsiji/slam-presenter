/* Presenter edit screen: papers repeater, photo focus point, fetch photo from Drive. */
(function ($) {
	'use strict';

	// Papers repeater.
	var $papers = $('.ssp-papers');
	var $template = $papers.find('.ssp-paper-template').detach().removeClass('ssp-paper-template');

	$papers.sortable({ handle: '.ssp-handle', axis: 'y' });

	$('.ssp-paper-add').on('click', function () {
		var next = parseInt($papers.attr('data-next'), 10) || 0;
		var $row = $template.clone();
		$row.find('input').each(function () {
			this.name = this.name.replace('__i__', next);
			this.value = '';
		});
		$papers.attr('data-next', next + 1).append($row);
		$row.find('.ssp-paper-title').trigger('focus');
	});

	$papers.on('click', '.ssp-paper-remove', function () {
		$(this).closest('.ssp-paper').remove();
	});

	// Photo cropper: drag/zoom writes the face point and zoom into the hidden fields saved with the post.
	var $host = $('.ssp-cropper-host');

	function mountCropper(crop, reset) {
		if (!crop || !$host.length) {
			return;
		}
		$('.ssp-cropper-empty').remove();
		if (reset) {
			crop.focus = '';
			crop.zoom = 1;
			$('#ssp_photo_focus').val('');
			$('#ssp_photo_zoom').val('');
		}
		$host.empty();
		new window.SSPCropper($host[0], $.extend({}, crop, {
			labels: sspEdit.cropper,
			onChange: function (focus, zoom) {
				$('#ssp_photo_focus').val(focus);
				$('#ssp_photo_zoom').val(zoom > 1 ? zoom : '');
			}
		}));
	}

	mountCropper($host.data('crop'));

	// Keep the cropper in sync when the photo is changed with "Set presenter photo".
	if (window.wp && wp.media && wp.media.featuredImage) {
		var frame = wp.media.featuredImage.frame();
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var src = a.sizes && a.sizes.large ? a.sizes.large.url : a.url;
			mountCropper({ src: src, width: a.width, height: a.height }, true);
		});
	}

	$('#ssp_photo_drive_url').on('input', function () {
		$('.ssp-fetch-photo').prop('disabled', !this.value);
	});

	$('.ssp-fetch-photo').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		var $status = $('.ssp-fetch-status').removeClass('is-error').text(sspEdit.i18n.fetching);
		$.post(sspEdit.ajaxUrl, {
			action: 'ssp_fetch_photo',
			nonce: sspEdit.nonce,
			post_id: $btn.data('post'),
			drive_url: $('#ssp_photo_drive_url').val(),
			force: 1
		}).done(function (res) {
			if (res.success) {
				$status.text('');
				mountCropper(res.data.crop, true);
				if (window.wp && wp.media && wp.media.featuredImage) {
					wp.media.featuredImage.set(res.data.attachment);
				}
			} else {
				$status.addClass('is-error').text(res.data.message);
			}
		}).fail(function () {
			$status.addClass('is-error').text('Request failed.');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});
})(jQuery);
