(function ($) {
	'use strict';
	var cfg = window.consentflowWoo || {};
	if (!cfg.events) return;

	function pushEvent(payload) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ ecommerce: null });
		window.dataLayer.push(payload);
	}

	function canAnalytics() {
		return !!cfg.analytics;
	}

	document.addEventListener('consentflow:updated', function (e) {
		var c = (e && e.detail) || {};
		cfg.analytics = c.analytics_storage === 'granted';
		cfg.ads = c.ad_storage === 'granted';
	});

	$(document.body).on('added_to_cart', function (e, fragments, cart_hash, $button) {
		if (!cfg.events.add_to_cart || !canAnalytics()) return;
		var $btn = $button && $button.length ? $button : null;
		var id = $btn ? $btn.data('product_id') : null;
		var qty = $btn ? parseInt($btn.data('quantity'), 10) || 1 : 1;
		if (!id) return;
		pushEvent({
			event: 'add_to_cart',
			ecommerce: {
				currency: cfg.currency || 'USD',
				items: [
					{
						item_id: String(id),
						quantity: qty,
					},
				],
			},
		});
	});
})(jQuery);
