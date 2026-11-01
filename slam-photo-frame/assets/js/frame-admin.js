/* Frame edit screen: choose the frame image from the media library and check it has a transparent window. */
(function ($) {
	'use strict';

	var l = window.spfAdmin || {};
	var $box = $('.spf-admin-frame');
	var $id = $('#spf_image_id');
	var $img = $box.find('.spf-admin-preview img');
	var $note = $box.find('.spf-image-note');
	var $remove = $box.find('.spf-remove-image');
	var media;

	function setNote(text, warning) {
		$note.text(text || '').toggleClass('is-warning', !!warning);
	}

	// Same scan as the front end: the box around pixels that are more than half transparent.
	function check(url, mime) {
		if (mime && mime !== 'image/png') {
			setNote(l.notPng, true);
			return;
		}
		var img = new Image();
		img.onload = function () {
			var w = img.naturalWidth;
			var h = img.naturalHeight;
			var c = document.createElement('canvas');
			c.width = w;
			c.height = h;
			var cx = c.getContext('2d');
			cx.drawImage(img, 0, 0);
			var data;
			try {
				data = cx.getImageData(0, 0, w, h).data;
			} catch (e) {
				setNote('');
				return;
			}
			var minX = w, minY = h, maxX = -1, maxY = -1;
			for (var y = 0; y < h; y++) {
				for (var x = 0; x < w; x++) {
					if (data[(y * w + x) * 4 + 3] < 128) {
						if (x < minX) { minX = x; }
						if (x > maxX) { maxX = x; }
						if (y < minY) { minY = y; }
						if (y > maxY) { maxY = y; }
					}
				}
			}
			if (maxX < 0) {
				setNote(l.noWindow, true);
			} else {
				setNote(l.window.replace('%1$s', maxX - minX + 1).replace('%2$s', maxY - minY + 1).replace('%3$s', minX).replace('%4$s', minY));
			}
		};
		img.src = url;
	}

	$box.on('click', '.spf-choose-image', function (e) {
		e.preventDefault();
		if (!media) {
			media = wp.media({
				title: l.choose,
				button: { text: l.use },
				library: { type: 'image' },
				multiple: false
			});
			media.on('select', function () {
				var att = media.state().get('selection').first().toJSON();
				$id.val(att.id);
				$img.attr('src', att.url).prop('hidden', false);
				$remove.prop('hidden', false);
				check(att.url, att.mime);
			});
		}
		media.open();
	});

	$remove.on('click', function (e) {
		e.preventDefault();
		$id.val('');
		$img.attr('src', '').prop('hidden', true);
		$remove.prop('hidden', true);
		setNote('');
	});

	if ($img.attr('src')) {
		check($img.attr('src'));
	}
}(jQuery));
