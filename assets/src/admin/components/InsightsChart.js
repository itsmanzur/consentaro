import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const COLORS = {
	accepted: '#0E7C66',
	denied: '#d9776f',
	customized: '#F5B942',
};

const SEGMENT_KEYS = [ 'accepted', 'denied', 'customized' ];

const CHART_WIDTH = 760;
const CHART_HEIGHT = 200;
const MARGIN = { top: 10, right: 8, bottom: 24, left: 8 };

const formatDate = ( iso ) => {
	const d = new Date( iso + 'T00:00:00' );
	return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
};

const InsightsChart = ( { days } ) => {
	const [ hoverIndex, setHoverIndex ] = useState( null );

	const n = days.length;
	const plotWidth = CHART_WIDTH - MARGIN.left - MARGIN.right;
	const plotHeight = CHART_HEIGHT - MARGIN.top - MARGIN.bottom;
	const slot = n > 0 ? plotWidth / n : plotWidth;
	const barWidth = Math.max( 1, Math.min( 22, slot * 0.6 ) );
	const maxTotal = Math.max(
		1,
		...days.map( ( d ) => d.accepted + d.denied + d.customized )
	);
	const labelEvery = n <= 10 ? 1 : Math.ceil( n / 6 );
	const hovered = hoverIndex !== null ? days[ hoverIndex ] : null;

	return (
		<div className="consentaro-insights__chart-wrap">
			<svg
				className="consentaro-insights__chart"
				viewBox={ `0 0 ${ CHART_WIDTH } ${ CHART_HEIGHT }` }
				preserveAspectRatio="none"
				role="img"
				aria-label={ __( 'Daily consent decisions', 'consentaro' ) }
			>
				{ days.map( ( day, i ) => {
					const total = day.accepted + day.denied + day.customized;
					const x = MARGIN.left + i * slot + ( slot - barWidth ) / 2;
					let yCursor = MARGIN.top + plotHeight;
					const isHovered = hoverIndex === i;
					const showLabel = i % labelEvery === 0 || i === n - 1;

					return (
						<g
							key={ day.date }
							onMouseEnter={ () => setHoverIndex( i ) }
							onMouseLeave={ () =>
								setHoverIndex( ( cur ) => ( cur === i ? null : cur ) )
							}
						>
							<title>
								{ `${ formatDate( day.date ) }: ${ total } ` +
									__( 'decisions', 'consentaro' ) }
							</title>
							{ /* Full-height, invisible hover target so thin/zero bars are still easy to hover. */ }
							<rect
								x={ x }
								y={ MARGIN.top }
								width={ barWidth }
								height={ plotHeight }
								fill="transparent"
							/>
							{ total === 0 ? (
								<rect
									x={ x }
									y={ MARGIN.top + plotHeight - 2 }
									width={ barWidth }
									height={ 2 }
									fill="#e2e4e7"
								/>
							) : (
								SEGMENT_KEYS.map( ( key ) => {
									const value = day[ key ];
									const h = ( value / maxTotal ) * plotHeight;
									yCursor -= h;
									if ( h <= 0 ) {
										return null;
									}
									return (
										<rect
											key={ key }
											x={ x }
											y={ yCursor }
											width={ barWidth }
											height={ h }
											fill={ COLORS[ key ] }
											className={
												isHovered
													? 'consentaro-insights__bar is-hovered'
													: 'consentaro-insights__bar'
											}
										/>
									);
								} )
							) }
							{ showLabel && (
								<text
									x={ x + barWidth / 2 }
									y={ CHART_HEIGHT - 6 }
									textAnchor="middle"
									className="consentaro-insights__chart-label"
								>
									{ formatDate( day.date ) }
								</text>
							) }
						</g>
					);
				} ) }
			</svg>
			{ hovered && (
				<div
					className="consentaro-insights__tooltip"
					style={ { left: `${ ( ( hoverIndex + 0.5 ) / n ) * 100 }%` } }
				>
					<strong>{ formatDate( hovered.date ) }</strong>
					<span>
						{ sprintf(
							/* translators: 1: accepted count 2: denied count 3: customized count */
							__(
								'Accepted %1$d · Denied %2$d · Customized %3$d',
								'consentaro'
							),
							hovered.accepted,
							hovered.denied,
							hovered.customized
						) }
					</span>
				</div>
			) }
			<div className="consentaro-insights__legend">
				<span className="consentaro-insights__legend-item">
					<span
						className="consentaro-insights__legend-dot"
						style={ { background: COLORS.accepted } }
					/>
					{ __( 'Accepted', 'consentaro' ) }
				</span>
				<span className="consentaro-insights__legend-item">
					<span
						className="consentaro-insights__legend-dot"
						style={ { background: COLORS.denied } }
					/>
					{ __( 'Denied', 'consentaro' ) }
				</span>
				<span className="consentaro-insights__legend-item">
					<span
						className="consentaro-insights__legend-dot"
						style={ { background: COLORS.customized } }
					/>
					{ __( 'Customized', 'consentaro' ) }
				</span>
			</div>
		</div>
	);
};

export default InsightsChart;
