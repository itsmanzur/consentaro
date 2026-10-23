import {
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	SelectControl,
	TextControl,
	TextareaControl,
	RangeControl,
	Button,
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
import { __, sprintf } from '@wordpress/i18n';
import { checkContrast } from './contrast';

const admin = window.consentaroAdmin || {};
const DEFAULTS = admin.defaults || {};

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

// Same list (and order) the front-end preferences modal renders.
const PREVIEW_CATEGORIES = [
	__( 'Analytics', 'consentaro' ),
	__( 'Advertising', 'consentaro' ),
	__( 'Ad user data', 'consentaro' ),
	__( 'Ad personalization', 'consentaro' ),
	__( 'Personalization', 'consentaro' ),
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

// A label equal to the built-in default is shown blank (with the default as
// placeholder), so "use the default" and "customized" are visibly different.
const customLabel = ( value, fallback ) =>
	! value || value === fallback ? '' : value;

const SectionHeading = ( { children } ) => (
	<h3 className="consentaro-admin__section-heading">{ children }</h3>
);

const ContrastBadge = ( { fg, bg } ) => {
	const { ratio, passes } = checkContrast( fg, bg );
	if ( ratio === null ) {
		return null;
	}
	const value = ratio.toFixed( 1 );
	return (
		<span
			className={ `consentaro-contrast ${ passes ? 'is-pass' : 'is-fail' }` }
			title={ sprintf(
				/* translators: %s: contrast ratio, e.g. 4.6 */
				__( 'Contrast ratio %s:1 (WCAG AA needs 4.5:1)', 'consentaro' ),
				value
			) }
		>
			<span aria-hidden="true">{ passes ? '✓' : '✕' }</span>
			<span>
				{ passes
					? __( 'AA pass', 'consentaro' )
					: __( 'AA fail', 'consentaro' ) }
			</span>
			<span className="consentaro-contrast__ratio">{ value }:1</span>
		</span>
	);
};

const ColorField = ( { label, color, onChange, contrastWith } ) => (
	<BaseControl
		label={ label }
		id={ `consentaro-color-${ label }` }
		className="consentaro-admin__color-field"
		__nextHasNoMarginBottom
	>
		<div className="consentaro-admin__color-row">
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
			{ contrastWith && <ContrastBadge fg={ color } bg={ contrastWith } /> }
		</div>
	</BaseControl>
);

const previewVars = ( banner ) => {
	const btnDirection = banner.btn_layout === 'stacked' ? 'column' : 'row';
	const justifyMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
	const marginMap = { left: '0', center: '0 auto', right: '0 0 0 auto' };

	return {
		'--consentaro-bg': banner.bg || '#ffffff',
		'--consentaro-text': banner.text_color || '#1a1a1a',
		'--consentaro-btn-bg': banner.btn_primary_bg || '#0E7C66',
		'--consentaro-btn-text': banner.btn_primary_text || '#ffffff',
		'--consentaro-btn-secondary-bg': banner.btn_secondary_bg || '#f0f0f1',
		'--consentaro-btn-secondary-text': banner.btn_secondary_text || '#1a1a1a',
		'--consentaro-radius': `${ banner.border_radius ?? 8 }px`,
		'--consentaro-btn-radius': `${ banner.btn_border_radius ?? 4 }px`,
		'--consentaro-btn-direction': btnDirection,
		'--consentaro-btn-justify': justifyMap[ banner.btn_align ] || 'flex-start',
		'--consentaro-btn-margin': marginMap[ banner.btn_align ] || '0',
		'--consentaro-font-size': `${ banner.font_size ?? 13 }px`,
	};
};

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
	// Which surface is previewed: the banner itself or the preferences modal.
	const [ view, setView ] = useState( 'banner' );
	// 'banner' = showing banner; 'fading' = mid fade-out; 'result' = outcome message.
	const [ phase, setPhase ] = useState( 'banner' );
	const [ action, setAction ] = useState( null );
	const fadeTimer = useRef( null );

	useEffect( () => () => {
		if ( fadeTimer.current ) {
			clearTimeout( fadeTimer.current );
		}
	}, [] );

	const triggerAction = ( nextAction ) => {
		if ( nextAction === 'customize' ) {
			setView( 'modal' );
			return;
		}
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
	const vars = previewVars( banner );

	// Mirrors the front end: blank copy/labels fall back to the defaults, so
	// the preview never shows a placeholder.
	const text = banner.text || DEFAULTS.text || '';
	const labelAccept = banner.label_accept || DEFAULTS.label_accept || __( 'Accept All', 'consentaro' );
	const labelDeny = banner.label_deny || DEFAULTS.label_deny || __( 'Deny All', 'consentaro' );
	const labelCustomize =
		banner.label_customize || DEFAULTS.label_customize || __( 'Customize', 'consentaro' );
	const hasPrivacyLink = !! ( banner.privacy_page_id || banner.privacy_url );

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
				className="consentaro-admin__preview-device-toggle consentaro-admin__preview-view-toggle"
				role="group"
				aria-label={ __( 'Preview surface', 'consentaro' ) }
			>
				<button
					type="button"
					className={ view === 'banner' ? 'is-active' : '' }
					onClick={ () => setView( 'banner' ) }
				>
					{ __( 'Banner', 'consentaro' ) }
				</button>
				<button
					type="button"
					className={ view === 'modal' ? 'is-active' : '' }
					onClick={ () => setView( 'modal' ) }
				>
					{ __( 'Preferences modal', 'consentaro' ) }
				</button>
			</div>

			<div
				className={ `consentaro-admin__preview-stage consentaro-admin__preview-stage--${
					view === 'modal' ? 'modal' : banner.position || 'bottom'
				} ${ device === 'mobile' ? 'is-mobile' : '' }` }
			>
				{ view === 'banner' && phase !== 'result' && (
					<div
						className={ `consentaro-admin__preview-banner ${
							phase === 'fading' ? 'is-fading' : ''
						}` }
						style={ vars }
					>
						<p>
							{ text }
							{ hasPrivacyLink && (
								<>
									{ ' ' }
									<span className="consentaro-admin__preview-link">
										{ __( 'Privacy Policy', 'consentaro' ) }
									</span>
								</>
							) }
						</p>
						<div className="consentaro-admin__preview-actions">
							<button
								type="button"
								className="is-primary"
								onClick={ () => triggerAction( 'accept' ) }
							>
								{ labelAccept }
							</button>
							<button
								type="button"
								className="is-secondary"
								onClick={ () => triggerAction( 'deny' ) }
							>
								{ labelDeny }
							</button>
							<button
								type="button"
								className="is-tertiary"
								onClick={ () => triggerAction( 'customize' ) }
							>
								{ labelCustomize }
							</button>
						</div>
					</div>
				) }

				{ view === 'banner' && phase === 'result' && result && (
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

				{ view === 'modal' && (
					<div className="consentaro-admin__preview-modal" style={ vars }>
						<h4>{ __( 'Customize cookies', 'consentaro' ) }</h4>
						<ul>
							{ PREVIEW_CATEGORIES.map( ( name ) => (
								<li key={ name }>
									<span>{ name }</span>
									<input type="checkbox" tabIndex={ -1 } readOnly aria-hidden="true" />
								</li>
							) ) }
						</ul>
						<div className="consentaro-admin__preview-modal-actions">
							<button type="button" className="is-secondary">
								{ __( 'Cancel', 'consentaro' ) }
							</button>
							<button type="button" className="is-primary">
								{ __( 'Save preferences', 'consentaro' ) }
							</button>
						</div>
					</div>
				) }
			</div>
		</div>
	);
};

const DesignTab = ( { form, onChange, onReset, saving } ) => {
	const banner = form.banner || {};
	const pages = Array.isArray( admin.pages ) ? admin.pages : [];

	const applyPreset = ( colors ) => {
		Object.entries( colors ).forEach( ( [ key, value ] ) => onChange( key, value ) );
	};

	const bg = banner.bg || '#ffffff';
	const btnBg = banner.btn_primary_bg || '#0E7C66';
	const btn2Bg = banner.btn_secondary_bg || '#f0f0f1';
	const pairs = [
		[ banner.text_color || '#1a1a1a', bg ],
		[ banner.btn_primary_text || '#ffffff', btnBg ],
		[ banner.btn_secondary_text || '#1a1a1a', btn2Bg ],
	];
	const anyFail = pairs.some( ( [ fg, back ] ) => ! checkContrast( fg, back ).passes );

	const pageOptions = [
		{ label: __( '— None —', 'consentaro' ), value: '0' },
		...pages.map( ( page ) => ( { label: page.title, value: String( page.id ) } ) ),
	];
	const selectedPage = String( banner.privacy_page_id || 0 );
	// wp_localize_script stringifies numbers, so normalize before comparing.
	const corePrivacyId = Number( admin.privacyPageId ) || 0;
	const canSuggestPage =
		corePrivacyId > 0 &&
		selectedPage === '0' &&
		pages.some( ( page ) => Number( page.id ) === corePrivacyId );

	return (
		<div className="consentaro-admin__design">
			<Card className="consentaro-admin__card">
				<CardHeader>
					<strong>{ __( 'Banner', 'consentaro' ) }</strong>
					<span className="consentaro-admin__card-sub">
						{ __( 'Position, copy, colors, shape & layout', 'consentaro' ) }
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
										{ label: __( 'Bottom bar', 'consentaro' ), value: 'bottom' },
										{ label: __( 'Top bar', 'consentaro' ), value: 'top' },
										{
											label: __( 'Bottom-right floating', 'consentaro' ),
											value: 'bottom-right',
										},
										{ label: __( 'Center modal', 'consentaro' ), value: 'modal' },
									] }
									onChange={ ( position ) => onChange( 'position', position ) }
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

							<div className="consentaro-admin__field-grid consentaro-admin__field-grid--3">
								<TextControl
									label={ __( 'Accept button', 'consentaro' ) }
									value={ customLabel( banner.label_accept, DEFAULTS.label_accept ) }
									placeholder={ DEFAULTS.label_accept }
									maxLength={ 40 }
									onChange={ ( v ) => onChange( 'label_accept', v ) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
								<TextControl
									label={ __( 'Deny button', 'consentaro' ) }
									value={ customLabel( banner.label_deny, DEFAULTS.label_deny ) }
									placeholder={ DEFAULTS.label_deny }
									maxLength={ 40 }
									onChange={ ( v ) => onChange( 'label_deny', v ) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
								<TextControl
									label={ __( 'Customize button', 'consentaro' ) }
									value={ customLabel( banner.label_customize, DEFAULTS.label_customize ) }
									placeholder={ DEFAULTS.label_customize }
									maxLength={ 40 }
									onChange={ ( v ) => onChange( 'label_customize', v ) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							</div>
							<p className="consentaro-admin__field-help">
								{ __(
									'Leave a button blank to use its default label.',
									'consentaro'
								) }
							</p>

							<div className="consentaro-admin__field-grid">
								<SelectControl
									label={ __( 'Privacy policy page', 'consentaro' ) }
									value={ selectedPage }
									options={ pageOptions }
									onChange={ ( id ) =>
										onChange( 'privacy_page_id', parseInt( id, 10 ) || 0 )
									}
									help={ __(
										'Adds a “Privacy Policy” link to the banner.',
										'consentaro'
									) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
								<TextControl
									label={ __( 'Or a custom URL', 'consentaro' ) }
									type="url"
									value={ banner.privacy_url || '' }
									placeholder="https://"
									onChange={ ( v ) => onChange( 'privacy_url', v ) }
									help={ __(
										'Used only when no page is chosen.',
										'consentaro'
									) }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							</div>
							{ canSuggestPage && (
								<Button
									variant="link"
									onClick={ () =>
										onChange( 'privacy_page_id', corePrivacyId )
									}
								>
									{ __(
										'Use my site’s Privacy Policy page',
										'consentaro'
									) }
								</Button>
							) }
						</div>

						<div className="consentaro-admin__section-group">
							<SectionHeading>{ __( 'Colors', 'consentaro' ) }</SectionHeading>

							<div className="consentaro-admin__colors">
								<ColorField
									label={ __( 'Background', 'consentaro' ) }
									color={ bg }
									onChange={ ( v ) => onChange( 'bg', v ) }
								/>
								<ColorField
									label={ __( 'Text', 'consentaro' ) }
									color={ banner.text_color || '#1a1a1a' }
									contrastWith={ bg }
									onChange={ ( v ) => onChange( 'text_color', v ) }
								/>
								<ColorField
									label={ __( 'Button background', 'consentaro' ) }
									color={ btnBg }
									onChange={ ( v ) => onChange( 'btn_primary_bg', v ) }
								/>
								<ColorField
									label={ __( 'Button text', 'consentaro' ) }
									color={ banner.btn_primary_text || '#ffffff' }
									contrastWith={ btnBg }
									onChange={ ( v ) => onChange( 'btn_primary_text', v ) }
								/>
								<ColorField
									label={ __( 'Secondary button background', 'consentaro' ) }
									color={ btn2Bg }
									onChange={ ( v ) => onChange( 'btn_secondary_bg', v ) }
								/>
								<ColorField
									label={ __( 'Secondary button text', 'consentaro' ) }
									color={ banner.btn_secondary_text || '#1a1a1a' }
									contrastWith={ btn2Bg }
									onChange={ ( v ) => onChange( 'btn_secondary_text', v ) }
								/>
							</div>
							<p
								className={ `consentaro-admin__field-help consentaro-contrast-summary ${
									anyFail ? 'is-fail' : 'is-pass'
								}` }
							>
								{ anyFail
									? __(
											'Some text/background pairs are below WCAG AA (4.5:1) and may be hard to read. This is a warning — you can still save.',
											'consentaro'
									  )
									: __(
											'All text and button colors meet WCAG AA contrast (4.5:1).',
											'consentaro'
									  ) }
							</p>
						</div>

						<div className="consentaro-admin__section-group">
							<SectionHeading>{ __( 'Shape & size', 'consentaro' ) }</SectionHeading>

							<div className="consentaro-admin__field-grid">
								<RangeControl
									label={ __( 'Banner corner radius', 'consentaro' ) }
									value={ banner.border_radius ?? 8 }
									onChange={ ( v ) => onChange( 'border_radius', v ) }
									min={ 0 }
									max={ 24 }
									__nextHasNoMarginBottom
								/>

								<RangeControl
									label={ __( 'Button corner radius', 'consentaro' ) }
									value={ banner.btn_border_radius ?? 4 }
									onChange={ ( v ) => onChange( 'btn_border_radius', v ) }
									min={ 0 }
									max={ 24 }
									__nextHasNoMarginBottom
								/>
							</div>

							<RangeControl
								label={ __( 'Font size', 'consentaro' ) }
								value={ banner.font_size ?? 13 }
								onChange={ ( v ) => onChange( 'font_size', v ) }
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
									onChange={ ( v ) => onChange( 'btn_layout', v ) }
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
									onChange={ ( v ) => onChange( 'btn_align', v ) }
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
