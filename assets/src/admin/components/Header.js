import { __ } from '@wordpress/i18n';

const Header = ( { enabled } ) => (
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
		<div
			className={ `consentaro-admin__status ${
				enabled
					? 'consentaro-admin__status--active'
					: 'consentaro-admin__status--disabled'
			}` }
		>
			<span className="consentaro-admin__status-dot" aria-hidden="true" />
			{ enabled ? __( 'Active', 'consentaro' ) : __( 'Disabled', 'consentaro' ) }
		</div>
	</div>
);

export default Header;
