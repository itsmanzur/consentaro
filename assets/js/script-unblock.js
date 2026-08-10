(function () {
	'use strict';

	// Maps our tracker categories to the Consent Mode v2 signal that unlocks them.
	var CATEGORY_SIGNAL = {
		analytics: 'analytics_storage',
		marketing: 'ad_storage',
		personalization: 'personalization_storage',
	};

	function isGranted(consent, category) {
		var signal = CATEGORY_SIGNAL[category];
		if (!signal) return false;
		return consent && consent[signal] === 'granted';
	}

	function revive(node) {
		var replacement = document.createElement('script');

		for (var i = 0; i < node.attributes.length; i++) {
			var attr = node.attributes[i];
			if (attr.name === 'type' || attr.name === 'data-consentaro-category') continue;
			if (attr.name === 'data-consentaro-src') {
				replacement.setAttribute('src', attr.value);
				continue;
			}
			replacement.setAttribute(attr.name, attr.value);
		}

		if (!node.hasAttribute('data-consentaro-src')) {
			replacement.text = node.text;
		}

		replacement.setAttribute('data-consentaro-revived', '1');
		node.parentNode.replaceChild(replacement, node);
	}

	function reviveGranted(consent) {
		var blocked = document.querySelectorAll(
			'script[data-consentaro-category]:not([data-consentaro-revived])'
		);
		for (var i = 0; i < blocked.length; i++) {
			var category = blocked[i].getAttribute('data-consentaro-category');
			if (isGranted(consent, category)) {
				revive(blocked[i]);
			}
		}
	}

	document.addEventListener('consentaro:updated', function (e) {
		reviveGranted((e && e.detail) || {});
	});

	// Cookie/GTM flows may already have a decision by the time this loads
	// (e.g. banner suppressed for a returning visitor with a stored cookie
	// but this script still enqueued for a mixed-consent edge case).
	if (window.consentaroGTM && window.consentaroGTM.loaded) {
		// A loaded GTM bootstrap implies consent was already resolved;
		// dataLayer 'consent update' pushes carry the real map, so just
		// listen — nothing to revive proactively without that map.
	}
})();
