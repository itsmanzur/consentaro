import { TabPanel, Spinner } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
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
};

const App = () => {
	// Last-saved settings (used for the status badge / Guide checklist sync).
	const [ settings, setSettings ] = useState( null );
	// Working draft the General/Design tabs edit.
	const [ form, setForm ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ tab, setTab ] = useState( 'guide' );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/settings' } )
			.then( ( data ) => {
				setSettings( data );
				setForm( data );
			} )
			.catch( () => {
				setNotice( {
					status: 'error',
					message: __( 'Failed to load settings.', 'consentaro' ),
				} );
			} );
	}, [] );

	const isDirty =
		!! settings && !! form && JSON.stringify( settings ) !== JSON.stringify( form );

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
		setForm( ( prev ) => ( { ...prev, banner: { ...DEFAULT_BANNER } } ) );
	}, [] );

	const save = useCallback( async () => {
		setSaving( true );
		setNotice( null );
		try {
			const response = await apiFetch( {
				path: '/consentaro/v1/settings',
				method: 'POST',
				data: form,
			} );
			const { rejected, ...updated } = response;
			setSettings( updated );
			setForm( updated );
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

	if ( ! form ) {
		return (
			<div className="consentaro-admin consentaro-admin--loading">
				<Spinner />
				<p>{ __( 'Loading settings…', 'consentaro' ) }</p>
			</div>
		);
	}

	return (
		<div
			className={ `consentaro-admin ${
				tab === 'guide' ? 'consentaro-admin--guide' : ''
			} ${ tab === 'design' ? 'consentaro-admin--wide' : '' }` }
		>
			<Header enabled={ !! settings?.enabled } />
			{ notice && (
				<div className="consentaro-admin__notice">
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
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
								onGoToTab={ ( name ) => setTab( name ) }
							/>
						);
					}
					if ( t.name === 'general' ) {
						return (
							<GeneralTab
								form={ form }
								onChange={ updateField }
								onSave={ save }
								saving={ saving }
							/>
						);
					}
					if ( t.name === 'scripts' ) {
						return (
							<ScriptsTab
								form={ form }
								onChange={ updateField }
								onSave={ save }
								saving={ saving }
							/>
						);
					}
					if ( t.name === 'design' ) {
						return (
							<DesignTab
								form={ form }
								onChange={ updateBanner }
								onReset={ resetBanner }
								onSave={ save }
								saving={ saving }
							/>
						);
					}
					return <InsightsTab />;
				} }
			</TabPanel>
		</div>
	);
};

export default App;
