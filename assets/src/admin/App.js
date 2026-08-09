import { TabPanel, Spinner } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import Header from './components/Header';
import Notice from './components/Notice';
import GeneralTab from './components/GeneralTab';
import DesignTab from './components/DesignTab';
import GuideTab from './components/GuideTab';

const DEFAULT_BANNER = {
	position: 'bottom',
	text: '',
	bg: '#ffffff',
	text_color: '#1a1a1a',
	btn_primary_bg: '#0073aa',
	btn_primary_text: '#ffffff',
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

	useEffect( () => {
		if ( ! notice || notice.status !== 'success' ) {
			return;
		}
		const timer = setTimeout( () => setNotice( null ), 3500 );
		return () => clearTimeout( timer );
	}, [ notice ] );

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
			}` }
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
					{ name: 'design', title: __( 'Design', 'consentaro' ) },
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
					return (
						<DesignTab
							form={ form }
							onChange={ updateBanner }
							onReset={ resetBanner }
							onSave={ save }
							saving={ saving }
						/>
					);
				} }
			</TabPanel>
		</div>
	);
};

export default App;
