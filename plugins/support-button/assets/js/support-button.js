(function () {
	'use strict';

	var cfg = window.spbConfig || {};

	document.addEventListener('DOMContentLoaded', function () {
		var widget    = document.getElementById('spb-widget');
		var toggle    = document.getElementById('spb-toggle');
		var menu      = document.getElementById('spb-menu');
		var bubble    = document.getElementById('spb-bubble');
		var offlineEl = document.getElementById('spb-offline');

		if (!toggle || !menu) return;

		/* ── Schedule check ──────────────────────────────── */
		var offline = false;
		if (cfg.schedule) {
			offline = !isOnlineNow(cfg.schedule);
			if (offline) {
				if (cfg.schedule.offlineMsg) {
					toggle.classList.add('spb-offline');
					toggle.setAttribute('aria-label', cfg.schedule.offlineMsg);
				} else {
					widget.style.display = 'none';
					return;
				}
			}
		}

		/* ── Welcome bubble ──────────────────────────────── */
		if (bubble && cfg.bubble && !offline) {
			var delay = Math.max(0, (cfg.bubble.delay || 0)) * 1000;
			setTimeout(function () {
				if (menu.getAttribute('aria-hidden') !== 'false') {
					bubble.classList.add('is-open');
				}
			}, delay);

			var dismissBtn = bubble.querySelector('.spb-bubble-x');
			if (dismissBtn) {
				dismissBtn.addEventListener('click', function () {
					bubble.classList.remove('is-open');
				});
			}
		}

		/* ── Toggle ──────────────────────────────────────── */
		toggle.addEventListener('click', function (e) {
			e.stopPropagation();
			if (offline) {
				toggleOfflineMsg();
				return;
			}
			menu.getAttribute('aria-hidden') === 'false' ? closeMenu() : openMenu();
		});

		document.addEventListener('click', function (e) {
			if (!widget.contains(e.target)) {
				closeMenu();
				hideOfflineMsg();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { closeMenu(); hideOfflineMsg(); }
		});

		/* ── Click tracking ──────────────────────────────── */
		if (cfg.ajaxUrl && cfg.nonce) {
			menu.querySelectorAll('.spb-item').forEach(function (link) {
				link.addEventListener('click', function () {
					var data = new FormData();
					data.append('action', 'support_btn_track');
					data.append('nonce', cfg.nonce);
					data.append('url', link.href);
					data.append('label', link.dataset.label || link.title || '');
					if (typeof navigator.sendBeacon === 'function') {
						navigator.sendBeacon(cfg.ajaxUrl, data);
					} else {
						fetch(cfg.ajaxUrl, { method: 'POST', body: data, keepalive: true }).catch(function () {});
					}
				});
			});
		}

		/* ── Helpers ─────────────────────────────────────── */
		function openMenu() {
			menu.setAttribute('aria-hidden', 'false');
			toggle.setAttribute('aria-expanded', 'true');
			toggle.setAttribute('aria-label', 'بستن منوی پشتیبانی');
			if (bubble) bubble.classList.remove('is-open');
		}

		function closeMenu() {
			menu.setAttribute('aria-hidden', 'true');
			toggle.setAttribute('aria-expanded', 'false');
			toggle.setAttribute('aria-label', 'باز کردن منوی پشتیبانی');
		}

		function toggleOfflineMsg() {
			if (!offlineEl) return;
			var isHidden = offlineEl.getAttribute('aria-hidden') === 'true';
			offlineEl.setAttribute('aria-hidden', isHidden ? 'false' : 'true');
			toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
		}

		function hideOfflineMsg() {
			if (offlineEl) offlineEl.setAttribute('aria-hidden', 'true');
		}

		function isOnlineNow(schedule) {
			var nowUtcMs = Date.now();
			var tzMs     = (cfg.tzOffset || 0) * 60 * 1000;
			var local    = new Date(nowUtcMs + tzMs);

			var day     = local.getUTCDay();
			var current = local.getUTCHours() * 60 + local.getUTCMinutes();

			var sp = (schedule.start || '09:00').split(':');
			var ep = (schedule.end   || '18:00').split(':');
			var startMin = +sp[0] * 60 + +sp[1];
			var endMin   = +ep[0] * 60 + +ep[1];

			return Array.isArray(schedule.days)
				&& schedule.days.indexOf(day) !== -1
				&& current >= startMin
				&& current < endMin;
		}
	});
})();
