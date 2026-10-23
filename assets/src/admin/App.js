import { TabPanel, Spinner, Button } from '@wordpress/components';
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import Header from './components/Header';
import Notice from './components/Notice';
import GeneralTab from './components/GeneralTab';
import ScriptsTab from './components/ScriptsTab';
import DesignTab from './components/DesignTab';
import GuideTab from './components/GuideTab';
import InsightsTab from './components/InsightsTab';

const DEFAULT_BANNER = {
	position: 'bottom',
	text: '',
	bg: '#ffffff',
	text_color: '#1a1a1a',
	btn_primary_bg: '#0E7C66',
	btn_primary_text: '#ffffff',
	btn_secondary_bg: '#f0f0f1',
	btn_secondary_text: '#1a1a1a',
	border_radius: 8,
	btn_border_radius: 4,
	btn_layout: 'inline',
	btn_align: 'left',
	font_size: 13,
	privacy_page_id: 0,
	privacy_url: '',
	label_accept: '',
	label_deny: '',
	label_customize: '',
};

// Server-derived / transient keys that are never part of the editable form.
const stripTransient = ( data ) => {
	const { rejected, setup, ...rest } = data; // eslint-disable-line no-unused-vars
	return rest;
};

const admin = window.consentaroAdmin || {};

const App = () => {
	// Last-saved settings (used for the status badge / dirty comparison).
	const [ settings, setSettings ] = useState( null );
	// Working draft the General/Design/Scripts tabs edit.
	const [ form, setForm ] = useState( null );
	// Server-computed setup checklist state.
	const [ setup, setSetup ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ tab, setTab ] = useState( 'guide' );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/settings' } )
			.then( ( data ) => {
				const clean = stripTransient( data );
				setSettings( clean );
				setForm( clean );
				setSetup( data.setup || null );
				// Land on Guide until setup has been completed once.
				if ( clean.setup_complete ) {
					setTab( 'general' );
				}
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: __( 'Failed to load settings.', 'consentaro' ),
				} );
			} );
	}, [] );

	const isDirty = useMemo(
		() => !! settings && !! form && JSON.stringify( settings ) !== JSON.stringify( form ),
		[ settings, form ]
	);

	useEffect( () => {
		if ( ! isDirty ) {
			return;
		}
		const handler = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
			return '';
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ isDirty ] );

	const updateField = useCallback( ( key, value ) => {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}, [] );

	const updateBanner = useCallback( ( key, value ) => {
		setForm( ( prev ) => ( {
			...prev,
			banner: { ...prev.banner, [ key ]: value },
		} ) );
	}, [] );

	const resetBanner = useCallback( () => {
		const d = admin.defaults || {};
		setForm( ( prev ) => ( {
			...prev,
			banner: {
				...DEFAULT_BANNER,
				text: d.text || prev.banner.text,
				label_accept: d.label_accept || '',
				label_deny: d.label_deny || '',
				label_customize: d.label_customize || '',
				// Reset is about look & feel — keep the privacy link.
				privacy_page_id: prev.banner.privacy_page_id,
				privacy_url: prev.banner.privacy_url,
			},
		} ) );
	}, [] );

	const discard = useCallback( () => {
		setForm( settings );
		setNotice( null );
	}, [ settings ] );

	const save = useCallback( async () => {
		setSaving( true );
		setNotice( null );
		try {
			const response = await apiFetch( {
				path: '/consentaro/v1/settings',
				method: 'POST',
				data: form,
			} );
			const { rejected } = response;
			const updated = stripTransient( response );
			setSettings( updated );
			setForm( updated );
			setSetup( response.setup || null );
			if ( rejected && rejected.length ) {
				setNotice( {
					status: 'warning',
					message:
						__(
							'Settings saved, but this looked invalid and was not changed: ',
							'consentaro'
						) + rejected.join( ', ' ),
				} );
			} else {
				setNotice( {
					status: 'success',
					message: __( 'Settings saved.', 'consentaro' ),
				} );
			}
		} catch ( e ) {
			setNotice( {
				status: 'error',
				message: __( 'Could not save settings.', 'consentaro' ),
			} );
		} finally {
			setSaving( false );
		}
	}, [ form ] );

	// Persists one or two checklist flags immediately WITHOUT touching the
	// unsaved edits in the form: only the returned flag keys are merged into
	// both snapshots.
	const saveFlags = useCallback( async ( flags ) => {
		try {
			const response = await apiFetch( {
				path: '/consentaro/v1/settings',
				method: 'POST',
				data: flags,
			} );
			const merged = {};
			[ ...Object.keys( flags ), 'setup_complete' ].forEach( ( key ) => {
				merged[ key ] = response[ key ];
			} );
			setSettings( ( prev ) => ( { ...prev, ...merged } ) );
			setForm( ( prev ) => ( { ...prev, ...merged } ) );
			setSetup( response.setup || null );
		} catch ( e ) {
			setNotice( {
				status: 'error',
				message: __( 'Could not update setup progress.', 'consentaro' ),
			} );
		}
	}, [] );

	const handleImported = useCallback( ( updated ) => {
		const clean = stripTransient( updated );
		setSettings( clean );
		setForm( clean );
		apiFetch( { path: '/consentaro/v1/settings' } )
			.then( ( data ) => setSetup( data.setup || null ) )
			.catch( () => {} );
	}, [] );

	if ( ! form ) {
		return (
			<div className="consentaro-admin consentaro-admin--loading">
				<Spinner />
				<p>{ __( 'Loading settings…', 'consentaro' ) }</p>
			</div>
		);
	}

	const showSaveBar = isDirty || [ 'general', 'scripts', 'design' ].includes( tab );

	return (
		<div
			className={ `consentaro-admin ${
				tab === 'guide' ? 'consentaro-admin--guide' : ''
			} ${ tab === 'design' ? 'consentaro-admin--wide' : '' } ${
				showSaveBar ? 'has-save-bar' : ''
			}` }
		>
			<Header
				enabled={ !! settings?.enabled }
				setup={ setup }
				onHelp={ () => setTab( 'guide' ) }
			/>
			{ notice && (
				<div className="consentaro-admin__notice">
					<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
				</div>
			) }
			<TabPanel
				className="consentaro-admin__tabs"
				activeClass="is-active"
				initialTabName={ tab }
				key={ tab }
				onSelect={ setTab }
				tabs={ [
					{ name: 'guide', title: __( 'Guide', 'consentaro' ) },
					{ name: 'general', title: __( 'General', 'consentaro' ) },
					{ name: 'scripts', title: __( 'Scripts', 'consentaro' ) },
					{ name: 'design', title: __( 'Design', 'consentaro' ) },
					{ name: 'insights', title: __( 'Insights', 'consentaro' ) },
				] }
			>
				{ ( t ) => {
					if ( t.name === 'guide' ) {
						return (
							<GuideTab
								settings={ settings }
								setup={ setup }
								homeUrl={ admin.homeUrl }
								onGoToTab={ ( name ) => setTab( name ) }
								onImported={ handleImported }
								onSkipGtm={ () => saveFlags( { gtm_skipped: true } ) }
								onConfirmText={ () => saveFlags( { banner_confirmed: true } ) }
								onMarkTested={ () => saveFlags( { setup_tested: true } ) }
							/>
						);
					}
					if ( t.name === 'general' ) {
						return (
							<GeneralTab
								form={ form }
								setup={ setup }
								onChange={ updateField }
								onGoToTab={ ( name ) => setTab( name ) }
								onSkipGtm={ () => saveFlags( { gtm_skipped: true } ) }
								onConfirmText={ () => saveFlags( { banner_confirmed: true } ) }
								onMarkTested={ () => saveFlags( { setup_tested: true } ) }
							/>
						);
					}
					if ( t.name === 'scripts' ) {
						return <ScriptsTab form={ form } onChange={ updateField } />;
					}
					if ( t.name === 'design' ) {
						return (
							<DesignTab
								form={ form }
								onChange={ updateBanner }
								onReset={ resetBanner }
								saving={ saving }
							/>
						);
					}
					return <InsightsTab />;
				} }
			</TabPanel>

			{ showSaveBar && (
				<div
					className={ `consentaro-savebar ${ isDirty ? 'is-dirty' : '' }` }
					role="region"
					aria-label={ __( 'Save changes', 'consentaro' ) }
				>
					<span className="consentaro-savebar__status" aria-live="polite">
						{ isDirty
							? __( 'You have unsaved changes', 'consentaro' )
							: __( 'All changes saved', 'consentaro' ) }
					</span>
					<span className="consentaro-savebar__actions">
						{ isDirty && (
							<Button
								variant="tertiary"
								onClick={ discard }
								disabled={ saving }
								__next40pxDefaultSize
							>
								{ __( 'Discard', 'consentaro' ) }
							</Button>
						) }
						<Button
							variant="primary"
							onClick={ save }
							disabled={ saving || ! isDirty }
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
					</span>
				</div>
			) }
		</div>
	);
};

export default App;
