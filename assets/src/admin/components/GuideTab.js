import { useState, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STORAGE_KEY = 'consentflow_guide_checklist_v1';

const SECTIONS = [
	{ id: 'intro', label: 'What is ConsentFlow?' },
	{ id: 'start', label: 'Quick start' },
	{ id: 'general', label: 'General settings' },
	{ id: 'design', label: 'Banner design' },
	{ id: 'banner', label: 'How the banner works' },
	{ id: 'gtm', label: 'Google Tag Manager' },
	{ id: 'geo', label: 'Location (EU banner)' },
	{ id: 'woo', label: 'WooCommerce' },
	{ id: 'faq', label: 'FAQ' },
];

const CHECKLIST = [
	{ id: 'enable', label: 'Turn ConsentFlow on in General' },
	{ id: 'gtm', label: 'Add your GTM container ID (if you use GTM)' },
	{ id: 'design', label: 'Choose banner position and colors' },
	{ id: 'geo', label: 'Decide if the banner should only show in the EU' },
	{ id: 'test', label: 'Visit your site in a private window and test Accept / Deny' },
];

const ModuleCard = ( { mark, title, children, defaultOpen = false } ) => {
	const [ open, setOpen ] = useState( defaultOpen );

	return (
		<div className={ `cf-guide__module ${ open ? 'is-open' : '' }` }>
			<button
				type="button"
				className="cf-guide__module-toggle"
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
			>
				<span className="cf-guide__module-left">
					<span className="cf-guide__module-mark" aria-hidden="true">
						{ mark }
					</span>
					<strong>{ title }</strong>
				</span>
				<span className="cf-guide__chevron" aria-hidden="true">
					{ open ? '▾' : '▸' }
				</span>
			</button>
			{ open && <div className="cf-guide__module-body">{ children }</div> }
		</div>
	);
};

const GuideTab = ( { onGoToTab } ) => {
	const [ active, setActive ] = useState( 'intro' );
	const [ checks, setChecks ] = useState( {} );

	useEffect( () => {
		try {
			const raw = window.localStorage.getItem( STORAGE_KEY );
			if ( raw ) {
				setChecks( JSON.parse( raw ) );
			}
		} catch ( e ) {
			// Ignore storage errors.
		}
	}, [] );

	const toggleCheck = ( id ) => {
		setChecks( ( prev ) => {
			const next = { ...prev, [ id ]: ! prev[ id ] };
			try {
				window.localStorage.setItem( STORAGE_KEY, JSON.stringify( next ) );
			} catch ( e ) {
				// Ignore.
			}
			return next;
		} );
	};

	const doneCount = CHECKLIST.filter( ( item ) => checks[ item.id ] ).length;
	const progress = Math.round( ( doneCount / CHECKLIST.length ) * 100 );

	const scrollTo = ( id ) => {
		setActive( id );
		const el = document.getElementById( `cf-guide-${ id }` );
		if ( el ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	};

	return (
		<div className="cf-guide">
			<aside className="cf-guide__nav" aria-label={ __( 'Guide sections', 'consentflow' ) }>
				<p className="cf-guide__nav-title">{ __( 'On this page', 'consentflow' ) }</p>
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

			<div className="cf-guide__content">
				<section id="cf-guide-intro" className="cf-guide__section">
					<div className="cf-guide__hero">
						<span className="cf-guide__badge">{ __( 'Start here', 'consentflow' ) }</span>
						<h2>{ __( 'What is ConsentFlow?', 'consentflow' ) }</h2>
						<p className="cf-guide__lead">
							{ __(
								'ConsentFlow is a simple helper for your WordPress website. It shows a clear cookie message, remembers each visitor’s choice, and tells Google (and your ads/analytics tools) what is allowed — so you can measure traffic and run ads without guessing.',
								'consentflow'
							) }
						</p>
					</div>

					<div className="cf-guide__plain">
						<h3>{ __( 'In everyday words', 'consentflow' ) }</h3>
						<ol className="cf-guide__steps">
							<li>{ __( 'A visitor opens your site.', 'consentflow' ) }</li>
							<li>
								{ __(
									'If needed, they see a short cookie banner.',
									'consentflow'
								) }
							</li>
							<li>
								{ __(
									'They tap Accept, Deny, or Customize.',
									'consentflow'
								) }
							</li>
							<li>
								{ __(
									'ConsentFlow saves that choice and only then loads Google Tag Manager (if you use it).',
									'consentflow'
								) }
							</li>
							<li>
								{ __(
									'Store tracking (like “add to cart”) only runs when the visitor allowed it.',
									'consentflow'
								) }
							</li>
						</ol>
						<p className="cf-guide__note">
							{ __(
								'You do not need to be a developer. Fill in a few settings, save, and test once in a private browser window.',
								'consentflow'
							) }
						</p>
					</div>
				</section>

				<section id="cf-guide-start" className="cf-guide__section">
					<h2>{ __( 'Quick start checklist', 'consentflow' ) }</h2>
					<p>
						{ __(
							'Tick each step as you finish it. Your progress is saved in this browser.',
							'consentflow'
						) }
					</p>

					<div
						className="cf-guide__progress"
						aria-label={ __( 'Setup progress', 'consentflow' ) }
					>
						<div className="cf-guide__progress-bar">
							<span style={ { width: `${ progress }%` } } />
						</div>
						<span className="cf-guide__progress-label">
							{ doneCount } / { CHECKLIST.length } · { progress }%
						</span>
					</div>

					<ul className="cf-guide__checklist">
						{ CHECKLIST.map( ( item ) => (
							<li key={ item.id }>
								<label>
									<input
										type="checkbox"
										checked={ !! checks[ item.id ] }
										onChange={ () => toggleCheck( item.id ) }
									/>
									<span className={ checks[ item.id ] ? 'is-done' : undefined }>
										{ item.label }
									</span>
								</label>
							</li>
						) ) }
					</ul>

					<div className="cf-guide__actions">
						<Button
							variant="primary"
							onClick={ () => onGoToTab( 'general' ) }
							__next40pxDefaultSize
						>
							{ __( 'Open General settings', 'consentflow' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => onGoToTab( 'design' ) }
							__next40pxDefaultSize
						>
							{ __( 'Open Design', 'consentflow' ) }
						</Button>
					</div>
				</section>

				<section id="cf-guide-general" className="cf-guide__section">
					<h2>{ __( 'Module guides', 'consentflow' ) }</h2>
					<p className="cf-guide__section-intro">
						{ __(
							'Tap a module to expand the how-to. Everything is written for non-technical users.',
							'consentflow'
						) }
					</p>

					<ModuleCard
						mark="1"
						title={ __( 'General settings', 'consentflow' ) }
						defaultOpen
					>
						<ul className="cf-guide__bullets">
							<li>
								<strong>{ __( 'Enable ConsentFlow', 'consentflow' ) }</strong>
								{ ' — ' }
								{ __(
									'The main power switch. Turn this off only if you need to pause the plugin temporarily.',
									'consentflow'
								) }
							</li>
							<li>
								<strong>{ __( 'GTM Container ID', 'consentflow' ) }</strong>
								{ ' — ' }
								{ __(
									'If you use Google Tag Manager, paste an ID that looks like GTM-XXXXXXX. Leave blank if you are not ready yet — the banner still works.',
									'consentflow'
								) }
							</li>
							<li>
								<strong>{ __( 'Geo-based banner', 'consentflow' ) }</strong>
								{ ' — ' }
								{ __(
									'When ON, the banner focuses on visitors who need a consent choice (such as the EU). When OFF, more visitors may see the banner. The “Detected” badge shows what the plugin thinks about your current location while you are logged into admin.',
									'consentflow'
								) }
							</li>
						</ul>
						<Button variant="link" onClick={ () => onGoToTab( 'general' ) }>
							{ __( 'Edit General →', 'consentflow' ) }
						</Button>
					</ModuleCard>

					<div id="cf-guide-design">
						<ModuleCard mark="2" title={ __( 'Banner design', 'consentflow' ) }>
							<ul className="cf-guide__bullets">
								<li>
									{ __(
										'Pick where the message appears: bottom bar, top bar, corner, or center.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'Write a short, friendly sentence visitors will understand.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'Choose colors that match your brand. Watch the live preview update as you change them.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'Click Save changes when you are happy with the look.',
										'consentflow'
									) }
								</li>
							</ul>
							<Button variant="link" onClick={ () => onGoToTab( 'design' ) }>
								{ __( 'Edit Design →', 'consentflow' ) }
							</Button>
						</ModuleCard>
					</div>

					<div id="cf-guide-banner">
						<ModuleCard
							mark="3"
							title={ __( 'How the banner works', 'consentflow' ) }
						>
							<ul className="cf-guide__bullets">
								<li>
									<strong>{ __( 'Accept All', 'consentflow' ) }</strong>
									{ ' — ' }
									{ __(
										'Visitor allows analytics and ads-related storage.',
										'consentflow'
									) }
								</li>
								<li>
									<strong>{ __( 'Deny All', 'consentflow' ) }</strong>
									{ ' — ' }
									{ __(
										'Visitor refuses optional tracking. Essential site functions still work.',
										'consentflow'
									) }
								</li>
								<li>
									<strong>{ __( 'Customize', 'consentflow' ) }</strong>
									{ ' — ' }
									{ __(
										'Visitor picks categories one by one, then saves.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'After a choice, the banner hides and the decision is remembered (so they are not asked again every visit).',
										'consentflow'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>

					<div id="cf-guide-gtm">
						<ModuleCard
							mark="4"
							title={ __( 'Google Tag Manager (GTM)', 'consentflow' ) }
						>
							<ul className="cf-guide__bullets">
								<li>
									{ __(
										'GTM is a toolbox that loads your analytics and ad tags.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'ConsentFlow waits for a visitor choice before loading GTM when a banner is shown. That way tags do not fire before permission.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'If someone already chose earlier, GTM can load right away on the next visit.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'Tip: after saving your GTM ID, open your site → Accept → check the browser Network tab for gtm.js.',
										'consentflow'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>

					<div id="cf-guide-geo">
						<ModuleCard
							mark="5"
							title={ __( 'Location / EU banner', 'consentflow' ) }
						>
							<ul className="cf-guide__bullets">
								<li>
									{ __(
										'The plugin tries to learn the visitor’s country in a light way (for example via your CDN or server, and a small backup lookup if needed).',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'This helps show the banner where privacy rules usually expect a clear choice.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'On a local test site, country may show as unknown — the banner can still appear so you can test easily.',
										'consentflow'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>

					<div id="cf-guide-woo">
						<ModuleCard
							mark="6"
							title={ __( 'WooCommerce shop events', 'consentflow' ) }
						>
							<ul className="cf-guide__bullets">
								<li>
									{ __(
										'If you sell with WooCommerce, ConsentFlow can send helpful shop events (view product, add to cart, purchase) into the data layer — only when the visitor allowed it.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'Add to cart / view product need analytics permission.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'Purchase needs both analytics and ads-related permission.',
										'consentflow'
									) }
								</li>
								<li>
									{ __(
										'No extra setup is required beyond enabling ConsentFlow and connecting GTM if you use it.',
										'consentflow'
									) }
								</li>
							</ul>
						</ModuleCard>
					</div>
				</section>

				<section id="cf-guide-faq" className="cf-guide__section">
					<h2>{ __( 'Common questions', 'consentflow' ) }</h2>
					<ModuleCard
						mark="?"
						title={ __( 'I do not see the banner on my site', 'consentflow' ) }
					>
						<p>
							{ __(
								'Try a private/incognito window (old choices may be saved). Confirm ConsentFlow is enabled. If Geo is on and you are outside the EU, the banner may stay hidden on purpose — turn Geo off temporarily to test.',
								'consentflow'
							) }
						</p>
					</ModuleCard>
					<ModuleCard
						mark="?"
						title={ __( 'Will this slow my website down?', 'consentflow' ) }
					>
						<p>
							{ __(
								'ConsentFlow is built to stay light. The visitor-facing script is very small, and scripts only load when they are needed.',
								'consentflow'
							) }
						</p>
					</ModuleCard>
					<ModuleCard
						mark="?"
						title={ __(
							'Do I need a lawyer or a huge cookie plugin?',
							'consentflow'
						) }
					>
						<p>
							{ __(
								'ConsentFlow helps with Google Consent Mode and a clear visitor choice. Privacy rules differ by country and business — this guide is not legal advice. If you are unsure, ask a professional for your region.',
								'consentflow'
							) }
						</p>
					</ModuleCard>

					<div className="cf-guide__footer-cta">
						<div>
							<strong>
								{ __(
									'You are ready when the checklist feels complete.',
									'consentflow'
								) }
							</strong>
							<p>
								{ __(
									'Save your settings, test once as a visitor, and you are good to go.',
									'consentflow'
								) }
							</p>
						</div>
						<Button
							variant="primary"
							onClick={ () => scrollTo( 'start' ) }
							__next40pxDefaultSize
						>
							{ __( 'Back to checklist', 'consentflow' ) }
						</Button>
					</div>
				</section>
			</div>
		</div>
	);
};

export default GuideTab;
