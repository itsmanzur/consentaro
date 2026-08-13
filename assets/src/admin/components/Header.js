import { __ } from '@wordpress/i18n';

const Header = ( { enabled } ) => (
	<div className="consentaro-admin__header">
		<div className="consentaro-admin__brand">
			<span className="consentaro-admin__mark" aria-hidden="true">
				<svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
					<defs>
						<linearGradient
							id="consentaroMarkGradient"
							x1="0"
							y1="0"
							x2="36"
							y2="36"
							gradientUnits="userSpaceOnUse"
						>
							<stop offset="0" stopColor="#0E7C66" />
							<stop offset="1" stopColor="#2FD9AE" />
						</linearGradient>
					</defs>
					<rect width="36" height="36" rx="7.2" fill="url(#consentaroMarkGradient)" />
					<path
						d="M10 18.5 L15.5 24 L26 12.5"
						fill="none"
						stroke="#fff"
						strokeWidth="3"
						strokeLinecap="round"
						strokeLinejoin="round"
					/>
					<circle cx="29" cy="7" r="3.5" fill="#F5B942" />
				</svg>
			</span>
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
