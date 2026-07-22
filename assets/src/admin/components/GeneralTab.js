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
		apiFetch( { path: '/consentflow/v1/geo-check' } )
			.then( setGeo )
			.catch( () => setGeo( null ) );
	}, [] );

	const update = ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	return (
		<Card className="consentflow-admin__card">
			<CardHeader>
				<strong>{ __( 'Setup', 'consentflow' ) }</strong>
				<span className="consentflow-admin__card-sub">
					{ __( 'Core options for Consent Mode & GTM', 'consentflow' ) }
				</span>
			</CardHeader>
			<CardBody>
				<VStack spacing={ 5 }>
					<ToggleControl
						label={ __( 'Enable ConsentFlow', 'consentflow' ) }
						checked={ !! form.enabled }
						onChange={ ( enabled ) => update( 'enabled', enabled ) }
						__nextHasNoMarginBottom
					/>

					<div className="consentflow-admin__field consentflow-admin__field--narrow">
						<TextControl
							label={ __( 'GTM Container ID', 'consentflow' ) }
							help={ __( 'Example: GTM-XXXXXXX', 'consentflow' ) }
							placeholder="GTM-"
							value={ form.gtm_id || '' }
							onChange={ ( gtm_id ) => update( 'gtm_id', gtm_id ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</div>

					<div className="consentflow-admin__section">
						<ToggleControl
							label={ __( 'Geo-based banner (EU/EEA)', 'consentflow' ) }
							help={ __(
								'Show banner only for EU visitors when enabled.',
								'consentflow'
							) }
							checked={ !! form.geo_enabled }
							onChange={ ( geo_enabled ) =>
								update( 'geo_enabled', geo_enabled )
							}
							__nextHasNoMarginBottom
						/>
						{ geo && (
							<div
								className={ `consentflow-admin__badge ${
									geo.is_eu
										? 'consentflow-admin__badge--eu'
										: 'consentflow-admin__badge--other'
								}` }
							>
								<span className="consentflow-admin__badge-label">
									{ __( 'Detected', 'consentflow' ) }
								</span>
								<span className="consentflow-admin__badge-value">
									{ geo.country || __( 'Unknown', 'consentflow' ) }
								</span>
								<span className="consentflow-admin__badge-sep">·</span>
								<span>
									{ geo.is_eu
										? __( 'EU region', 'consentflow' )
										: __( 'Non-EU', 'consentflow' ) }
								</span>
							</div>
						) }
					</div>
				</VStack>
			</CardBody>
			<CardFooter className="consentflow-admin__footer">
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
								{ __( 'Saving…', 'consentflow' ) }
							</>
						) : (
							__( 'Save changes', 'consentflow' )
						) }
					</Button>
				</HStack>
			</CardFooter>
		</Card>
	);
};

export default GeneralTab;
