/* Square photo cropper: drag to move, slider or wheel to zoom. Stores the result as a face point + zoom
 * (see SSP_Presenter::frame), so the original image is never changed. */
(function (window) {
	'use strict';

	var MAX_ZOOM = 4;

	function clamp(v, min, max) {
		return Math.min(max, Math.max(min, v));
	}

	// Same calculation as SSP_Presenter::frame(): returns the window (fractions of the image) and the img CSS.
	function frame(w, h, fx, fy, zoom) {
		zoom = clamp(zoom || 1, 1, MAX_ZOOM);
		var side = Math.min(w, h) / zoom;
		var wx = side / w;
		var wy = side / h;
		var left = clamp(fx - wx / 2, 0, 1 - wx);
		var top = clamp(fy - wy / 2, 0, 1 - wy);
		return {
			wx: wx,
			wy: wy,
			left: left,
			top: top,
			css: { width: (100 / wx) + '%', height: (100 / wy) + '%', left: (-100 * left / wx) + '%', top: (-100 * top / wy) + '%' }
		};
	}

	function parseFocus(focus) {
		var m = /^([\d.]+)% ([\d.]+)%$/.exec(focus || '');
		return m ? [clamp(m[1] / 100, 0, 1), clamp(m[2] / 100, 0, 1)] : [0.5, 0.5];
	}

	/**
	 * @param {HTMLElement} el    Empty container.
	 * @param {Object}      opts  src, width, height, focus ("x% y%"), zoom, labels {zoom, reset, help}, onChange(focus, zoom).
	 */
	function Cropper(el, opts) {
		var self = this;
		var labels = opts.labels || {};
		var p = parseFocus(opts.focus);
		this.w = opts.width;
		this.h = opts.height;
		this.fx = p[0];
		this.fy = p[1];
		this.zoom = clamp(parseFloat(opts.zoom) || 1, 1, MAX_ZOOM);
		this.onChange = opts.onChange || function () {};

		el.classList.add('ssp-cropper');
		el.innerHTML =
			'<div class="ssp-cropper-view ssp-frame" tabindex="0" role="application" aria-label="' + (labels.help || '') + '">' +
			'<img class="ssp-frame-img" alt="" draggable="false"><span class="ssp-cropper-grid" aria-hidden="true"></span></div>' +
			'<div class="ssp-cropper-controls">' +
			'<span class="dashicons dashicons-search" aria-hidden="true"></span>' +
			'<input type="range" min="1" max="' + MAX_ZOOM + '" step="0.01" aria-label="' + (labels.zoom || 'Zoom') + '">' +
			'<output></output>' +
			'<button type="button" class="button-link ssp-cropper-reset">' + (labels.reset || 'Reset') + '</button>' +
			'</div>' +
			(labels.help ? '<p class="description">' + labels.help + '</p>' : '');

		this.view = el.querySelector('.ssp-cropper-view');
		this.img = el.querySelector('img');
		this.range = el.querySelector('input[type=range]');
		this.out = el.querySelector('output');
		this.img.src = opts.src;

		// Drag to move.
		var drag = null;
		this.view.addEventListener('pointerdown', function (e) {
			drag = { x: e.clientX, y: e.clientY, fx: self.fx, fy: self.fy };
			self.view.setPointerCapture(e.pointerId);
			self.view.classList.add('is-dragging');
		});
		this.view.addEventListener('pointermove', function (e) {
			if (!drag) {
				return;
			}
			var size = self.view.getBoundingClientRect().width;
			var f = frame(self.w, self.h, self.fx, self.fy, self.zoom);
			self.set(drag.fx - (e.clientX - drag.x) / size * f.wx, drag.fy - (e.clientY - drag.y) / size * f.wy, self.zoom);
		});
		['pointerup', 'pointercancel'].forEach(function (type) {
			self.view.addEventListener(type, function () {
				drag = null;
				self.view.classList.remove('is-dragging');
			});
		});

		// Wheel and slider zoom around the centre of the square.
		this.view.addEventListener('wheel', function (e) {
			e.preventDefault();
			self.set(self.fx, self.fy, self.zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08));
		}, { passive: false });
		this.range.addEventListener('input', function () {
			self.set(self.fx, self.fy, parseFloat(this.value));
		});

		// Keyboard: arrows move, +/- zoom.
		this.view.addEventListener('keydown', function (e) {
			var f = frame(self.w, self.h, self.fx, self.fy, self.zoom);
			var step = e.shiftKey ? 0.1 : 0.02;
			var moves = { ArrowLeft: [-step * f.wx, 0], ArrowRight: [step * f.wx, 0], ArrowUp: [0, -step * f.wy], ArrowDown: [0, step * f.wy] };
			if (moves[e.key]) {
				e.preventDefault();
				self.set(self.fx + moves[e.key][0], self.fy + moves[e.key][1], self.zoom);
			} else if (e.key === '+' || e.key === '=' || e.key === '-') {
				e.preventDefault();
				self.set(self.fx, self.fy, self.zoom * (e.key === '-' ? 1 / 1.08 : 1.08));
			}
		});

		el.querySelector('.ssp-cropper-reset').addEventListener('click', function () {
			self.set(0.5, 0.5, 1);
		});

		this.set(this.fx, this.fy, this.zoom, true);
	}

	Cropper.prototype.set = function (fx, fy, zoom, silent) {
		this.zoom = clamp(zoom, 1, MAX_ZOOM);
		// Keep the stored point at the centre of what is visible, so it survives edge clamping.
		var f = frame(this.w, this.h, clamp(fx, 0, 1), clamp(fy, 0, 1), this.zoom);
		this.fx = f.left + f.wx / 2;
		this.fy = f.top + f.wy / 2;
		Object.assign(this.img.style, f.css);
		this.range.value = this.zoom;
		this.out.textContent = this.zoom.toFixed(2) + '×';
		if (!silent) {
			this.onChange(this.focus(), this.zoomValue());
		}
	};

	Cropper.prototype.focus = function () {
		return (this.fx * 100).toFixed(1) + '% ' + (this.fy * 100).toFixed(1) + '%';
	};

	Cropper.prototype.zoomValue = function () {
		return Math.round(this.zoom * 100) / 100;
	};

	Cropper.frame = frame;
	Cropper.parseFocus = parseFocus;
	window.SSPCropper = Cropper;
})(window);
