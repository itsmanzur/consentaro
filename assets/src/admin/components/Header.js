import { __ } from '@wordpress/i18n';

const Header = () => (
	<div className="consentaro-admin__header">
		<div className="consentaro-admin__brand">
			<span className="consentaro-admin__mark" aria-hidden="true" />
			<div>
				<h1>{ __( 'Consentaro', 'consentaro' ) }</h1>
				<p className="consentaro-admin__tagline">
					{ __(
						'Google Consent Mode v2 — light, fast, WooCommerce-ready.',
						'consentaro'
					) }
				</p>
			</div>
		</div>
	</div>
);

export default Header;
