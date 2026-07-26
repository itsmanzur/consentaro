(function () {
	'use strict';
	var cfg = window.consentaroGTM;
	if (!cfg || !cfg.id || cfg.loaded) return;

	function loadGTM(id) {
		if (window.consentaroGTM && window.consentaroGTM.loaded) return;
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			'gtm.start': new Date().getTime(),
			event: 'gtm.js',
		});
		var f = document.getElementsByTagName('script')[0];
		var j = document.createElement('script');
		j.async = true;
		j.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id);
		f.parentNode.insertBefore(j, f);
		window.consentaroGTM = window.consentaroGTM || {};
		window.consentaroGTM.id = id;
		window.consentaroGTM.loaded = true;
		document.dispatchEvent(
			new CustomEvent('consentaro:gtm-loaded', { detail: { id: id } })
		);
	}

	document.addEventListener('consentaro:updated', function () {
		loadGTM(cfg.id);
	});
})();
