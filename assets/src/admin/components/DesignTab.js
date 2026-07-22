import {
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	SelectControl,
	TextareaControl,
	Button,
	Spinner,
	Dropdown,
	ColorPicker,
	ColorIndicator,
	BaseControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const ColorField = ( { label, color, onChange } ) => (
	<BaseControl
		label={ label }
		id={ `cf-color-${ label }` }
		className="consentflow-admin__color-field"
		__nextHasNoMarginBottom
	>
		<Dropdown
			className="consentflow-admin__color-dropdown"
			contentClassName="consentflow-admin__color-popover"
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					onClick={ onToggle }
					aria-expanded={ isOpen }
					className="consentflow-admin__color-toggle"
					variant="secondary"
					__next40pxDefaultSize
				>
					<ColorIndicator colorValue={ color } />
					<code>{ color }</code>
				</Button>
			) }
			renderContent={ () => (
				<ColorPicker
					color={ color }
					onChange={ onChange }
					enableAlpha={ false }
					defaultValue={ color }
				/>
			) }
		/>
	</BaseControl>
);

const BannerPreview = ( { banner } ) => (
	<div className="consentflow-admin__preview">
		<div className="consentflow-admin__preview-label">
			{ __( 'Live preview', 'consentflow' ) }
		</div>
		<div
			className={ `consentflow-admin__preview-stage consentflow-admin__preview-stage--${
				banner.position || 'bottom'
			}` }
		>
			<div
				className="consentflow-admin__preview-banner"
				style={ {
					'--cf-bg': banner.bg || '#ffffff',
					'--cf-text': banner.text_color || '#1a1a1a',
					'--cf-btn-bg': banner.btn_primary_bg || '#0073aa',
					'--cf-btn-text': banner.btn_primary_text || '#ffffff',
				} }
			>
				<p>{ banner.text || __( 'Banner text…', 'consentflow' ) }</p>
				<div className="consentflow-admin__preview-actions">
					<button type="button" className="is-primary">
						{ __( 'Accept All', 'consentflow' ) }
					</button>
					<button type="button" className="is-secondary">
						{ __( 'Deny All', 'consentflow' ) }
					</button>
				</div>
			</div>
		</div>
	</div>
);

const DesignTab = ( { settings, onSave, saving } ) => {
	const [ form, setForm ] = useState( settings );

	useEffect( () => {
		setForm( settings );
	}, [ settings ] );

	const banner = form.banner || {};

	const updateBanner = ( key, value ) => {
		setForm( ( prev ) => ( {
			...prev,
			banner: { ...prev.banner, [ key ]: value },
		} ) );
	};

	return (
		<div className="consentflow-admin__design">
			<Card className="consentflow-admin__card">
				<CardHeader>
					<strong>{ __( 'Banner', 'consentflow' ) }</strong>
					<span className="consentflow-admin__card-sub">
						{ __( 'Position, copy, and colors', 'consentflow' ) }
					</span>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 5 }>
						<div className="consentflow-admin__field consentflow-admin__field--narrow">
							<SelectControl
								label={ __( 'Position', 'consentflow' ) }
								value={ banner.position || 'bottom' }
								options={ [
									{
										label: __( 'Bottom bar', 'consentflow' ),
										value: 'bottom',
									},
									{
										label: __( 'Top bar', 'consentflow' ),
										value: 'top',
									},
									{
										label: __( 'Bottom-right floating', 'consentflow' ),
										value: 'bottom-right',
									},
									{
										label: __( 'Center modal', 'consentflow' ),
										value: 'modal',
									},
								] }
								onChange={ ( position ) =>
									updateBanner( 'position', position )
								}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>

						<TextareaControl
							label={ __( 'Banner text', 'consentflow' ) }
							value={ banner.text || '' }
							onChange={ ( text ) => updateBanner( 'text', text ) }
							rows={ 3 }
							__nextHasNoMarginBottom
						/>

						<div className="consentflow-admin__colors">
							<ColorField
								label={ __( 'Background', 'consentflow' ) }
								color={ banner.bg || '#ffffff' }
								onChange={ ( bg ) => updateBanner( 'bg', bg ) }
							/>
							<ColorField
								label={ __( 'Text', 'consentflow' ) }
								color={ banner.text_color || '#1a1a1a' }
								onChange={ ( text_color ) =>
									updateBanner( 'text_color', text_color )
								}
							/>
							<ColorField
								label={ __( 'Button background', 'consentflow' ) }
								color={ banner.btn_primary_bg || '#0073aa' }
								onChange={ ( btn_primary_bg ) =>
									updateBanner( 'btn_primary_bg', btn_primary_bg )
								}
							/>
							<ColorField
								label={ __( 'Button text', 'consentflow' ) }
								color={ banner.btn_primary_text || '#ffffff' }
								onChange={ ( btn_primary_text ) =>
									updateBanner( 'btn_primary_text', btn_primary_text )
								}
							/>
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

			<BannerPreview banner={ banner } />
		</div>
	);
};

export default DesignTab;
