import {
	Card,
	CardBody,
	CardHeader,
	ToggleControl,
	CustomSelectControl,
	Button,
	Spinner,
	Notice as WPNotice,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

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

// CustomSelectControl renders its own styled listbox (not a native <select>),
// so the highlight color can be themed with CSS — a native <select>'s open-state
// highlight is drawn by the OS/browser and can't be overridden.
const CUSTOM_SELECT_OPTIONS = OVERRIDE_OPTIONS.map( ( option ) => ( {
	key: option.value === '' ? 'auto' : option.value,
	name: option.label,
	value: option.value,
} ) );

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

const EmptyIllustration = () => (
	<svg
		className="consentaro-admin__empty-art"
		viewBox="0 0 120 90"
		role="img"
		aria-label={ __( 'No scripts detected yet', 'consentaro' ) }
	>
		<rect x="14" y="14" width="92" height="62" rx="8" fill="#e7f4f0" />
		<rect x="26" y="28" width="44" height="6" rx="3" fill="#0E7C66" opacity=".35" />
		<rect x="26" y="42" width="68" height="6" rx="3" fill="#0E7C66" opacity=".2" />
		<rect x="26" y="56" width="30" height="6" rx="3" fill="#0E7C66" opacity=".2" />
		<circle cx="92" cy="62" r="17" fill="#fff" stroke="#0E7C66" strokeWidth="3" />
		<path d="m104 74 10 10" stroke="#0E7C66" strokeWidth="4" strokeLinecap="round" />
		<circle cx="29" cy="12" r="5" fill="#F5B942" />
	</svg>
);

const formatTime = ( date ) => {
	try {
		return date.toLocaleTimeString( undefined, { hour: 'numeric', minute: '2-digit' } );
	} catch ( e ) {
		return '';
	}
};

const ScriptsTab = ( { form, onChange } ) => {
	const [ rows, setRows ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );
	const [ updatedAt, setUpdatedAt ] = useState( null );

	const overrides = form.script_overrides || {};

	const loadDetected = useCallback( () => {
		setLoading( true );
		setLoadError( false );
		apiFetch( { path: '/consentaro/v1/detected-scripts' } )
			.then( ( data ) => {
				const list = Array.isArray( data ) ? data : [];
				list.sort( ( a, b ) => b.last_seen - a.last_seen );
				setRows( list );
				setUpdatedAt( new Date() );
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
							<HStack justify="flex-end" spacing={ 3 }>
								<span className="consentaro-admin__updated" aria-live="polite">
									{ loading
										? __( 'Refreshing…', 'consentaro' )
										: updatedAt
										? sprintf(
												/* translators: %s: time of day */
												__( 'Last updated %s', 'consentaro' ),
												formatTime( updatedAt )
										  )
										: '' }
								</span>
								<Button
									variant="tertiary"
									onClick={ loadDetected }
									disabled={ loading }
									__next40pxDefaultSize
								>
									{ loading ? <Spinner /> : __( 'Refresh', 'consentaro' ) }
								</Button>
							</HStack>
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
							<div className="consentaro-admin__empty">
								<EmptyIllustration />
								<strong>
									{ __( 'No third-party scripts detected yet', 'consentaro' ) }
								</strong>
								<p>
									{ __(
										'Scripts appear here as real visitors browse your site. Visit a few pages in a private window, then press Refresh.',
										'consentaro'
									) }
								</p>
							</div>
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
												<CustomSelectControl
													label={ __( 'Category', 'consentaro' ) }
													hideLabelFromVision
													className="consentaro-admin__scripts-select"
													options={ CUSTOM_SELECT_OPTIONS }
													value={ CUSTOM_SELECT_OPTIONS.find(
														( option ) =>
															option.value ===
															( overrides[ row.identifier ] || '' )
													) }
													onChange={ ( { selectedItem } ) =>
														setOverride(
															row.identifier,
															selectedItem.value
														)
													}
												/>
												<span
													className={ `consentaro-chip consentaro-chip--${
														overrides[ row.identifier ] || row.category
													}` }
												>
													{ CATEGORY_LABELS[
														overrides[ row.identifier ] || row.category
													] || row.category }
													<span className="consentaro-chip__source">
														{ overrides[ row.identifier ]
															? __( 'manual', 'consentaro' )
															: __( 'auto', 'consentaro' ) }
													</span>
												</span>
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
		</Card>
	);
};

export default ScriptsTab;
