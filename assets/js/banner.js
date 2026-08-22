(function () {
	'use strict';
	var cfg = window.consentaroAPI || {};
	var root = document.getElementById('consentaro-banner');
	if (!root || !cfg.url) return;

	var modal = document.getElementById('consentaro-banner-modal');
	var modalCard = modal ? modal.querySelector('.consentaro-banner__modal-card') : null;
	var modalTrigger = null; // Element that opened the modal, to restore focus on close.
	var trapCleanup = null;

	var FOCUSABLE_SELECTOR =
		'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

	function focusableElements(container) {
		return Array.prototype.slice
			.call(container.querySelectorAll(FOCUSABLE_SELECTOR))
			.filter(function (el) {
				return !el.disabled && el.offsetParent !== null;
			});
	}

	function trapFocus(container) {
		function handleKeydown(e) {
			if (e.key !== 'Tab') return;
			var items = focusableElements(container);
			if (!items.length) return;
			var first = items[0];
			var last = items[items.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
		container.addEventListener('keydown', handleKeydown);
		return function () {
			container.removeEventListener('keydown', handleKeydown);
		};
	}

	function clearTrap() {
		if (trapCleanup) {
			trapCleanup();
			trapCleanup = null;
		}
	}

	function openModal(trigger) {
		if (!modal) return;
		modalTrigger = trigger || null;
		modal.classList.add('is-open');
		if (modalCard) {
			var items = focusableElements(modalCard);
			if (items.length) items[0].focus();
			trapCleanup = trapFocus(modalCard);
		}
	}

	function closeModal() {
		if (!modal) return;
		modal.classList.remove('is-open');
		clearTrap();
		if (modalTrigger && typeof modalTrigger.focus === 'function') {
			modalTrigger.focus();
		}
		modalTrigger = null;
	}

	function gtagUpdate(consent) {
		window.dataLayer = window.dataLayer || [];
		function gtag() {
			window.dataLayer.push(arguments);
		}
		window.gtag = window.gtag || gtag;
		window.gtag('consent', 'update', consent);
	}

	function post(body) {
		return fetch(cfg.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || '',
			},
			body: JSON.stringify(body),
		}).then(function (r) {
			if (!r.ok) throw new Error('consentaro-request-failed');
			return r.json();
		});
	}

	function loadGTM() {
		var g = window.consentaroGTM;
		if (!g || !g.id || g.loaded) return;
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			'gtm.start': new Date().getTime(),
			event: 'gtm.js',
		});
		var f = document.getElementsByTagName('script')[0];
		var j = document.createElement('script');
		j.async = true;
		j.src =
			'https://www.googletagmanager.com/gtm.js?id=' +
			encodeURIComponent(g.id);
		f.parentNode.insertBefore(j, f);
		g.loaded = true;
	}

	function done(data) {
		var consent = (data && data.update) || (data && data.consent) || {};
		gtagUpdate(consent);
		loadGTM();
		clearTrap();
		if (root && root.parentNode) root.parentNode.removeChild(root);
		if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
		document.dispatchEvent(
			new CustomEvent('consentaro:updated', { detail: consent })
		);
	}

	function onAction(action, trigger) {
		if (action === 'customize') {
			openModal(trigger);
			return;
		}
		post({ action: action }).then(done).catch(function () {});
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-consentaro-action]');
		if (!btn) return;
		e.preventDefault();
		onAction(btn.getAttribute('data-consentaro-action'), btn);
	});

	if (modal) {
		modal.addEventListener('click', function (e) {
			if (e.target === modal || e.target.closest('[data-consentaro-close]')) {
				closeModal();
				return;
			}
			var save = e.target.closest('[data-consentaro-save-custom]');
			if (!save) return;
			e.preventDefault();
			var consent = {};
			var boxes = modal.querySelectorAll('[data-consentaro-type]');
			for (var i = 0; i < boxes.length; i++) {
				consent[boxes[i].getAttribute('data-consentaro-type')] = boxes[i].checked
					? 'granted'
					: 'denied';
			}
			consent.functionality_storage = 'granted';
			consent.security_storage = 'granted';
			post({ action: 'custom', consent: consent }).then(done).catch(function () {});
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.classList.contains('is-open')) {
				closeModal();
			}
		});
	}

	// Move focus into the banner on first render — but only if the visitor
	// isn't already interacting with something else on the page.
	if (
		document.activeElement === document.body ||
		document.activeElement === document.documentElement
	) {
		var firstFocusable = root.querySelector('[data-consentaro-action]');
		if (firstFocusable) firstFocusable.focus();
	}
})();
