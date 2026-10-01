(function ($) {
	'use strict';
	var cfg = window.consentaroWoo || {};
	if (!cfg.events) return;

	function pushEvent(payload) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ ecommerce: null });
		window.dataLayer.push(payload);
	}

	function canAnalytics() {
		return !!cfg.analytics;
	}

	document.addEventListener('consentaro:updated', function (e) {
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

		// Filled by WooCommerce.php from whatever add-to-cart controls were
		// actually rendered on this page — approximate for a variable
		// product (parent price), exact for a simple product.
		var prices = window.consentaroWooPrices || {};
		var known = prices[id] || null;
		var price = known ? known.price : 0;

		pushEvent({
			event: 'add_to_cart',
			ecommerce: {
				currency: cfg.currency || 'USD',
				value: price * qty,
				items: [
					{
						item_id: String(id),
						item_name: known ? known.name : undefined,
						price: price,
						quantity: qty,
					},
				],
			},
		});
	});
})(jQuery);
