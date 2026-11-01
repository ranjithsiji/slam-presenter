/* Frame maker: puts a photo (camera or gallery) behind the frame PNG, exports a JPEG and shares it.
 * The page shows the frame and a "Choose your photo" button; the photo is fitted in a full-screen editor.
 * Everything happens in the browser; only a use count is sent to the server. */
(function () {
	'use strict';

	var MAX_SOURCE = 2400; // Longest side kept from a photo, enough for any frame and kind to phone memory.
	var MIN_ZOOM = 0.5; // Zoom is relative to the size that just covers the frame's window.
	var MAX_ZOOM = 6;
	var BLUR_SIZE = 24; // The blurred background is the photo drawn this small, then stretched.

	function clamp(v, min, max) {
		return Math.min(max, Math.max(min, v));
	}

	function sameOrigin(url) {
		try {
			return new URL(url, window.location.href).origin === window.location.origin;
		} catch (e) {
			return true;
		}
	}

	function Maker(el) {
		var self = this;
		this.el = el;
		this.cfg = JSON.parse(el.getAttribute('data-config'));
		this.l = this.cfg.labels;
		this.canvas = el.querySelector('.spf-canvas');
		this.ctx = this.canvas.getContext('2d');
		this.preview = el.querySelector('.spf-frame-preview');
		this.result = el.querySelector('.spf-result');
		this.note = el.querySelector('.spf-note');
		this.zoomInput = el.querySelector('[data-action=zoom]');
		this.blurInput = el.querySelector('[data-action=blur]');
		this.sheet = el.querySelector('.spf-sheet');
		this.editor = el.querySelector('.spf-editor');
		this.dialog = el.querySelector('.spf-camera');
		this.video = this.dialog.querySelector('video');
		this.photo = null;
		this.blurred = null;
		this.state = null;
		this.file = null;
		this.counted = false;
		this.dragging = false;
		this.facing = 'user';

		this.frame = new Image();
		if (!sameOrigin(this.cfg.image)) {
			this.frame.crossOrigin = 'anonymous'; // Otherwise the canvas is tainted and cannot be exported.
		}
		this.frame.onload = function () {
			self.canvas.width = self.frame.naturalWidth;
			self.canvas.height = self.frame.naturalHeight;
			self.hole = self.findWindow();
		};
		this.frame.src = this.cfg.image;

		this.bindButtons();
		this.bindGestures();
		this.bindCamera();
	}

	/**
	 * Bounding box of the transparent part of the frame: where the photo shows. The whole frame if there is none.
	 */
	Maker.prototype.findWindow = function () {
		var w = this.canvas.width;
		var h = this.canvas.height;
		var full = { x: 0, y: 0, w: w, h: h };
		var step = Math.max(1, Math.round(Math.max(w, h) / 400)); // Scan a ~400 px copy, it is only a box.
		var sw = Math.ceil(w / step);
		var sh = Math.ceil(h / step);
		var c = document.createElement('canvas');
		c.width = sw;
		c.height = sh;
		var cx = c.getContext('2d');
		cx.drawImage(this.frame, 0, 0, sw, sh);
		var data;
		try {
			data = cx.getImageData(0, 0, sw, sh).data;
		} catch (e) {
			return full;
		}
		var minX = sw, minY = sh, maxX = -1, maxY = -1;
		for (var y = 0; y < sh; y++) {
			for (var x = 0; x < sw; x++) {
				if (data[(y * sw + x) * 4 + 3] < 128) {
					if (x < minX) { minX = x; }
					if (x > maxX) { maxX = x; }
					if (y < minY) { minY = y; }
					if (y > maxY) { maxY = y; }
				}
			}
		}
		if (maxX < 0) {
			return full;
		}
		return { x: minX * step, y: minY * step, w: (maxX - minX + 1) * step, h: (maxY - minY + 1) * step };
	};

	// Photo scale at zoom 1: just covers the window, taking the rotation into account.
	Maker.prototype.baseScale = function () {
		var turned = this.state.rot % 2 === 1;
		var pw = turned ? this.photo.height : this.photo.width;
		var ph = turned ? this.photo.width : this.photo.height;
		return Math.max(this.hole.w / pw, this.hole.h / ph);
	};

	// The photo, transformed by the current state (centre, zoom, quarter turns, mirror).
	Maker.prototype.drawPhoto = function (source, scale) {
		var ctx = this.ctx;
		ctx.save();
		ctx.translate(this.state.x, this.state.y);
		ctx.scale(this.state.flip ? -1 : 1, 1);
		ctx.rotate(this.state.rot * Math.PI / 2);
		ctx.scale(scale, scale);
		ctx.drawImage(source, -source.width / 2, -source.height / 2);
		ctx.restore();
	};

	/**
	 * @param {boolean} final For the exported picture: no editing aids (faded frame, grid).
	 */
	Maker.prototype.draw = function (final) {
		var ctx = this.ctx;
		var w = this.canvas.width;
		var h = this.canvas.height;
		var b = this.hole;
		ctx.fillStyle = '#fff';
		ctx.fillRect(0, 0, w, h);
		if (!this.photo) {
			return;
		}
		ctx.imageSmoothingEnabled = true;
		ctx.imageSmoothingQuality = 'high';
		if (this.state.blur) {
			// Blurred copy of the photo filling the window, for the parts the photo itself does not cover.
			var turned = this.state.rot % 2 === 1;
			var bw = turned ? this.blurred.height : this.blurred.width;
			var bh = turned ? this.blurred.width : this.blurred.height;
			var keep = { x: this.state.x, y: this.state.y };
			this.state.x = b.x + b.w / 2;
			this.state.y = b.y + b.h / 2;
			this.drawPhoto(this.blurred, Math.max(b.w / bw, b.h / bh) * 1.1);
			this.state.x = keep.x;
			this.state.y = keep.y;
		}
		this.drawPhoto(this.photo, this.baseScale() * this.state.zoom);

		if (!final && this.dragging) {
			// While moving: the frame fades so the whole photo shows, with a rule-of-thirds grid over the window.
			ctx.globalAlpha = 0.35;
			ctx.drawImage(this.frame, 0, 0, w, h);
			ctx.globalAlpha = 1;
			ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
			ctx.lineWidth = Math.max(1, w / 600);
			ctx.beginPath();
			for (var i = 1; i < 3; i++) {
				ctx.moveTo(b.x + b.w * i / 3, b.y);
				ctx.lineTo(b.x + b.w * i / 3, b.y + b.h);
				ctx.moveTo(b.x, b.y + b.h * i / 3);
				ctx.lineTo(b.x + b.w, b.y + b.h * i / 3);
			}
			ctx.stroke();
		} else {
			ctx.drawImage(this.frame, 0, 0, w, h);
		}
	};

	Maker.prototype.reset = function () {
		this.state.zoom = 1;
		this.state.x = this.hole.x + this.hole.w / 2;
		this.state.y = this.hole.y + this.hole.h / 2;
		this.update();
	};

	Maker.prototype.update = function () {
		var t = Math.log(this.state.zoom / MIN_ZOOM) / Math.log(MAX_ZOOM / MIN_ZOOM);
		this.zoomInput.value = t;
		this.zoomInput.setAttribute('aria-valuetext', Math.round(this.state.zoom * 100) + '%');
		this.draw();
	};

	// Zoom keeping the canvas point (px, py) where it is.
	Maker.prototype.zoomAt = function (zoom, px, py) {
		var st = this.state;
		zoom = clamp(zoom, MIN_ZOOM, MAX_ZOOM);
		var k = zoom / st.zoom;
		st.x = px - (px - st.x) * k;
		st.y = py - (py - st.y) * k;
		st.zoom = zoom;
		this.moveBy(0, 0);
	};

	Maker.prototype.moveBy = function (dx, dy) {
		var st = this.state;
		st.x = clamp(st.x + dx, 0, this.canvas.width);
		st.y = clamp(st.y + dy, 0, this.canvas.height);
		this.update();
	};

	// Canvas pixels per CSS pixel and the canvas position of a pointer.
	Maker.prototype.toCanvas = function (clientX, clientY) {
		var rect = this.canvas.getBoundingClientRect();
		var k = this.canvas.width / rect.width;
		return { x: (clientX - rect.left) * k, y: (clientY - rect.top) * k, k: k };
	};

	Maker.prototype.setPhoto = function (source, width, height, flip) {
		var k = Math.min(1, MAX_SOURCE / Math.max(width, height));
		var c = document.createElement('canvas');
		c.width = Math.round(width * k);
		c.height = Math.round(height * k);
		c.getContext('2d').drawImage(source, 0, 0, c.width, c.height);
		this.photo = c;

		var small = document.createElement('canvas');
		var ks = BLUR_SIZE / Math.max(c.width, c.height);
		small.width = Math.max(1, Math.round(c.width * ks));
		small.height = Math.max(1, Math.round(c.height * ks));
		small.getContext('2d').drawImage(c, 0, 0, small.width, small.height);
		this.blurred = small;

		this.state = { zoom: 1, x: 0, y: 0, rot: 0, flip: !!flip, blur: this.blurInput.checked };
		this.counted = false;
		this.reset();
		this.openEditor();
	};

	Maker.prototype.loadFile = function (file) {
		var self = this;
		if (!file) {
			return;
		}
		var url = URL.createObjectURL(file);
		var img = new Image();
		img.onload = function () {
			// Browsers apply the EXIF orientation, so phone photos come in upright.
			self.setPhoto(img, img.naturalWidth, img.naturalHeight, false);
			URL.revokeObjectURL(url);
		};
		img.onerror = function () {
			URL.revokeObjectURL(url);
		};
		img.src = url;
	};

	Maker.prototype.openSheet = function () {
		if (!this.sheet.open) {
			this.sheet.showModal();
		}
	};

	Maker.prototype.closeSheet = function () {
		if (this.sheet.open) {
			this.sheet.close();
		}
	};

	Maker.prototype.openEditor = function () {
		if (!this.editor.open) {
			this.editor.showModal();
			document.documentElement.classList.add('spf-no-scroll');
		}
		this.draw();
	};

	Maker.prototype.closeEditor = function () {
		if (this.editor.open) {
			this.editor.close();
		}
	};

	Maker.prototype.showStep = function (name) {
		Array.prototype.forEach.call(this.el.querySelectorAll('.spf-step'), function (step) {
			step.hidden = step.getAttribute('data-step') !== name;
		});
		this.el.setAttribute('data-step', name);
		var sharing = name === 'share';
		this.result.hidden = !sharing;
		this.preview.hidden = sharing;
		this.setNote('');
	};

	Maker.prototype.setNote = function (text) {
		this.note.textContent = text;
		this.note.hidden = !text;
	};

	Maker.prototype.finish = function () {
		var self = this;
		this.draw(true);
		try {
			this.canvas.toBlob(function (blob) {
				self.draw();
				if (!blob) {
					return;
				}
				// A random number per picture, so saving several does not overwrite or rename earlier ones.
				var name = (self.cfg.fileName || 'photo-frame') + '-' + Math.floor(100000 + Math.random() * 900000) + '.jpg';
				self.file = new File([blob], name, { type: 'image/jpeg' });
				if (self.result.src) {
					URL.revokeObjectURL(self.result.src);
				}
				self.result.src = URL.createObjectURL(blob);
				self.showShareOptions();
				self.showStep('share');
				self.closeEditor();
				self.el.querySelector('.spf-stage').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}, 'image/jpeg', 0.92);
		} catch (e) {
			// A tainted canvas (frame on another domain without CORS) cannot be exported.
			window.console && window.console.error(e);
		}
	};

	Maker.prototype.canShareFile = function () {
		return !!(this.file && navigator.canShare && navigator.canShare({ files: [this.file] }));
	};

	Maker.prototype.count = function () {
		if (this.counted || !window.fetch) {
			return;
		}
		this.counted = true;
		fetch(this.cfg.useUrl, { method: 'POST', keepalive: true, credentials: 'omit' }).catch(function () {});
		var count = this.el.querySelector('.spf-count');
		if (count && this.l.count) {
			this.cfg.uses = (this.cfg.uses || 0) + 1;
			count.querySelector('span').textContent = this.l.count.replace('%s', this.cfg.uses.toLocaleString());
			count.hidden = false;
		}
	};

	// The caption sent with the picture: share text and the frame link.
	Maker.prototype.caption = function () {
		return [this.cfg.shareText, this.cfg.url].filter(Boolean).join(' ');
	};

	/**
	 * "Share picture" only where the browser can share the image file itself; otherwise Download is the main button.
	 */
	Maker.prototype.showShareOptions = function () {
		var canShare = this.canShareFile();
		var download = this.el.querySelector('[data-step=share] [data-action=download]');
		this.el.querySelector('[data-step=share] [data-action=share]').hidden = !canShare;
		download.classList.toggle('spf-btn-primary', !canShare);
		this.el.querySelector('[data-action=share-link]').hidden = !navigator.share;
	};

	/**
	 * The browser's share menu with the picture alone: apps such as WhatsApp, Facebook and Telegram drop the image
	 * when text comes with it. The caption goes to the clipboard instead, to paste in the post.
	 */
	Maker.prototype.share = function () {
		var self = this;
		if (!this.canShareFile()) {
			this.download();
			return;
		}
		var copied = this.copyText(this.caption()); // Started within the tap, before the share menu takes focus.
		navigator.share({ files: [this.file] }).then(function () {
			self.count();
			copied.then(function () {
				self.setNote(self.l.caption_copied);
			}, function () {});
		}).catch(function (e) {
			if (e && e.name !== 'AbortError') {
				self.download();
			}
		});
	};

	Maker.prototype.download = function () {
		var a = document.createElement('a');
		a.href = this.result.src;
		a.download = this.file.name;
		document.body.appendChild(a);
		a.click();
		a.remove();
		this.count();
		this.setNote(this.l.saved);
	};

	// The frame link through the browser's share menu (any app), for inviting friends.
	Maker.prototype.shareLink = function () {
		navigator.share({ title: this.cfg.title, text: this.cfg.shareText, url: this.cfg.url }).catch(function () {});
	};

	/**
	 * Copies text; resolves when done. The Clipboard API needs HTTPS, so falls back to a selected textarea.
	 */
	Maker.prototype.copyText = function (text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		var tmp = document.createElement('textarea');
		tmp.value = text;
		tmp.setAttribute('readonly', '');
		tmp.style.position = 'fixed';
		tmp.style.opacity = '0';
		this.el.appendChild(tmp);
		tmp.select();
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {}
		tmp.remove();
		return ok ? Promise.resolve() : Promise.reject();
	};

	Maker.prototype.copy = function (text, note) {
		var self = this;
		this.copyText(text).then(function () {
			self.setNote(note);
		}, function () {
			window.prompt('', text);
		});
	};

	Maker.prototype.bindButtons = function () {
		var self = this;
		var gallery = this.el.querySelector('[data-input=gallery]');
		var camera = this.el.querySelector('[data-input=camera]');
		[gallery, camera].forEach(function (input) {
			input.addEventListener('change', function () {
				self.loadFile(input.files[0]);
				input.value = '';
			});
		});
		this.cameraInput = camera;

		this.el.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-action]');
			if (!btn || !self.el.contains(btn)) {
				return;
			}
			switch (btn.getAttribute('data-action')) {
				case 'start':
					self.openSheet();
					break;
				case 'choose':
					self.closeSheet();
					gallery.click();
					break;
				case 'take':
					self.closeSheet();
					self.takePhoto();
					break;
				case 'rotate':
					self.state.rot = (self.state.rot + 1) % 4;
					self.update();
					break;
				case 'rotate-left':
					self.state.rot = (self.state.rot + 3) % 4;
					self.update();
					break;
				case 'zoom-in':
				case 'zoom-out':
					self.zoomAt(self.state.zoom * (btn.getAttribute('data-action') === 'zoom-in' ? 1.2 : 1 / 1.2), self.hole.x + self.hole.w / 2, self.hole.y + self.hole.h / 2);
					break;
				case 'flip':
					self.state.flip = !self.state.flip;
					self.update();
					break;
				case 'reset':
					self.reset();
					break;
				case 'close':
					self.closeEditor();
					break;
				case 'done':
					self.finish();
					break;
				case 'edit':
					self.openEditor();
					break;
				case 'share':
					self.share();
					break;
				case 'download':
					self.download();
					break;
				case 'copy-caption':
					self.copy(self.caption(), self.l.caption_copied);
					break;
				case 'share-link':
					self.shareLink();
					break;
				case 'copy':
					self.copy(self.cfg.url, self.l.copied);
					break;
			}
		});

		// Tapping the dimmed backdrop closes the photo source sheet.
		this.sheet.addEventListener('click', function (e) {
			if (e.target === self.sheet) {
				self.closeSheet();
			}
		});
		this.editor.addEventListener('close', function () {
			document.documentElement.classList.remove('spf-no-scroll');
		});

		this.zoomInput.addEventListener('input', function () {
			var t = parseFloat(self.zoomInput.value);
			var zoom = MIN_ZOOM * Math.pow(MAX_ZOOM / MIN_ZOOM, t);
			self.zoomAt(zoom, self.hole.x + self.hole.w / 2, self.hole.y + self.hole.h / 2);
		});
		this.blurInput.addEventListener('change', function () {
			if (self.state) {
				self.state.blur = self.blurInput.checked;
				self.draw();
			}
		});
	};

	Maker.prototype.bindGestures = function () {
		var self = this;
		var canvas = this.canvas;
		var pointers = {};
		var last = null; // Previous centre and spread of the active pointers.

		function snapshot() {
			var ids = Object.keys(pointers);
			if (!ids.length) {
				return null;
			}
			var a = pointers[ids[0]];
			var b = pointers[ids[1]] || a;
			return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, d: Math.hypot(a.x - b.x, a.y - b.y), n: ids.length };
		}

		function setDragging(on) {
			self.dragging = on;
			canvas.classList.toggle('is-dragging', on);
			self.draw();
		}

		canvas.addEventListener('pointerdown', function (e) {
			if (!self.photo) {
				return;
			}
			canvas.setPointerCapture(e.pointerId);
			pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
			last = snapshot();
			setDragging(true);
		});
		canvas.addEventListener('pointermove', function (e) {
			if (!pointers[e.pointerId]) {
				return;
			}
			pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
			var now = snapshot();
			var p = self.toCanvas(now.x, now.y);
			if (now.n > 1 && last.n > 1 && last.d > 0) {
				self.zoomAt(self.state.zoom * now.d / last.d, p.x, p.y);
			}
			self.moveBy((now.x - last.x) * p.k, (now.y - last.y) * p.k);
			last = now;
		});
		['pointerup', 'pointercancel'].forEach(function (type) {
			canvas.addEventListener(type, function (e) {
				delete pointers[e.pointerId];
				last = snapshot();
				if (!last) {
					setDragging(false);
				}
			});
		});

		canvas.addEventListener('wheel', function (e) {
			if (!self.photo) {
				return;
			}
			e.preventDefault();
			var p = self.toCanvas(e.clientX, e.clientY);
			self.zoomAt(self.state.zoom * Math.exp(-e.deltaY * 0.0015), p.x, p.y);
		}, { passive: false });

		canvas.addEventListener('keydown', function (e) {
			if (!self.photo) {
				return;
			}
			var step = canvas.width / 50;
			var moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
			if (moves[e.key]) {
				self.moveBy(moves[e.key][0], moves[e.key][1]);
			} else if (e.key === '+' || e.key === '=') {
				self.zoomAt(self.state.zoom * 1.1, self.state.x, self.state.y);
			} else if (e.key === '-') {
				self.zoomAt(self.state.zoom / 1.1, self.state.x, self.state.y);
			} else {
				return;
			}
			e.preventDefault();
		});
	};

	// Live camera in a dialog on computers; phones (and pages without HTTPS) use the system camera instead.
	Maker.prototype.takePhoto = function () {
		var live = navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext &&
			!window.matchMedia('(pointer: coarse)').matches;
		if (live) {
			this.openCamera();
		} else {
			this.cameraInput.click();
		}
	};

	Maker.prototype.openCamera = function () {
		var self = this;
		this.stopCamera();
		navigator.mediaDevices.getUserMedia({
			video: { facingMode: this.facing, width: { ideal: 1920 }, height: { ideal: 1920 } },
			audio: false
		}).then(function (stream) {
			self.stream = stream;
			self.video.srcObject = stream;
			self.video.classList.toggle('is-mirrored', self.facing === 'user');
			self.video.play();
			if (!self.dialog.open) {
				self.dialog.showModal();
			}
		}).catch(function () {
			if (self.dialog.open) {
				self.dialog.close();
			}
			self.cameraInput.click();
		});
	};

	Maker.prototype.stopCamera = function () {
		if (this.stream) {
			this.stream.getTracks().forEach(function (track) {
				track.stop();
			});
			this.stream = null;
		}
	};

	Maker.prototype.bindCamera = function () {
		var self = this;
		this.dialog.addEventListener('close', function () {
			self.stopCamera();
		});
		this.dialog.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-action]');
			var action = btn && btn.getAttribute('data-action');
			if (action === 'camera-cancel') {
				self.dialog.close();
			} else if (action === 'camera-switch') {
				self.facing = self.facing === 'user' ? 'environment' : 'user';
				self.openCamera();
			} else if (action === 'camera-capture' && self.video.videoWidth) {
				// Selfies start mirrored, as seen in the preview; Flip turns them back.
				self.dialog.close();
				self.setPhoto(self.video, self.video.videoWidth, self.video.videoHeight, self.facing === 'user');
			}
			if (action) {
				e.stopPropagation(); // Not the maker's own click handler.
			}
		});
	};

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.spf-maker'), function (el) {
			if (!el.spfMaker) {
				el.spfMaker = new Maker(el);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
