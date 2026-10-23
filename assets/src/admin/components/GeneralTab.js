import {
	Card,
	CardBody,
	CardHeader,
	ToggleControl,
	TextControl,
	RangeControl,
	Button,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import SetupChecklist from './SetupChecklist';

// Mirrors Settings::GTM_PATTERN on the server.
const GTM_ID_PATTERN = /^GTM-[A-Z0-9]{4,10}$/;

const admin = window.consentaroAdmin || {};

const GeoStatus = ( { geo, geoEnabled } ) => {
	if ( ! geo ) {
		return null;
	}

	let tone = 'neutral';
	let message;

	if ( ! geoEnabled ) {
		message = __(
			'Location targeting is off — the banner is shown to every visitor.',
			'consentaro'
		);
	} else if ( ! geo.detected ) {
		tone = 'warn';
		message = __(
			'Your location: couldn’t detect it (common on a local site) — the banner is shown anyway, to be safe.',
			'consentaro'
		);
	} else if ( geo.is_eu ) {
		tone = 'eu';
		message = sprintf(
			/* translators: %s: country code, e.g. DE */
			__(
				'Your location: %s (EU/EEA) — visitors from here see the banner.',
				'consentaro'
			),
			geo.country
		);
	} else {
		message = sprintf(
			/* translators: %s: country code, e.g. US */
			__(
				'Your location: %s (outside the EU/EEA) — the banner is hidden for visitors from here.',
				'consentaro'
			),
			geo.country
		);
	}

	return (
		<div className={ `consentaro-geo consentaro-geo--${ tone }` }>
			<span className="consentaro-geo__text">{ message }</span>
			{ admin.previewUrl && (
				<a
					className="components-button is-secondary consentaro-geo__preview"
					href={ admin.previewUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Preview as EU visitor', 'consentaro' ) }
				</a>
			) }
		</div>
	);
};

const GeneralTab = ( {
	form,
	setup,
	onChange,
	onGoToTab,
	onSkipGtm,
	onConfirmText,
	onMarkTested,
} ) => {
	const [ geo, setGeo ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/geo-check' } )
			.then( setGeo )
			.catch( () => setGeo( null ) );
	}, [] );

	const gtmId = form.gtm_id || '';
	const gtmState =
		gtmId === '' ? 'empty' : GTM_ID_PATTERN.test( gtmId ) ? 'valid' : 'invalid';

	const exportCsv = async () => {
		try {
			const response = await apiFetch( {
				path: '/consentaro/v1/consent-log/export',
				parse: false,
			} );
			const text = await response.text();
			const blob = new Blob( [ text ], { type: 'text/csv' } );
			const url = window.URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = 'consentaro-consent-log.csv';
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );
			window.URL.revokeObjectURL( url );
		} catch ( e ) {
			// Silent — a failed export isn't worth a blocking error state here.
		}
	};

	return (
		<VStack spacing={ 4 }>
			<SetupChecklist
				setup={ setup }
				homeUrl={ admin.homeUrl }
				onGoToTab={ onGoToTab }
				onSkipGtm={ onSkipGtm }
				onConfirmText={ onConfirmText }
				onMarkTested={ onMarkTested }
			/>

			<Card className="consentaro-admin__card">
				<CardHeader>
					<strong>{ __( 'Setup', 'consentaro' ) }</strong>
					<span className="consentaro-admin__card-sub">
						{ __( 'Core options for Consent Mode & GTM', 'consentaro' ) }
					</span>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 5 }>
						<ToggleControl
							label={ __( 'Enable Consentaro', 'consentaro' ) }
							checked={ !! form.enabled }
							onChange={ ( enabled ) => onChange( 'enabled', enabled ) }
							__nextHasNoMarginBottom
						/>

						<div className="consentaro-admin__field consentaro-admin__field--narrow">
							<TextControl
								label={ __( 'GTM Container ID', 'consentaro' ) }
								help={
									gtmState === 'invalid' ? (
										<span
											className="consentaro-admin__field-error"
											role="alert"
										>
											{ __(
												'That doesn’t look like a GTM ID. Use the form GTM-XXXXXXX (letters and numbers, 4–10 after “GTM-”). It won’t be saved until fixed.',
												'consentaro'
											) }
										</span>
									) : gtmState === 'valid' ? (
										<span className="consentaro-admin__field-ok">
											<span aria-hidden="true">✓ </span>
											{ __( 'Looks valid', 'consentaro' ) }
										</span>
									) : (
										__( 'Example: GTM-XXXXXXX', 'consentaro' )
									)
								}
								placeholder="GTM-"
								value={ gtmId }
								onChange={ ( value ) =>
									onChange(
										'gtm_id',
										value.toUpperCase().replace( /\s+/g, '' )
									)
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>

						<div className="consentaro-admin__section">
							<ToggleControl
								label={ __( 'Geo-based banner (EU/EEA)', 'consentaro' ) }
								help={ __(
									'Show banner only for EU visitors when enabled.',
									'consentaro'
								) }
								checked={ !! form.geo_enabled }
								onChange={ ( geo_enabled ) =>
									onChange( 'geo_enabled', geo_enabled )
								}
								__nextHasNoMarginBottom
							/>
							{ !! form.geo_enabled && (
								<div className="consentaro-admin__field consentaro-admin__field--indent">
									<ToggleControl
										label={ __(
											'This site is behind Cloudflare',
											'consentaro'
										) }
										help={ __(
											'Only enable this if your site actually proxies traffic through Cloudflare (orange-cloud DNS). Cloudflare-supplied headers are used for faster, more accurate country detection — but if your site is not really behind Cloudflare, these headers can be faked by anyone, letting them force the wrong country and skip the consent banner. Leave off if unsure.',
											'consentaro'
										) }
										checked={ !! form.cf_trusted }
										onChange={ ( cf_trusted ) =>
											onChange( 'cf_trusted', cf_trusted )
										}
										__nextHasNoMarginBottom
									/>
								</div>
							) }
							<GeoStatus geo={ geo } geoEnabled={ !! form.geo_enabled } />
						</div>

						<div className="consentaro-admin__section">
							<ToggleControl
								label={ __(
									'Keep a log of consent decisions',
									'consentaro'
								) }
								help={ __(
									'Off by default. When on, each Accept/Deny/Customize decision is stored with a timestamp, the categories chosen, and a random non-identifying token — never an IP address, user agent, or other personal identifier. Useful as GDPR “proof of consent.”',
									'consentaro'
								) }
								checked={ !! form.consent_log_enabled }
								onChange={ ( consent_log_enabled ) =>
									onChange( 'consent_log_enabled', consent_log_enabled )
								}
								__nextHasNoMarginBottom
							/>
							{ !! form.consent_log_enabled && (
								<div className="consentaro-admin__field consentaro-admin__field--indent">
									<div className="consentaro-admin__field consentaro-admin__field--narrow">
										<RangeControl
											label={ __(
												'Keep records for (months)',
												'consentaro'
											) }
											value={ form.consent_expiry_months ?? 12 }
											onChange={ ( consent_expiry_months ) =>
												onChange(
													'consent_expiry_months',
													consent_expiry_months
												)
											}
											min={ 1 }
											max={ 24 }
											__nextHasNoMarginBottom
										/>
									</div>
									{ admin.consentLogUrl ? (
										<a
											className="components-button is-secondary"
											href={ admin.consentLogUrl }
										>
											{ __(
												'Open Consent Log (export CSV there) →',
												'consentaro'
											) }
										</a>
									) : (
										<Button
											variant="secondary"
											onClick={ exportCsv }
											__next40pxDefaultSize
										>
											{ __( 'Export CSV', 'consentaro' ) }
										</Button>
									) }
								</div>
							) }
						</div>
					</VStack>
				</CardBody>
			</Card>
		</VStack>
	);
};

export default GeneralTab;
