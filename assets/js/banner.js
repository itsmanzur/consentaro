(function () {
	'use strict';
	var cfg = window.consentaroAPI || {};
	var root = document.getElementById('consentaro-banner');
	if (!root || !cfg.url) return;

	var modal = document.getElementById('consentaro-banner-modal');

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
		if (root && root.parentNode) root.parentNode.removeChild(root);
		if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
		document.dispatchEvent(
			new CustomEvent('consentaro:updated', { detail: consent })
		);
	}

	function onAction(action) {
		if (action === 'customize') {
			if (modal) modal.classList.add('is-open');
			return;
		}
		post({ action: action }).then(done).catch(function () {});
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-consentaro-action]');
		if (!btn) return;
		e.preventDefault();
		onAction(btn.getAttribute('data-consentaro-action'));
	});

	if (modal) {
		modal.addEventListener('click', function (e) {
			if (e.target === modal || e.target.closest('[data-consentaro-close]')) {
				modal.classList.remove('is-open');
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
	}
})();
