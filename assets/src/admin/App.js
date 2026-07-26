import { TabPanel, Spinner } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import Header from './components/Header';
import Notice from './components/Notice';
import GeneralTab from './components/GeneralTab';
import DesignTab from './components/DesignTab';
import GuideTab from './components/GuideTab';

const App = () => {
	const [ settings, setSettings ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ tab, setTab ] = useState( 'guide' );

	useEffect( () => {
		apiFetch( { path: '/consentaro/v1/settings' } )
			.then( setSettings )
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

	const save = useCallback( async ( data ) => {
		setSaving( true );
		setNotice( null );
		try {
			const updated = await apiFetch( {
				path: '/consentaro/v1/settings',
				method: 'POST',
				data,
			} );
			setSettings( updated );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'consentaro' ),
			} );
		} catch ( e ) {
			setNotice( {
				status: 'error',
				message: __( 'Could not save settings.', 'consentaro' ),
			} );
		} finally {
			setSaving( false );
		}
	}, [] );

	if ( ! settings ) {
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
			<Header />
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
							<GuideTab onGoToTab={ ( name ) => setTab( name ) } />
						);
					}
					if ( t.name === 'general' ) {
						return (
							<GeneralTab
								settings={ settings }
								onSave={ save }
								saving={ saving }
							/>
						);
					}
					return (
						<DesignTab
							settings={ settings }
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
