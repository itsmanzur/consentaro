import {
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	ToggleControl,
	SelectControl,
	Button,
	Spinner,
	Notice as WPNotice,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const CATEGORY_LABELS = {
	necessary: __( 'Necessary', 'consentaro' ),
	analytics: __( 'Analytics', 'consentaro' ),
	marketing: __( 'Marketing', 'consentaro' ),
	personalization: __( 'Personalization', 'consentaro' ),
};

const OVERRIDE_OPTIONS = [
	{ label: __( 'Auto (detected)', 'consentaro' ), value: '' },
	{ label: CATEGORY_LABELS.necessary, value: 'necessary' },
	{ label: CATEGORY_LABELS.analytics, value: 'analytics' },
	{ label: CATEGORY_LABELS.marketing, value: 'marketing' },
	{ label: CATEGORY_LABELS.personalization, value: 'personalization' },
];

function formatSeen( timestamp ) {
	if ( ! timestamp ) {
		return __( 'Just now', 'consentaro' );
	}
	try {
		return new Date( timestamp * 1000 ).toLocaleDateString( undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		} );
	} catch ( e ) {
		return '';
	}
}

function shortIdentifier( identifier, isSrc ) {
	if ( ! isSrc ) {
		return __( 'Inline script', 'consentaro' ) + ' · ' + identifier.replace( 'inline:', '' );
	}
	try {
		const url = new URL( identifier );
		return url.hostname + url.pathname;
	} catch ( e ) {
		return identifier;
	}
}

const ScriptsTab = ( { form, onChange, onSave, saving } ) => {
	const [ rows, setRows ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );

	const overrides = form.script_overrides || {};

	const loadDetected = useCallback( () => {
		setLoading( true );
		setLoadError( false );
		apiFetch( { path: '/consentaro/v1/detected-scripts' } )
			.then( ( data ) => {
				const list = Array.isArray( data ) ? data : [];
				list.sort( ( a, b ) => b.last_seen - a.last_seen );
				setRows( list );
			} )
			.catch( () => {
				setLoadError( true );
				setRows( [] );
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		loadDetected();
	}, [ loadDetected ] );

	const setOverride = ( identifier, category ) => {
		const next = { ...overrides };
		if ( category === '' ) {
			delete next[ identifier ];
		} else {
			next[ identifier ] = category;
		}
		onChange( 'script_overrides', next );
	};

	return (
		<Card className="consentaro-admin__card">
			<CardHeader>
				<strong>{ __( 'Script Blocking', 'consentaro' ) }</strong>
				<span className="consentaro-admin__card-sub">
					{ __(
						'Automatically hold known trackers back until a visitor consents',
						'consentaro'
					) }
				</span>
			</CardHeader>
			<CardBody>
				<VStack spacing={ 5 }>
					<ToggleControl
						label={ __( 'Block third-party scripts until consent', 'consentaro' ) }
						help={ __(
							'When on, scripts recognized as analytics, marketing, or personalization trackers are held inert until the visitor accepts that category. Unrecognized scripts are always left alone.',
							'consentaro'
						) }
						checked={ !! form.script_blocking }
						onChange={ ( value ) => onChange( 'script_blocking', value ) }
						__nextHasNoMarginBottom
					/>

					<div className="consentaro-admin__section">
						<HStack justify="space-between">
							<strong>{ __( 'Detected scripts', 'consentaro' ) }</strong>
							<Button
								variant="tertiary"
								onClick={ loadDetected }
								disabled={ loading }
								__next40pxDefaultSize
							>
								{ loading ? (
									<Spinner />
								) : (
									__( 'Refresh', 'consentaro' )
								) }
							</Button>
						</HStack>
						<p className="consentaro-admin__card-sub">
							{ __(
								'This list fills in as real visitors browse the site — it is not a one-time scan. Give it some traffic after activating, then check back.',
								'consentaro'
							) }
						</p>

						{ loadError && (
							<WPNotice status="error" isDismissible={ false }>
								{ __( 'Could not load detected scripts.', 'consentaro' ) }
							</WPNotice>
						) }

						{ ! loading && rows && rows.length === 0 && ! loadError && (
							<WPNotice status="info" isDismissible={ false }>
								{ __(
									'No third-party scripts detected yet.',
									'consentaro'
								) }
							</WPNotice>
						) }

						{ rows && rows.length > 0 && (
							<table className="consentaro-admin__scripts-table">
								<thead>
									<tr>
										<th>{ __( 'Script', 'consentaro' ) }</th>
										<th>{ __( 'Category', 'consentaro' ) }</th>
										<th>{ __( 'Last seen', 'consentaro' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ rows.map( ( row ) => (
										<tr key={ row.identifier }>
											<td>
												<code
													className="consentaro-admin__scripts-src"
													title={ row.identifier }
												>
													{ shortIdentifier( row.identifier, row.is_src ) }
												</code>
											</td>
											<td>
												<SelectControl
													value={ overrides[ row.identifier ] || '' }
													options={ OVERRIDE_OPTIONS }
													onChange={ ( value ) =>
														setOverride( row.identifier, value )
													}
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												{ ! overrides[ row.identifier ] && (
													<span className="consentaro-admin__scripts-auto">
														{ __( 'auto:', 'consentaro' ) }{ ' ' }
														{ CATEGORY_LABELS[ row.category ] || row.category }
													</span>
												) }
											</td>
											<td>{ formatSeen( row.last_seen ) }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						) }
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
				</HStack>
			</CardFooter>
		</Card>
	);
};

export default ScriptsTab;
