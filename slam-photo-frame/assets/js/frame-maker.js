/* Frame maker: puts a photo (camera or gallery) behind the frame PNG, exports a JPEG and shares it.
 * Everything happens in the browser; only a use count is sent to the server. */
(function () {
	'use strict';

	var MAX_SOURCE = 2400; // Longest side kept from a photo, enough for any frame and kind to phone memory.
	var MIN_ZOOM = 0.5; // Zoom is relative to the size that just covers the frame's window.
	var MAX_ZOOM = 6;

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
		this.result = el.querySelector('.spf-result');
		this.note = el.querySelector('.spf-note');
		this.zoomInput = el.querySelector('[data-action=zoom]');
		this.dialog = el.querySelector('.spf-camera');
		this.video = this.dialog.querySelector('video');
		this.photo = null;
		this.state = null;
		this.file = null;
		this.counted = false;
		this.facing = 'user';

		this.frame = new Image();
		if (!sameOrigin(this.cfg.image)) {
			this.frame.crossOrigin = 'anonymous'; // Otherwise the canvas is tainted and cannot be exported.
		}
		this.frame.onload = function () {
			self.canvas.width = self.frame.naturalWidth;
			self.canvas.height = self.frame.naturalHeight;
			self.hole = self.findWindow();
			self.draw();
			el.classList.add('is-ready');
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

	Maker.prototype.draw = function () {
		var ctx = this.ctx;
		var w = this.canvas.width;
		var h = this.canvas.height;
		ctx.fillStyle = '#fff';
		ctx.fillRect(0, 0, w, h);
		if (this.photo) {
			var s = this.baseScale() * this.state.zoom;
			ctx.save();
			ctx.translate(this.state.x, this.state.y);
			ctx.scale(this.state.flip ? -1 : 1, 1);
			ctx.rotate(this.state.rot * Math.PI / 2);
			ctx.scale(s, s);
			ctx.imageSmoothingQuality = 'high';
			ctx.drawImage(this.photo, -this.photo.width / 2, -this.photo.height / 2);
			ctx.restore();
		} else {
			this.drawPlaceholder();
		}
		ctx.drawImage(this.frame, 0, 0, w, h);
	};

	// A grey silhouette in the window until there is a photo.
	Maker.prototype.drawPlaceholder = function () {
		var ctx = this.ctx;
		var b = this.hole;
		var r = Math.min(b.w, b.h);
		var cx = b.x + b.w / 2;
		var cy = b.y + b.h / 2;
		ctx.fillStyle = '#e4e6e8';
		ctx.fillRect(b.x, b.y, b.w, b.h);
		ctx.fillStyle = '#c3c8cd';
		ctx.beginPath();
		ctx.arc(cx, cy - r * 0.12, r * 0.16, 0, Math.PI * 2);
		ctx.fill();
		ctx.beginPath();
		ctx.ellipse(cx, cy + r * 0.32, r * 0.3, r * 0.22, 0, Math.PI, 0);
		ctx.fill();
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
		this.state = { zoom: 1, x: 0, y: 0, rot: 0, flip: !!flip };
		this.counted = false;
		this.reset();
		this.showStep('edit');
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

	Maker.prototype.showStep = function (name) {
		Array.prototype.forEach.call(this.el.querySelectorAll('.spf-step'), function (step) {
			step.hidden = step.getAttribute('data-step') !== name;
		});
		this.el.setAttribute('data-step', name);
		var sharing = name === 'share';
		this.result.hidden = !sharing;
		this.canvas.hidden = sharing;
		if (!sharing) {
			this.setNote('');
		}
	};

	Maker.prototype.setNote = function (text) {
		this.note.textContent = text;
		this.note.hidden = !text;
	};

	Maker.prototype.finish = function () {
		var self = this;
		this.draw();
		try {
			this.canvas.toBlob(function (blob) {
				if (!blob) {
					return;
				}
				var name = (self.cfg.fileName || 'photo-frame') + '.jpg';
				self.file = new File([blob], name, { type: 'image/jpeg' });
				if (self.result.src) {
					URL.revokeObjectURL(self.result.src);
				}
				self.result.src = URL.createObjectURL(blob);
				self.el.querySelector('[data-action=share]').hidden = !self.canShareFile();
				self.showStep('share');
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
	};

	Maker.prototype.share = function (fallbackApp) {
		var self = this;
		var text = [this.cfg.shareText, this.cfg.url].filter(Boolean).join(' ');
		navigator.share({ files: [this.file], title: this.cfg.title, text: text }).then(function () {
			self.count();
		}).catch(function (e) {
			if (e && e.name !== 'AbortError') {
				self.download(fallbackApp);
			}
		});
	};

	Maker.prototype.download = function (app) {
		var a = document.createElement('a');
		a.href = this.result.src;
		a.download = this.file.name;
		document.body.appendChild(a);
		a.click();
		a.remove();
		this.count();
		if (app) {
			this.setNote(this.l.saved.replace('%s', app));
		}
	};

	Maker.prototype.copyLink = function () {
		var self = this;
		var done = function () {
			self.setNote(self.l.copied);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(this.cfg.url).then(done, function () {
				window.prompt('', self.cfg.url);
			});
		} else {
			window.prompt('', this.cfg.url);
		}
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
			var action = btn.getAttribute('data-action');
			switch (action) {
				case 'choose':
					gallery.click();
					break;
				case 'take':
					self.takePhoto();
					break;
				case 'rotate':
					self.state.rot = (self.state.rot + 1) % 4;
					self.update();
					break;
				case 'flip':
					self.state.flip = !self.state.flip;
					self.update();
					break;
				case 'reset':
					self.reset();
					break;
				case 'done':
					self.finish();
					break;
				case 'edit':
					self.showStep('edit');
					break;
				case 'share':
					self.share();
					break;
				case 'download':
					self.download();
					break;
				case 'app':
					// Instagram, WhatsApp Status and Facebook take pictures only from the app: share sheet, else save it.
					if (self.canShareFile()) {
						self.share(btn.getAttribute('data-app'));
					} else {
						self.download(btn.getAttribute('data-app'));
					}
					break;
				case 'copy':
					self.copyLink();
					break;
			}
		});

		this.zoomInput.addEventListener('input', function () {
			var t = parseFloat(self.zoomInput.value);
			var zoom = MIN_ZOOM * Math.pow(MAX_ZOOM / MIN_ZOOM, t);
			self.zoomAt(zoom, self.hole.x + self.hole.w / 2, self.hole.y + self.hole.h / 2);
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

		canvas.addEventListener('pointerdown', function (e) {
			if (!self.photo) {
				self.el.querySelector('[data-input=gallery]').click();
				return;
			}
			canvas.setPointerCapture(e.pointerId);
			pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
			last = snapshot();
			canvas.classList.add('is-dragging');
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
					canvas.classList.remove('is-dragging');
				}
			});
		});

		canvas.addEventListener('wheel', function (e) {
			if (!self.photo || self.el.getAttribute('data-step') !== 'edit') {
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
			!window.matchMedia('(pointer: coarse)').matches && this.dialog.showModal;
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
				self.setPhoto(self.video, self.video.videoWidth, self.video.videoHeight, self.facing === 'user');
				self.dialog.close();
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
