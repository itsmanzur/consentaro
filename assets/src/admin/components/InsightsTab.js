import {
	Card,
	CardBody,
	CardHeader,
	Spinner,
	Notice as WPNotice,
	__experimentalHStack as HStack,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import InsightsChart from './InsightsChart';

const RANGE_OPTIONS = [ 7, 30, 90 ];
const SPLIT_COLORS = {
	accepted: '#0E7C66',
	denied: '#d9776f',
	customized: '#F5B942',
};

const admin = window.consentaroAdmin || {};

// Integer percentages that always add up to 100 (largest-remainder method),
// so the split row can't show 99% or 101% from rounding.
const percentSplit = ( counts ) => {
	const total = counts.reduce( ( a, b ) => a + b, 0 );
	if ( total === 0 ) {
		return counts.map( () => 0 );
	}
	const raw = counts.map( ( c ) => ( c / total ) * 100 );
	const floors = raw.map( Math.floor );
	let remainder = 100 - floors.reduce( ( a, b ) => a + b, 0 );
	const order = raw
		.map( ( value, index ) => ( { index, frac: value - Math.floor( value ) } ) )
		.sort( ( a, b ) => b.frac - a.frac );
	for ( let i = 0; remainder > 0 && i < order.length; i++, remainder-- ) {
		floors[ order[ i ].index ] += 1;
	}
	return floors;
};

const SummaryCard = ( { label, value, empty } ) => (
	<div className="consentaro-insights__summary-card">
		<span className="consentaro-insights__summary-label">{ label }</span>
		{ empty ? (
			<>
				<span className="consentaro-insights__summary-value is-empty">—</span>
				<span className="consentaro-insights__summary-note">
					{ __( 'No data yet', 'consentaro' ) }
				</span>
			</>
		) : (
			<span className="consentaro-insights__summary-value">{ value }</span>
		) }
	</div>
);

const SplitRow = ( { totals, grandTotal } ) => {
	const keys = [ 'accepted', 'denied', 'customized' ];
	const labels = {
		accepted: __( 'Accepted', 'consentaro' ),
		denied: __( 'Denied', 'consentaro' ),
		customized: __( 'Customized', 'consentaro' ),
	};
	const percents = percentSplit( keys.map( ( k ) => totals[ k ] ) );

	return (
		<div className="consentaro-insights__split">
			<div
				className="consentaro-insights__split-bar"
				role="img"
				aria-label={ keys
					.map( ( k, i ) => `${ labels[ k ] } ${ percents[ i ] }%` )
					.join( ', ' ) }
			>
				{ keys.map( ( k, i ) =>
					totals[ k ] > 0 ? (
						<span
							key={ k }
							style={ {
								width: `${ ( totals[ k ] / grandTotal ) * 100 }%`,
								background: SPLIT_COLORS[ k ],
							} }
						/>
					) : null
				) }
			</div>
			<ul className="consentaro-insights__split-list">
				{ keys.map( ( k, i ) => (
					<li key={ k }>
						<span
							className="consentaro-insights__legend-dot"
							style={ { background: SPLIT_COLORS[ k ] } }
							aria-hidden="true"
						/>
						<strong>{ percents[ i ] }%</strong> { labels[ k ] }
						<span className="consentaro-insights__split-count">
							({ totals[ k ] })
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
};

const InsightsTab = () => {
	const [ range, setRange ] = useState( 30 );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );

	const load = useCallback( ( selectedRange ) => {
		setLoading( true );
		setLoadError( false );
		apiFetch( { path: `/consentaro/v1/insights?range=${ selectedRange }` } )
			.then( setData )
			.catch( () => setLoadError( true ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		load( range );
	}, [ range, load ] );

	const days = data?.days || [];
	const totals = data?.totals || { accepted: 0, denied: 0, customized: 0 };
	const grandTotal = totals.accepted + totals.denied + totals.customized;
	const hasData = grandTotal > 0;

	const today = days.length ? days[ days.length - 1 ] : null;
	const todayTotal = today ? today.accepted + today.denied + today.customized : 0;

	const acceptRate = hasData ? Math.round( ( totals.accepted / grandTotal ) * 100 ) : null;

	const log = data?.log;
	const logEnabled = !! log?.enabled;
	const logTotals = log?.totals;
	const logTotal = logTotals
		? logTotals.accepted + logTotals.denied + logTotals.customized
		: 0;
	const rangeStart = days.length ? days[ 0 ].date : null;
	const logCoversPart = !! ( log?.since && rangeStart && log.since > rangeStart );
	const logDiffers = logEnabled && logTotal !== grandTotal;

	const logLink =
		logEnabled && admin.consentLogUrl ? (
			<a className="consentaro-insights__log-link" href={ admin.consentLogUrl }>
				{ __( 'View Consent Log →', 'consentaro' ) }
			</a>
		) : null;

	return (
		<Card className="consentaro-admin__card">
			<CardHeader>
				<strong>{ __( 'Insights', 'consentaro' ) }</strong>
				<span className="consentaro-admin__card-sub">
					{ __( 'Anonymous daily consent-decision counts', 'consentaro' ) }
				</span>
			</CardHeader>
			<CardBody>
				{ data && (
					<p className="consentaro-insights__privacy-note">
						{ logEnabled
							? __(
									'Daily counts shown here. Individual decisions are stored in the Consent Log (anonymous token, no IP/user agent).',
									'consentaro'
							  )
							: __(
									'Only anonymous daily counts are stored — no visitor data, IP addresses, or individual records.',
									'consentaro'
							  ) }{ ' ' }
						{ logLink }
					</p>
				) }

				<div className="consentaro-admin__field consentaro-admin__field--narrow">
					<ToggleGroupControl
						label={ __( 'Range', 'consentaro' ) }
						value={ range }
						onChange={ ( value ) => setRange( Number( value ) ) }
						isBlock
						__nextHasNoMarginBottom
					>
						{ RANGE_OPTIONS.map( ( option ) => (
							<ToggleGroupControlOption
								key={ option }
								value={ option }
								label={ sprintfDays( option ) }
							/>
						) ) }
					</ToggleGroupControl>
				</div>

				{ loading && (
					<HStack justify="flex-start">
						<Spinner />
						<span>{ __( 'Loading…', 'consentaro' ) }</span>
					</HStack>
				) }

				{ ! loading && loadError && (
					<WPNotice status="error" isDismissible={ false }>
						{ __( 'Could not load insights.', 'consentaro' ) }
					</WPNotice>
				) }

				{ ! loading && ! loadError && (
					<>
						<div className="consentaro-insights__summary">
							<SummaryCard
								label={ __( 'Today', 'consentaro' ) }
								value={ todayTotal }
								empty={ ! hasData }
							/>
							<SummaryCard
								label={ sprintf(
									/* translators: %d: number of days */
									__( 'Accept rate (%d days)', 'consentaro' ),
									range
								) }
								value={ `${ acceptRate }%` }
								empty={ ! hasData }
							/>
							<SummaryCard
								label={ __( 'Total (selected range)', 'consentaro' ) }
								value={ grandTotal }
								empty={ ! hasData }
							/>
						</div>

						{ ! hasData && (
							<p className="consentaro-insights__empty">
								{ __(
									'No decisions recorded in this range yet — numbers will appear here once visitors start responding to the banner.',
									'consentaro'
								) }
							</p>
						) }

						{ hasData && (
							<>
								<SplitRow totals={ totals } grandTotal={ grandTotal } />
								<InsightsChart days={ days } />
							</>
						) }

						{ logEnabled && logTotals && (
							<div className="consentaro-insights__logcheck">
								<span>
									{ sprintf(
										/* translators: %d: number of decisions in the Consent Log */
										__(
											'Consent Log, same range: %d decisions.',
											'consentaro'
										),
										logTotal
									) }
								</span>
								{ logDiffers && (
									<span className="consentaro-insights__logcheck-note">
										{ logCoversPart
											? sprintf(
													/* translators: %s: date, e.g. 2026-08-23 */
													__(
														'The log was switched on on %s, so it covers only part of this range.',
														'consentaro'
													),
													log.since
											  )
											: __(
													'Totals can differ if older log records were already pruned by your retention setting.',
													'consentaro'
											  ) }
									</span>
								) }
							</div>
						) }
					</>
				) }
			</CardBody>
		</Card>
	);
};

const sprintfDays = ( n ) =>
	/* translators: %d: number of days */
	`${ n } ${ __( 'days', 'consentaro' ) }`;

export default InsightsTab;
