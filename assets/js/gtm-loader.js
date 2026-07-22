(function () {
	'use strict';
	var cfg = window.consentflowGTM;
	if (!cfg || !cfg.id || cfg.loaded) return;

	function loadGTM(id) {
		if (window.consentflowGTM && window.consentflowGTM.loaded) return;
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
		window.consentflowGTM = window.consentflowGTM || {};
		window.consentflowGTM.id = id;
		window.consentflowGTM.loaded = true;
		document.dispatchEvent(
			new CustomEvent('consentflow:gtm-loaded', { detail: { id: id } })
		);
	}

	document.addEventListener('consentflow:updated', function () {
		loadGTM(cfg.id);
	});
})();
