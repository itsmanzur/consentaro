import {
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	ToggleControl,
	TextControl,
	Button,
	Spinner,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const GTM_ID_PATTERN = /^GTM-[A-Z0-9]+$/;

const GeneralTab = ( { form, onChange, onSave, saving } ) => {
	const [ geo, setGeo ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/geo-check' } )
			.then( setGeo )
			.catch( () => setGeo( null ) );
	}, [] );

	const gtmId = form.gtm_id || '';
	const gtmInvalid = gtmId !== '' && ! GTM_ID_PATTERN.test( gtmId.toUpperCase() );

	return (
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
								gtmInvalid ? (
									<span className="consentaro-admin__field-error">
										{ __(
											'Doesn’t look like a GTM ID (e.g. GTM-XXXXXXX) — this won’t be saved until fixed.',
											'consentaro'
										) }
									</span>
								) : (
									__( 'Example: GTM-XXXXXXX', 'consentaro' )
								)
							}
							placeholder="GTM-"
							value={ gtmId }
							onChange={ ( gtm_id ) => onChange( 'gtm_id', gtm_id ) }
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
						{ geo && (
							<div
								className={ `consentaro-admin__badge ${
									geo.is_eu
										? 'consentaro-admin__badge--eu'
										: 'consentaro-admin__badge--other'
								}` }
							>
								<span className="consentaro-admin__badge-label">
									{ __( 'Detected', 'consentaro' ) }
								</span>
								<span className="consentaro-admin__badge-value">
									{ geo.country || __( 'Unknown', 'consentaro' ) }
								</span>
								<span className="consentaro-admin__badge-sep">·</span>
								<span>
									{ geo.is_eu
										? __( 'EU region', 'consentaro' )
										: __( 'Non-EU', 'consentaro' ) }
								</span>
							</div>
						) }
					</div>
				</VStack>
			</CardBody>
			<CardFooter className="consentaro-admin__footer">
				<HStack justify="flex-start">
					<Button
						variant="primary"
						disabled={ saving }
						onClick={ onSave }
						__next40pxDefaultSize
					>
						{ saving ? (
							<>
								<Spinner />
								{ __( 'Saving…', 'consentaro' ) }
							</>
						) : (
							__( 'Save changes', 'consentaro' )
						) }
					</Button>
				</HStack>
			</CardFooter>
		</Card>
	);
};

export default GeneralTab;
