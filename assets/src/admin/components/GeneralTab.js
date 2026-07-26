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

const GeneralTab = ( { settings, onSave, saving } ) => {
	const [ form, setForm ] = useState( settings );
	const [ geo, setGeo ] = useState( null );

	useEffect( () => {
		setForm( settings );
	}, [ settings ] );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/geo-check' } )
			.then( setGeo )
			.catch( () => setGeo( null ) );
	}, [] );

	const update = ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

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
						onChange={ ( enabled ) => update( 'enabled', enabled ) }
						__nextHasNoMarginBottom
					/>

					<div className="consentaro-admin__field consentaro-admin__field--narrow">
						<TextControl
							label={ __( 'GTM Container ID', 'consentaro' ) }
							help={ __( 'Example: GTM-XXXXXXX', 'consentaro' ) }
							placeholder="GTM-"
							value={ form.gtm_id || '' }
							onChange={ ( gtm_id ) => update( 'gtm_id', gtm_id ) }
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
								update( 'geo_enabled', geo_enabled )
							}
							__nextHasNoMarginBottom
						/>
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
						onClick={ () => onSave( form ) }
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
