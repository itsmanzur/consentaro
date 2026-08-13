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
import { __ } from '@wordpress/i18n';
import InsightsChart from './InsightsChart';

const RANGE_OPTIONS = [ 7, 30, 90 ];

const SummaryCard = ( { label, value } ) => (
	<div className="consentaro-insights__summary-card">
		<span className="consentaro-insights__summary-label">{ label }</span>
		<span className="consentaro-insights__summary-value">{ value }</span>
	</div>
);

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

	const today = days.length ? days[ days.length - 1 ] : null;
	const todayTotal = today ? today.accepted + today.denied + today.customized : 0;

	const last7 = days.slice( -7 );
	const last7Totals = last7.reduce(
		( acc, d ) => ( {
			accepted: acc.accepted + d.accepted,
			denied: acc.denied + d.denied,
			customized: acc.customized + d.customized,
		} ),
		{ accepted: 0, denied: 0, customized: 0 }
	);
	const last7Total = last7Totals.accepted + last7Totals.denied + last7Totals.customized;
	const acceptRate =
		last7Total > 0 ? Math.round( ( last7Totals.accepted / last7Total ) * 100 ) : 0;

	return (
		<Card className="consentaro-admin__card">
			<CardHeader>
				<strong>{ __( 'Insights', 'consentaro' ) }</strong>
				<span className="consentaro-admin__card-sub">
					{ __(
						'Anonymous daily consent-decision counts',
						'consentaro'
					) }
				</span>
			</CardHeader>
			<CardBody>
				<p className="consentaro-insights__privacy-note">
					{ __(
						'Only anonymous daily counts are stored — no visitor data, IP addresses, or individual records.',
						'consentaro'
					) }
				</p>

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

				{ ! loading && ! loadError && grandTotal === 0 && (
					<p className="consentaro-insights__empty">
						{ __(
							'No decisions recorded yet — numbers will appear here once visitors start responding to the banner.',
							'consentaro'
						) }
					</p>
				) }

				{ ! loading && ! loadError && grandTotal > 0 && (
					<>
						<div className="consentaro-insights__summary">
							<SummaryCard
								label={ __( 'Today', 'consentaro' ) }
								value={ todayTotal }
							/>
							<SummaryCard
								label={ __( 'Accept rate (7 days)', 'consentaro' ) }
								value={ `${ acceptRate }%` }
							/>
							<SummaryCard
								label={ __( 'Total (selected range)', 'consentaro' ) }
								value={ grandTotal }
							/>
						</div>

						<InsightsChart days={ days } />
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
