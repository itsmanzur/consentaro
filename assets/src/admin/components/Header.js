import { __ } from '@wordpress/i18n';

const Header = () => (
	<div className="consentflow-admin__header">
		<div className="consentflow-admin__brand">
			<span className="consentflow-admin__mark" aria-hidden="true" />
			<div>
				<h1>{ __( 'ConsentFlow', 'consentflow' ) }</h1>
				<p className="consentflow-admin__tagline">
					{ __(
						'Google Consent Mode v2 — light, fast, WooCommerce-ready.',
						'consentflow'
					) }
				</p>
			</div>
		</div>
	</div>
);

export default Header;
