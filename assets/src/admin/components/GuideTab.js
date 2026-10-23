import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Notice as WPNotice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import SetupChecklist from './SetupChecklist';

const SECTIONS = [
	{ id: 'intro', label: 'What is Consentaro?' },
	{ id: 'import', label: 'Switching plugins?' },
	{ id: 'start', label: 'Quick start' },
	{ id: 'general', label: 'General settings' },
	{ id: 'design', label: 'Banner design' },
	{ id: 'banner', label: 'How the banner works' },
	{ id: 'gtm', label: 'Google Tag Manager' },
	{ id: 'geo', label: 'Location (EU banner)' },
	{ id: 'woo', label: 'WooCommerce' },
	{ id: 'faq', label: 'FAQ' },
];

const BANNER_FIELD_LABELS = {
	position: __( 'Position', 'consentaro' ),
	text: __( 'Banner text', 'consentaro' ),
	bg: __( 'Background', 'consentaro' ),
	text_color: __( 'Text color', 'consentaro' ),
	btn_primary_bg: __( 'Primary button', 'consentaro' ),
	btn_secondary_bg: __( 'Secondary button', 'consentaro' ),
};

const ColorSwatch = ( { value } ) => (
	<span className="consentaro-import__swatch-row">
		<span
			className="consentaro-import__swatch"
			style={ { backgroundColor: value } }
			aria-hidden="true"
		/>
		{ value }
	</span>
);

const ImportDiffRow = ( { label, fieldKey, before, after } ) => {
	const isColor = fieldKey.indexOf( '_bg' ) !== -1 || fieldKey === 'bg';
	return (
		<tr>
			<th scope="row">{ label }</th>
			<td>{ isColor ? <ColorSwatch value={ before } /> : String( before ) }</td>
			<td>{ isColor ? <ColorSwatch value={ after } /> : String( after ) }</td>
		</tr>
	);
};

const ImportCard = ( { source, currentBanner, onImported } ) => {
	const [ preview, setPreview ] = useState( null );
	const [ loadingPreview, setLoadingPreview ] = useState( false );
	const [ applying, setApplying ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ applied, setApplied ] = useState( false );

	const loadPreview = useCallback( () => {
		setLoadingPreview( true );
		setError( null );
		apiFetch( { path: `/consentaro/v1/import/preview?source=${ source.source }` } )
			.then( ( data ) => {
				if ( data.error ) {
					setError( data.error );
					setPreview( null );
				} else {
					setPreview( data );
				}
			} )
			.catch( () =>
				setError( __( 'Could not read settings from this plugin.', 'consentaro' ) )
			)
			.finally( () => setLoadingPreview( false ) );
	}, [ source.source ] );

	const apply = useCallback( () => {
		setApplying( true );
		setError( null );
		apiFetch( {
			path: '/consentaro/v1/import/apply',
			method: 'POST',
			data: { source: source.source },
		} )
			.then( ( data ) => {
				if ( ! data.success ) {
					setError( __( 'Import could not be applied.', 'consentaro' ) );
					return;
				}
				setApplied( true );
				setConfirming( false );
				if ( onImported ) {
					onImported( data.settings );
				}
			} )
			.catch( () => setError( __( 'Import could not be applied.', 'consentaro' ) ) )
			.finally( () => setApplying( false ) );
	}, [ source.source, onImported ] );

	if ( ! source.import_supported ) {
		return (
			<div className="consentaro-import__card consentaro-import__card--unsupported">
				<p>
					{ sprintf(
						/* translators: %s: plugin name, e.g. "CookieYes" */
						__(
							'%s detected — automatic import isn’t available for this plugin yet. You’ll need to configure your banner manually in Consentaro.',
							'consentaro'
						),
						source.name
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="consentaro-import__card">
			{ ! preview && ! applied && (
				<>
					<p>
						{ sprintf(
							/* translators: %s: plugin name, e.g. "Complianz" */
							__(
								'We found %s active on this site. Import its banner text, position, and colors into Consentaro?',
								'consentaro'
							),
							source.name
						) }
					</p>
					<Button variant="secondary" onClick={ loadPreview } isBusy={ loadingPreview } disabled={ loadingPreview }>
						{ loadingPreview ? __( 'Reading settings…', 'consentaro' ) : __( 'Preview', 'consentaro' ) }
					</Button>
				</>
			) }

			{ error && (
				<WPNotice status="error" isDismissible={ false }>
					{ error }
				</WPNotice>
			) }

			{ preview && ! applied && (
				<>
					<table className="consentaro-import__diff-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Setting', 'consentaro' ) }</th>
								<th scope="col">{ __( 'Current (Consentaro)', 'consentaro' ) }</th>
								<th scope="col">{ sprintf( __( 'From %s', 'consentaro' ), source.name ) }</th>
							</tr>
						</thead>
						<tbody>
							{ Object.keys( BANNER_FIELD_LABELS ).map( ( key ) => (
								<ImportDiffRow
									key={ key }
									label={ BANNER_FIELD_LABELS[ key ] }
									fieldKey={ key }
									before={ currentBanner?.[ key ] ?? '' }
									after={ preview.banner?.[ key ] ?? '' }
								/>
							) ) }
						</tbody>
					</table>

					{ preview.notes && preview.notes.length > 0 && (
						<ul className="consentaro-import__notes">
							{ preview.notes.map( ( note, i ) => (
								<li key={ i }>{ note }</li>
							) ) }
						</ul>
					) }

					{ ! confirming && (
						<Button variant="primary" onClick={ () => setConfirming( true ) }>
							{ __( 'Apply', 'consentaro' ) }
						</Button>
					) }

					{ confirming && (
						<div className="consentaro-import__confirm">
							<p>
								{ __(
									'This will overwrite your current banner text, position, and colors in Consentaro. This cannot be undone automatically.',
									'consentaro'
								) }
							</p>
							<Button variant="primary" isDestructive onClick={ apply } isBusy={ applying } disabled={ applying }>
								{ applying ? __( 'Applying…', 'consentaro' ) : __( 'Yes, overwrite and apply', 'consentaro' ) }
							</Button>
							<Button variant="tertiary" onClick={ () => setConfirming( false ) } disabled={ applying }>
								{ __( 'Cancel', 'consentaro' ) }
							</Button>
						</div>
					) }
				</>
			) }

			{ applied && (
				<WPNotice status="success" isDismissible={ false }>
					{ sprintf(
						__( 'Imported from %s. Review the Design tab to confirm it looks right.', 'consentaro' ),
						source.name
					) }
				</WPNotice>
			) }

			<p className="consentaro-import__note">
				{ sprintf(
					/* translators: %s: plugin name */
					__(
						'This imports banner text, position, and colors only — other settings need manual configuration. Remember to deactivate %s once you’ve confirmed Consentaro is set up correctly, so both plugins don’t show a banner at the same time.',
						'consentaro'
					),
					source.name
				) }
			</p>
		</div>
	);
};

const ImportSection = ( { onImported, currentBanner } ) => {
	const [ sources, setSources ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/import/detect' } )
			.then( ( data ) => setSources( Array.isArray( data ) ? data : [] ) )
			.catch( () => setSources( [] ) )
			.finally( () => setLoading( false ) );
	}, [] );

	if ( loading ) {
		return (
			<section id="consentaro-guide-import" className="consentaro-guide__section">
				<Spinner />
			</section>
		);
	}

	if ( ! sources || sources.length === 0 ) {
		return null;
	}

	return (
		<section id="consentaro-guide-import" className="consentaro-guide__section">
			<h2>{ __( 'Switching from another cookie plugin?', 'consentaro' ) }</h2>
			{ sources.map( ( source ) => (
				<ImportCard
					key={ source.source }
					source={ source }
					currentBanner={ currentBanner }
					onImported={ onImported }
				/>
			) ) }
		</section>
	);
};

const ModuleCard = ( { mark, title, children, defaultOpen = false } ) => {
	const [ open, setOpen ] = useState( defaultOpen );

	return (
		<div className={ `consentaro-guide__module ${ open ? 'is-open' : '' }` }>
			<button
				type="button"
				className="consentaro-guide__module-toggle"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
			>
				<span className="consentaro-guide__module-left">
					<span className="consentaro-guide__module-mark" aria-hidden="true">
						{ mark }
					</span>
					<strong>{ title }</strong>
				</span>
				<span className="consentaro-guide__chevron" aria-hidden="true">
					{ open ? '▾' : '▸' }
				</span>
			</button>
			{ open && <div className="consentaro-guide__module-body">{ children }</div> }
		</div>
	);
};

const GuideTab = ( {
	settings,
	setup,
	homeUrl,
	onGoToTab,
	onImported,
	onSkipGtm,
	onConfirmText,
	onMarkTested,
} ) => {
	const [ active, setActive ] = useState( 'intro' );

	const scrollTo = ( id ) => {
		setActive( id );
		const el = document.getElementById( `consentaro-guide-${ id }` );
		if ( el ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	};

	return (
		<div className="consentaro-guide">
			<aside className="consentaro-guide__nav" aria-label={ __( 'Guide sections', 'consentaro' ) }>
				<p className="consentaro-guide__nav-title">{ __( 'On this page', 'consentaro' ) }</p>
				<ul>
					{ SECTIONS.map( ( s ) => (
						<li key={ s.id }>
							<button
								type="button"
								className={ active === s.id ? 'is-active' : '' }
								onClick={ () => scrollTo( s.id ) }
							>
								{ s.label }
							</button>
						</li>
					) ) }
				</ul>
			</aside>

			<div className="consentaro-guide__content">
				<section id="consentaro-guide-intro" className="consentaro-guide__section">
					<div className="consentaro-guide__hero">
						<span className="consentaro-guide__badge">{ __( 'Start here', 'consentaro' ) }</span>
						<h2>{ __( 'What is Consentaro?', 'consentaro' ) }</h2>
						<p className="consentaro-guide__lead">
							{ __(
								'Consentaro is a simple helper for your WordPress website. It shows a clear cookie message, remembers each visitor’s choice, and tells Google (and your ads/analytics tools) what is allowed — so you can measure traffic and run ads without guessing.',
								'consentaro'
							) }
						</p>
					</div>

					<div className="consentaro-guide__plain">
						<h3>{ __( 'In everyday words', 'consentaro' ) }</h3>
						<ol className="consentaro-guide__steps">
							<li>{ __( 'A visitor opens your site.', 'consentaro' ) }</li>
							<li>
								{ __(
									'If needed, they see a short cookie banner.',
									'consentaro'
								) }
							</li>
							<li>
								{ __(
									'They tap Accept, Deny, or Customize.',
									'consentaro'
								) }
							</li>
							<li>
								{ __(
									'Consentaro saves that choice and only then loads Google Tag Manager (if you use it).',
									'consentaro'
								) }
							</li>
							<li>
								{ __(
									'Store tracking (like “add to cart”) only runs when the visitor allowed it.',
									'consentaro'
								) }
							</li>
						</ol>
						<p className="consentaro-guide__note">
							{ __(
								'You do not need to be a developer. Fill in a few settings, save, and test once in a private browser window.',
								'consentaro'
							) }
						</p>
					</div>
				</section>

				<ImportSection onImported={ onImported } currentBanner={ settings?.banner } />

				<section id="consentaro-guide-start" className="consentaro-guide__section">
					<h2>{ __( 'Quick start checklist', 'consentaro' ) }</h2>
					<p>
						{ __(
							'This checklist is based on your saved settings, so it always matches what is actually set up.',
							'consentaro'
						) }
					</p>

					<SetupChecklist
						setup={ setup }
						homeUrl={ homeUrl }
						onGoToTab={ onGoToTab }
						onSkipGtm={ onSkipGtm }
						onConfirmText={ onConfirmText }
						onMarkTested={ onMarkTested }
					/>

					<div className="consentaro-guide__actions">
						<Button
							variant="primary"
							onClick={ () => onGoToTab( 'general' ) }
							__next40pxDefaultSize
						>
							{ __( 'Open General settings', 'consentaro' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => onGoToTab( 'design' ) }
							__next40pxDefaultSize
						>
							{ __( 'Open Design', 'consentaro' ) }
						</Button>
					</div>
				</section>

				<section id="consentaro-guide-general" className="consentaro-guide__section">
					<h2>{ __( 'Module guides', 'consentaro' ) }</h2>
					<p className="consentaro-guide__section-intro">
						{ __(
							'Tap a module to expand the how-to. Everything is written for non-technical users.',
							'consentaro'
						) }
					</p>

					<ModuleCard
						mark="1"
						title={ __( 'General settings', 'consentaro' ) }
						defaultOpen
					>
						<ul className="consentaro-guide__bullets">
							<li>
								<strong>{ __( 'Enable Consentaro', 'consentaro' ) }</strong>
								{ ' — ' }
								{ __(
									'The main power switch. Turn this off only if you need to pause the plugin temporarily.',
									'consentaro'
								) }
							</li>
							<li>
								<strong>{ __( 'GTM Container ID', 'consentaro' ) }</strong>
								{ ' — ' }
								{ __(
									'If you use Google Tag Manager, paste an ID that looks like GTM-XXXXXXX. Leave blank if you are not ready yet — the banner still works.',
									'consentaro'
								) }
							</li>
							<li>
								<strong>{ __( 'Geo-based banner', 'consentaro' ) }</strong>
								{ ' — ' }
								{ __(
									'When ON, the banner focuses on visitors who need a consent choice (such as the EU). When OFF, more visitors may see the banner. The “Detected” badge shows what the plugin thinks about your current location while you are logged into admin.',
									'consentaro'
								) }
							</li>
						</ul>
						<Button variant="link" onClick={ () => onGoToTab( 'general' ) }>
							{ __( 'Edit General →', 'consentaro' ) }
						</Button>
					</ModuleCard>

					<div id="consentaro-guide-design">
						<ModuleCard mark="2" title={ __( 'Banner design', 'consentaro' ) }>
							<ul className="consentaro-guide__bullets">
								<li>
									{ __(
										'Pick where the message appears: bottom bar, top bar, corner, or center.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Write a short, friendly sentence visitors will understand.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Choose colors that match your brand. Watch the live preview update as you change them.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Click Save changes when you are happy with the look.',
										'consentaro'
									) }
								</li>
							</ul>
							<Button variant="link" onClick={ () => onGoToTab( 'design' ) }>
								{ __( 'Edit Design →', 'consentaro' ) }
							</Button>
						</ModuleCard>
					</div>

					<div id="consentaro-guide-banner">
						<ModuleCard
							mark="3"
							title={ __( 'How the banner works', 'consentaro' ) }
						>
							<ul className="consentaro-guide__bullets">
								<li>
									<strong>{ __( 'Accept All', 'consentaro' ) }</strong>
									{ ' — ' }
									{ __(
										'Visitor allows analytics and ads-related storage.',
										'consentaro'
									) }
								</li>
								<li>
									<strong>{ __( 'Deny All', 'consentaro' ) }</strong>
									{ ' — ' }
									{ __(
										'Visitor refuses optional tracking. Essential site functions still work.',
										'consentaro'
									) }
								</li>
								<li>
									<strong>{ __( 'Customize', 'consentaro' ) }</strong>
									{ ' — ' }
									{ __(
										'Visitor picks categories one by one, then saves.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'After a choice, the banner hides and the decision is remembered (so they are not asked again every visit).',
										'consentaro'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>

					<div id="consentaro-guide-gtm">
						<ModuleCard
							mark="4"
							title={ __( 'Google Tag Manager (GTM)', 'consentaro' ) }
						>
							<ul className="consentaro-guide__bullets">
								<li>
									{ __(
										'GTM is a toolbox that loads your analytics and ad tags.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Consentaro waits for a visitor choice before loading GTM when a banner is shown. That way tags do not fire before permission.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'If someone already chose earlier, GTM can load right away on the next visit.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Tip: after saving your GTM ID, open your site → Accept → check the browser Network tab for gtm.js.',
										'consentaro'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>

					<div id="consentaro-guide-geo">
						<ModuleCard
							mark="5"
							title={ __( 'Location / EU banner', 'consentaro' ) }
						>
							<ul className="consentaro-guide__bullets">
								<li>
									{ __(
										'The plugin tries to learn the visitor’s country in a light way (for example via your CDN or server, and a small backup lookup if needed).',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'This helps show the banner where privacy rules usually expect a clear choice.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'On a local test site, country may show as unknown — the banner can still appear so you can test easily.',
										'consentaro'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>

					<div id="consentaro-guide-woo">
						<ModuleCard
							mark="6"
							title={ __( 'WooCommerce shop events', 'consentaro' ) }
						>
							<ul className="consentaro-guide__bullets">
								<li>
									{ __(
										'If you sell with WooCommerce, Consentaro can send helpful shop events (view product, add to cart, purchase) into the data layer — only when the visitor allowed it.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Add to cart / view product need analytics permission.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'Purchase needs both analytics and ads-related permission.',
										'consentaro'
									) }
								</li>
								<li>
									{ __(
										'No extra setup is required beyond enabling Consentaro and connecting GTM if you use it.',
										'consentaro'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>
				</section>

				<section id="consentaro-guide-faq" className="consentaro-guide__section">
					<h2>{ __( 'Common questions', 'consentaro' ) }</h2>
					<ModuleCard
						mark="?"
						title={ __( 'I do not see the banner on my site', 'consentaro' ) }
					>
						<p>
							{ __(
								'Try a private/incognito window (old choices may be saved). Confirm Consentaro is enabled. If Geo is on and you are outside the EU, the banner may stay hidden on purpose — turn Geo off temporarily to test.',
								'consentaro'
							) }
						</p>
					</ModuleCard>
					<ModuleCard
						mark="?"
						title={ __( 'Will this slow my website down?', 'consentaro' ) }
					>
						<p>
							{ __(
								'Consentaro is built to stay light. The visitor-facing script is very small, and scripts only load when they are needed.',
								'consentaro'
							) }
						</p>
					</ModuleCard>
					<ModuleCard
						mark="?"
						title={ __(
							'Do I need a lawyer or a huge cookie plugin?',
							'consentaro'
						) }
					>
						<p>
							{ __(
								'Consentaro helps with Google Consent Mode and a clear visitor choice. Privacy rules differ by country and business — this guide is not legal advice. If you are unsure, ask a professional for your region.',
								'consentaro'
							) }
						</p>
					</ModuleCard>

					<div className="consentaro-guide__footer-cta">
						<div>
							<strong>
								{ __(
									'You are ready when the checklist feels complete.',
									'consentaro'
								) }
							</strong>
							<p>
								{ __(
									'Save your settings, test once as a visitor, and you are good to go.',
									'consentaro'
								) }
							</p>
						</div>
						<Button
							variant="primary"
							onClick={ () => scrollTo( 'start' ) }
							__next40pxDefaultSize
						>
							{ __( 'Back to checklist', 'consentaro' ) }
						</Button>
					</div>
				</section>
			</div>
		</div>
	);
};

export default GuideTab;
