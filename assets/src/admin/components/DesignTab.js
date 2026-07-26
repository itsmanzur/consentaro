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
		id={ `consentaro-color-${ label }` }
		className="consentaro-admin__color-field"
		__nextHasNoMarginBottom
	>
		<Dropdown
			className="consentaro-admin__color-dropdown"
			contentClassName="consentaro-admin__color-popover"
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					onClick={ onToggle }
					aria-expanded={ isOpen }
					className="consentaro-admin__color-toggle"
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
	<div className="consentaro-admin__preview">
		<div className="consentaro-admin__preview-label">
			{ __( 'Live preview', 'consentaro' ) }
		</div>
		<div
			className={ `consentaro-admin__preview-stage consentaro-admin__preview-stage--${
				banner.position || 'bottom'
			}` }
		>
			<div
				className="consentaro-admin__preview-banner"
				style={ {
					'--consentaro-bg': banner.bg || '#ffffff',
					'--consentaro-text': banner.text_color || '#1a1a1a',
					'--consentaro-btn-bg': banner.btn_primary_bg || '#0073aa',
					'--consentaro-btn-text': banner.btn_primary_text || '#ffffff',
				} }
			>
				<p>{ banner.text || __( 'Banner text…', 'consentaro' ) }</p>
				<div className="consentaro-admin__preview-actions">
					<button type="button" className="is-primary">
						{ __( 'Accept All', 'consentaro' ) }
					</button>
					<button type="button" className="is-secondary">
						{ __( 'Deny All', 'consentaro' ) }
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
		<div className="consentaro-admin__design">
			<Card className="consentaro-admin__card">
				<CardHeader>
					<strong>{ __( 'Banner', 'consentaro' ) }</strong>
					<span className="consentaro-admin__card-sub">
						{ __( 'Position, copy, and colors', 'consentaro' ) }
					</span>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 5 }>
						<div className="consentaro-admin__field consentaro-admin__field--narrow">
							<SelectControl
								label={ __( 'Position', 'consentaro' ) }
								value={ banner.position || 'bottom' }
								options={ [
									{
										label: __( 'Bottom bar', 'consentaro' ),
										value: 'bottom',
									},
									{
										label: __( 'Top bar', 'consentaro' ),
										value: 'top',
									},
									{
										label: __( 'Bottom-right floating', 'consentaro' ),
										value: 'bottom-right',
									},
									{
										label: __( 'Center modal', 'consentaro' ),
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
							label={ __( 'Banner text', 'consentaro' ) }
							value={ banner.text || '' }
							onChange={ ( text ) => updateBanner( 'text', text ) }
							rows={ 3 }
							__nextHasNoMarginBottom
						/>

						<div className="consentaro-admin__colors">
							<ColorField
								label={ __( 'Background', 'consentaro' ) }
								color={ banner.bg || '#ffffff' }
								onChange={ ( bg ) => updateBanner( 'bg', bg ) }
							/>
							<ColorField
								label={ __( 'Text', 'consentaro' ) }
								color={ banner.text_color || '#1a1a1a' }
								onChange={ ( text_color ) =>
									updateBanner( 'text_color', text_color )
								}
							/>
							<ColorField
								label={ __( 'Button background', 'consentaro' ) }
								color={ banner.btn_primary_bg || '#0073aa' }
								onChange={ ( btn_primary_bg ) =>
									updateBanner( 'btn_primary_bg', btn_primary_bg )
								}
							/>
							<ColorField
								label={ __( 'Button text', 'consentaro' ) }
								color={ banner.btn_primary_text || '#ffffff' }
								onChange={ ( btn_primary_text ) =>
									updateBanner( 'btn_primary_text', btn_primary_text )
								}
							/>
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

			<BannerPreview banner={ banner } />
		</div>
	);
};

export default DesignTab;
