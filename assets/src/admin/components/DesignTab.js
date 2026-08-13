import {
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	SelectControl,
	TextareaControl,
	RangeControl,
	Button,
	Spinner,
	Dropdown,
	ColorPicker,
	ColorIndicator,
	BaseControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const PRESETS = [
	{
		id: 'consentaro',
		label: __( 'Consentaro', 'consentaro' ),
		colors: {
			bg: '#10192B',
			text_color: '#ffffff',
			btn_primary_bg: '#0E7C66',
			btn_primary_text: '#ffffff',
			btn_secondary_bg: '#26314A',
			btn_secondary_text: '#ffffff',
		},
	},
	{
		id: 'minimal',
		label: __( 'Minimal', 'consentaro' ),
		colors: {
			bg: '#ffffff',
			text_color: '#1a1a1a',
			btn_primary_bg: '#3c434a',
			btn_primary_text: '#ffffff',
			btn_secondary_bg: '#f0f0f1',
			btn_secondary_text: '#1a1a1a',
		},
	},
	{
		id: 'dark',
		label: __( 'Dark', 'consentaro' ),
		colors: {
			bg: '#10192B',
			text_color: '#ffffff',
			btn_primary_bg: '#2FD9AE',
			btn_primary_text: '#10192B',
			btn_secondary_bg: '#26314A',
			btn_secondary_text: '#ffffff',
		},
	},
	{
		id: 'warm',
		label: __( 'Warm', 'consentaro' ),
		colors: {
			bg: '#FBF3E1',
			text_color: '#10192B',
			btn_primary_bg: '#F5B942',
			btn_primary_text: '#10192B',
			btn_secondary_bg: '#F0E6CE',
			btn_secondary_text: '#10192B',
		},
	},
];

const PRESET_COLOR_KEYS = [
	'bg',
	'text_color',
	'btn_primary_bg',
	'btn_primary_text',
	'btn_secondary_bg',
	'btn_secondary_text',
];

const isPresetActive = ( preset, banner ) =>
	PRESET_COLOR_KEYS.every(
		( key ) =>
			( banner[ key ] || '' ).toLowerCase() ===
			preset.colors[ key ].toLowerCase()
	);

const PresetPicker = ( { onApply, banner } ) => (
	<div className="consentaro-admin__presets">
		{ PRESETS.map( ( preset ) => {
			const active = isPresetActive( preset, banner );
			return (
				<button
					type="button"
					key={ preset.id }
					className={ `consentaro-admin__preset-swatch ${
						active ? 'is-active' : ''
					}` }
					onClick={ () => onApply( preset.colors ) }
					aria-pressed={ active }
				>
					<span
						className="consentaro-admin__preset-swatch-preview"
						aria-hidden="true"
						style={ { background: preset.colors.bg } }
					>
						<span
							className="consentaro-admin__preset-swatch-dot"
							style={ {
								background: preset.colors.btn_primary_bg,
								boxShadow: `0 0 0 2px ${ preset.colors.btn_primary_text }, 0 0 0 3px #fff`,
							} }
						/>
						{ active && (
							<span
								className="consentaro-admin__preset-swatch-check"
								aria-hidden="true"
							>
								✓
							</span>
						) }
					</span>
					<span className="consentaro-admin__preset-swatch-label">
						{ preset.label }
					</span>
				</button>
			);
		} ) }
	</div>
);

const SectionHeading = ( { children } ) => (
	<h3 className="consentaro-admin__section-heading">{ children }</h3>
);

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

const PREVIEW_RESULTS = {
	accept: {
		text: __( '✓ Consent saved — Accept All', 'consentaro' ),
		isSuccess: true,
	},
	deny: {
		text: __( '✓ Consent saved — Deny All', 'consentaro' ),
		isSuccess: true,
	},
	customize: {
		text: __( 'Customize panel would open here', 'consentaro' ),
		isSuccess: false,
	},
};

const BannerPreview = ( { banner } ) => {
	const [ device, setDevice ] = useState( 'desktop' );
	// null = showing banner; 'fading' = banner mid fade-out; 'result' = showing outcome message.
	const [ phase, setPhase ] = useState( 'banner' );
	const [ action, setAction ] = useState( null );
	const fadeTimer = useRef( null );

	useEffect( () => () => {
		if ( fadeTimer.current ) {
			clearTimeout( fadeTimer.current );
		}
	}, [] );

	const triggerAction = ( nextAction ) => {
		setAction( nextAction );
		setPhase( 'fading' );
		fadeTimer.current = setTimeout( () => setPhase( 'result' ), 300 );
	};

	const resetPreview = () => {
		if ( fadeTimer.current ) {
			clearTimeout( fadeTimer.current );
		}
		setPhase( 'banner' );
		setAction( null );
	};

	const result = action ? PREVIEW_RESULTS[ action ] : null;

	const btnDirection = banner.btn_layout === 'stacked' ? 'column' : 'row';
	const justifyMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
	const btnJustify = justifyMap[ banner.btn_align ] || 'flex-start';
	const marginMap = { left: '0', center: '0 auto', right: '0 0 0 auto' };
	const btnMargin = marginMap[ banner.btn_align ] || '0';

	return (
		<div className="consentaro-admin__preview">
			<div className="consentaro-admin__preview-label">
				<span>{ __( 'Live preview', 'consentaro' ) }</span>
				<div
					className="consentaro-admin__preview-device-toggle"
					role="group"
					aria-label={ __( 'Preview device size', 'consentaro' ) }
				>
					<button
						type="button"
						className={ device === 'desktop' ? 'is-active' : '' }
						onClick={ () => setDevice( 'desktop' ) }
					>
						{ __( 'Desktop', 'consentaro' ) }
					</button>
					<button
						type="button"
						className={ device === 'mobile' ? 'is-active' : '' }
						onClick={ () => setDevice( 'mobile' ) }
					>
						{ __( 'Mobile', 'consentaro' ) }
					</button>
				</div>
			</div>
			<div
				className={ `consentaro-admin__preview-stage consentaro-admin__preview-stage--${
					banner.position || 'bottom'
				} ${ device === 'mobile' ? 'is-mobile' : '' }` }
			>
				{ phase !== 'result' && (
					<div
						className={ `consentaro-admin__preview-banner ${
							phase === 'fading' ? 'is-fading' : ''
						}` }
						style={ {
							'--consentaro-bg': banner.bg || '#ffffff',
							'--consentaro-text': banner.text_color || '#1a1a1a',
							'--consentaro-btn-bg': banner.btn_primary_bg || '#0E7C66',
							'--consentaro-btn-text': banner.btn_primary_text || '#ffffff',
							'--consentaro-btn-secondary-bg':
								banner.btn_secondary_bg || '#f0f0f1',
							'--consentaro-btn-secondary-text':
								banner.btn_secondary_text || '#1a1a1a',
							'--consentaro-radius': `${ banner.border_radius ?? 8 }px`,
							'--consentaro-btn-radius': `${
								banner.btn_border_radius ?? 4
							}px`,
							'--consentaro-btn-direction': btnDirection,
							'--consentaro-btn-justify': btnJustify,
							'--consentaro-btn-margin': btnMargin,
							'--consentaro-font-size': `${ banner.font_size ?? 13 }px`,
						} }
					>
						<p>{ banner.text || __( 'Banner text…', 'consentaro' ) }</p>
						<div className="consentaro-admin__preview-actions">
							<button
								type="button"
								className="is-primary"
								onClick={ () => triggerAction( 'accept' ) }
							>
								{ __( 'Accept All', 'consentaro' ) }
							</button>
							<button
								type="button"
								className="is-secondary"
								onClick={ () => triggerAction( 'deny' ) }
							>
								{ __( 'Deny All', 'consentaro' ) }
							</button>
							<button
								type="button"
								className="is-tertiary"
								onClick={ () => triggerAction( 'customize' ) }
							>
								{ __( 'Customize', 'consentaro' ) }
							</button>
						</div>
					</div>
				) }
				{ phase === 'result' && result && (
					<div className="consentaro-admin__preview-result">
						<p
							className={ `consentaro-admin__preview-result-text ${
								result.isSuccess ? 'is-success' : ''
							}` }
						>
							{ result.text }
						</p>
						<button
							type="button"
							className="consentaro-admin__preview-reset"
							onClick={ resetPreview }
						>
							{ __( '↺ Reset preview', 'consentaro' ) }
						</button>
					</div>
				) }
			</div>
		</div>
	);
};

const DesignTab = ( { form, onChange, onReset, onSave, saving } ) => {
	const banner = form.banner || {};

	const applyPreset = ( colors ) => {
		Object.entries( colors ).forEach( ( [ key, value ] ) => onChange( key, value ) );
	};

	return (
		<div className="consentaro-admin__design">
			<Card className="consentaro-admin__card">
				<CardHeader>
					<strong>{ __( 'Banner', 'consentaro' ) }</strong>
					<span className="consentaro-admin__card-sub">
						{ __(
							'Position, copy, colors, shape & layout',
							'consentaro'
						) }
					</span>
				</CardHeader>
				<CardBody>
					<VStack spacing={ 4 }>
						<div className="consentaro-admin__field">
							<BaseControl
								label={ __( 'Theme presets', 'consentaro' ) }
								help={ __(
									'Applies colors only — position, text, and radius stay as they are.',
									'consentaro'
								) }
								__nextHasNoMarginBottom
							>
								<PresetPicker onApply={ applyPreset } banner={ banner } />
							</BaseControl>
						</div>

						<div className="consentaro-admin__section-group">
							<SectionHeading>{ __( 'Content', 'consentaro' ) }</SectionHeading>

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
										onChange( 'position', position )
									}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							</div>

							<TextareaControl
								label={ __( 'Banner text', 'consentaro' ) }
								value={ banner.text || '' }
								onChange={ ( text ) => onChange( 'text', text ) }
								rows={ 3 }
								__nextHasNoMarginBottom
							/>
						</div>

						<div className="consentaro-admin__section-group">
							<SectionHeading>{ __( 'Colors', 'consentaro' ) }</SectionHeading>

							<div className="consentaro-admin__colors">
								<ColorField
									label={ __( 'Background', 'consentaro' ) }
									color={ banner.bg || '#ffffff' }
									onChange={ ( bg ) => onChange( 'bg', bg ) }
								/>
								<ColorField
									label={ __( 'Text', 'consentaro' ) }
									color={ banner.text_color || '#1a1a1a' }
									onChange={ ( text_color ) =>
										onChange( 'text_color', text_color )
									}
								/>
								<ColorField
									label={ __( 'Button background', 'consentaro' ) }
									color={ banner.btn_primary_bg || '#0E7C66' }
									onChange={ ( btn_primary_bg ) =>
										onChange( 'btn_primary_bg', btn_primary_bg )
									}
								/>
								<ColorField
									label={ __( 'Button text', 'consentaro' ) }
									color={ banner.btn_primary_text || '#ffffff' }
									onChange={ ( btn_primary_text ) =>
										onChange( 'btn_primary_text', btn_primary_text )
									}
								/>
								<ColorField
									label={ __(
										'Secondary button background',
										'consentaro'
									) }
									color={ banner.btn_secondary_bg || '#f0f0f1' }
									onChange={ ( btn_secondary_bg ) =>
										onChange( 'btn_secondary_bg', btn_secondary_bg )
									}
								/>
								<ColorField
									label={ __( 'Secondary button text', 'consentaro' ) }
									color={ banner.btn_secondary_text || '#1a1a1a' }
									onChange={ ( btn_secondary_text ) =>
										onChange( 'btn_secondary_text', btn_secondary_text )
									}
								/>
							</div>
						</div>

						<div className="consentaro-admin__section-group">
							<SectionHeading>{ __( 'Shape & size', 'consentaro' ) }</SectionHeading>

							<div className="consentaro-admin__field-grid">
								<RangeControl
									label={ __( 'Banner corner radius', 'consentaro' ) }
									value={ banner.border_radius ?? 8 }
									onChange={ ( border_radius ) =>
										onChange( 'border_radius', border_radius )
									}
									min={ 0 }
									max={ 24 }
									__nextHasNoMarginBottom
								/>

								<RangeControl
									label={ __( 'Button corner radius', 'consentaro' ) }
									value={ banner.btn_border_radius ?? 4 }
									onChange={ ( btn_border_radius ) =>
										onChange( 'btn_border_radius', btn_border_radius )
									}
									min={ 0 }
									max={ 24 }
									__nextHasNoMarginBottom
								/>
							</div>

							<RangeControl
								label={ __( 'Font size', 'consentaro' ) }
								value={ banner.font_size ?? 13 }
								onChange={ ( font_size ) =>
									onChange( 'font_size', font_size )
								}
								min={ 11 }
								max={ 18 }
								__nextHasNoMarginBottom
							/>
						</div>

						<div className="consentaro-admin__section-group">
							<SectionHeading>{ __( 'Layout', 'consentaro' ) }</SectionHeading>

							<div className="consentaro-admin__field-grid">
								<ToggleGroupControl
									label={ __( 'Button layout', 'consentaro' ) }
									value={ banner.btn_layout || 'inline' }
									onChange={ ( btn_layout ) =>
										onChange( 'btn_layout', btn_layout )
									}
									isBlock
									__nextHasNoMarginBottom
								>
									<ToggleGroupControlOption
										value="inline"
										label={ __( 'Inline', 'consentaro' ) }
									/>
									<ToggleGroupControlOption
										value="stacked"
										label={ __( 'Stacked', 'consentaro' ) }
									/>
								</ToggleGroupControl>

								<ToggleGroupControl
									label={ __( 'Button alignment', 'consentaro' ) }
									value={ banner.btn_align || 'left' }
									onChange={ ( btn_align ) =>
										onChange( 'btn_align', btn_align )
									}
									isBlock
									__nextHasNoMarginBottom
								>
									<ToggleGroupControlOption
										value="left"
										label={ __( 'Left', 'consentaro' ) }
									/>
									<ToggleGroupControlOption
										value="center"
										label={ __( 'Center', 'consentaro' ) }
									/>
									<ToggleGroupControlOption
										value="right"
										label={ __( 'Right', 'consentaro' ) }
									/>
								</ToggleGroupControl>
							</div>
							<p className="consentaro-admin__field-help">
								{ __(
									'Alignment is most visible with stacked buttons or the center modal position.',
									'consentaro'
								) }
							</p>
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
						<Button
							variant="tertiary"
							disabled={ saving }
							onClick={ onReset }
							__next40pxDefaultSize
						>
							{ __( 'Reset to defaults', 'consentaro' ) }
						</Button>
					</HStack>
				</CardFooter>
			</Card>

			<BannerPreview banner={ banner } />
		</div>
	);
};

export default DesignTab;
