import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { scoreRating, RATING_LABELS, formatDate } from '../utils';

/* -------------------------------------------------------------------------
 * Score ring (Lighthouse style gauge)
 * ---------------------------------------------------------------------- */

export function ScoreRing( { score, size = 96, stroke = 8, label, sublabel } ) {
	const rating = scoreRating( score );
	const radius = ( size - stroke ) / 2;
	const circumference = 2 * Math.PI * radius;
	const value =
		score === null || score === undefined
			? 0
			: Math.max( 0, Math.min( 100, score ) );
	const offset = circumference * ( 1 - value / 100 );

	return (
		<div
			className={ `gpi-ring gpi-ring--${ rating }` }
			style={ { '--gpi-ring-size': `${ size }px` } }
		>
			<svg
				width={ size }
				height={ size }
				viewBox={ `0 0 ${ size } ${ size }` }
				role="img"
				aria-label={
					label
						? `${ label }: ${ score ?? '–' }`
						: String( score ?? '–' )
				}
			>
				<circle
					className="gpi-ring__track"
					cx={ size / 2 }
					cy={ size / 2 }
					r={ radius }
					strokeWidth={ stroke }
					fill="none"
				/>
				<circle
					className="gpi-ring__value"
					cx={ size / 2 }
					cy={ size / 2 }
					r={ radius }
					strokeWidth={ stroke }
					fill="none"
					strokeDasharray={ circumference }
					strokeDashoffset={ offset }
					strokeLinecap="round"
					transform={ `rotate(-90 ${ size / 2 } ${ size / 2 })` }
				/>
				<text
					className="gpi-ring__score"
					x="50%"
					y="50%"
					dominantBaseline="central"
					textAnchor="middle"
					style={ { fontSize: size * 0.3 } }
				>
					{ score === null || score === undefined ? '–' : score }
				</text>
			</svg>
			{ label && <div className="gpi-ring__label">{ label }</div> }
			{ sublabel && (
				<div className="gpi-ring__sublabel">{ sublabel }</div>
			) }
		</div>
	);
}

/* -------------------------------------------------------------------------
 * Small helpers
 * ---------------------------------------------------------------------- */

export function ScoreBadge( { score, size = 'md' } ) {
	const rating = scoreRating( score );
	return (
		<span
			className={ `gpi-badge gpi-badge--${ rating } gpi-badge--${ size }` }
			title={ RATING_LABELS[ rating ] }
		>
			{ score === null || score === undefined ? '–' : score }
		</span>
	);
}

export function Sparkline( { values = [], width = 88, height = 28 } ) {
	if ( values.length < 2 ) {
		return (
			<span
				className="gpi-sparkline gpi-sparkline--empty"
				aria-hidden="true"
			/>
		);
	}
	const step = width / ( values.length - 1 );
	const y = ( value ) => height - 3 - ( value / 100 ) * ( height - 6 );
	const points = values.map(
		( value, index ) =>
			`${ ( index * step ).toFixed( 1 ) },${ y( value ).toFixed( 1 ) }`
	);
	const last = values[ values.length - 1 ];
	const change = last - values[ 0 ];
	return (
		<span
			className={ `gpi-sparkline gpi-sparkline--${ scoreRating( last ) }` }
			title={ sprintf(
				/* translators: %s: score change */ __(
					'Change over the last checks: %s',
					'google-pagespeed-insights'
				),
				( change > 0 ? '+' : '' ) + change
			) }
		>
			<svg
				width={ width }
				height={ height }
				viewBox={ `0 0 ${ width } ${ height }` }
				aria-hidden="true"
			>
				<polyline
					points={ points.join( ' ' ) }
					fill="none"
					strokeWidth="2"
					strokeLinejoin="round"
					strokeLinecap="round"
				/>
				<circle
					cx={ ( values.length - 1 ) * step }
					cy={ y( last ) }
					r="2.5"
				/>
			</svg>
		</span>
	);
}

/* -------------------------------------------------------------------------
 * Line chart with hover tooltip
 * ---------------------------------------------------------------------- */

/**
 * Width of an element, kept in sync with resizes, so charts are drawn at real size (no stretched text).
 */
function useElementWidth( ref, fallback = 720 ) {
	const [ width, setWidth ] = useState( fallback );
	useEffect( () => {
		const element = ref.current;
		if ( ! element ) {
			return undefined;
		}
		setWidth( element.clientWidth || fallback );
		if ( ! window.ResizeObserver ) {
			return undefined;
		}
		const observer = new window.ResizeObserver( ( entries ) => {
			const next = Math.round( entries[ 0 ].contentRect.width );
			if ( next > 0 ) {
				setWidth( next );
			}
		} );
		observer.observe( element );
		return () => observer.disconnect();
	}, [ ref, fallback ] );
	return width;
}

export function LineChart( {
	series,
	data,
	height = 240,
	xKey = 'date',
	yMax = 100,
	formatX,
	formatY = ( value ) => value,
	emptyText,
} ) {
	const wrapper = useRef();
	const [ hover, setHover ] = useState( null );
	const width = useElementWidth( wrapper );
	const pad = { top: 16, right: 16, bottom: 28, left: 36 };
	const innerWidth = width - pad.left - pad.right;
	const innerHeight = height - pad.top - pad.bottom;
	const visible = series.filter( ( item ) =>
		data.some(
			( point ) =>
				point[ item.key ] !== undefined && point[ item.key ] !== null
		)
	);

	if ( ! data.length || ! visible.length ) {
		return (
			<div
				className="gpi-chart-empty"
				ref={ wrapper }
				style={ { height } }
			>
				{ emptyText ||
					__(
						'No history yet. Trends appear after a few scans.',
						'google-pagespeed-insights'
					) }
			</div>
		);
	}

	const x = ( index ) =>
		pad.left +
		( data.length === 1
			? innerWidth / 2
			: ( index / ( data.length - 1 ) ) * innerWidth );
	const y = ( value ) =>
		pad.top + innerHeight - ( value / yMax ) * innerHeight;
	const ticks = [ 0, 25, 50, 75, 100 ].map(
		( tick ) => ( tick / 100 ) * yMax
	);
	const maxLabels = Math.max( 2, Math.floor( innerWidth / 90 ) );
	const labelEvery = Math.max( 1, Math.ceil( data.length / maxLabels ) );
	const fmtX = formatX || ( ( value ) => value );

	const onMove = ( event ) => {
		const rect = wrapper.current.getBoundingClientRect();
		const relative =
			( ( event.clientX - rect.left ) / rect.width ) * width - pad.left;
		const index =
			data.length === 1
				? 0
				: Math.round( ( relative / innerWidth ) * ( data.length - 1 ) );
		setHover( Math.max( 0, Math.min( data.length - 1, index ) ) );
	};

	return (
		<div
			className="gpi-linechart"
			ref={ wrapper }
			onMouseMove={ onMove }
			onMouseLeave={ () => setHover( null ) }
		>
			<svg
				viewBox={ `0 0 ${ width } ${ height }` }
				preserveAspectRatio="none"
				style={ { height } }
				role="img"
				aria-label={ __(
					'Score trend chart',
					'google-pagespeed-insights'
				) }
			>
				<rect
					className="gpi-linechart__band gpi-linechart__band--good"
					x={ pad.left }
					y={ y( yMax ) }
					width={ innerWidth }
					height={ y( 0.9 * yMax ) - y( yMax ) }
				/>
				{ ticks.map( ( tick ) => (
					<g key={ tick }>
						<line
							className="gpi-linechart__grid"
							x1={ pad.left }
							x2={ width - pad.right }
							y1={ y( tick ) }
							y2={ y( tick ) }
						/>
						<text
							className="gpi-linechart__axis"
							x={ pad.left - 8 }
							y={ y( tick ) }
							textAnchor="end"
							dominantBaseline="central"
						>
							{ formatY( tick ) }
						</text>
					</g>
				) ) }
				{ data.map( ( point, index ) =>
					index % labelEvery === 0 || index === data.length - 1 ? (
						<text
							key={ index }
							className="gpi-linechart__axis"
							x={ x( index ) }
							y={ height - 8 }
							textAnchor="middle"
						>
							{ fmtX( point[ xKey ] ) }
						</text>
					) : null
				) }
				{ visible.map( ( item ) => {
					const points = data
						.map( ( point, index ) =>
							point[ item.key ] === undefined ||
							point[ item.key ] === null
								? null
								: [ x( index ), y( point[ item.key ] ) ]
						)
						.filter( Boolean );
					const line = points
						.map(
							( [ px, py ] ) =>
								`${ px.toFixed( 1 ) },${ py.toFixed( 1 ) }`
						)
						.join( ' ' );
					const area =
						item.area && points.length > 1
							? `${ points[ 0 ][ 0 ] },${ y( 0 ) } ${ line } ${ points[ points.length - 1 ][ 0 ] },${ y( 0 ) }`
							: null;
					return (
						<g
							key={ item.key }
							className="gpi-linechart__series"
							style={ { '--gpi-series': item.color } }
						>
							{ area && (
								<polygon
									className="gpi-linechart__area"
									points={ area }
								/>
							) }
							{ points.length > 1 && (
								<polyline
									className="gpi-linechart__line"
									points={ line }
									fill="none"
								/>
							) }
							{ points.map(
								( [ px, py ], index ) =>
									( points.length < 25 ||
										hover !== null ) && (
										<circle
											key={ index }
											className="gpi-linechart__dot"
											cx={ px }
											cy={ py }
											r={ 3 }
										/>
									)
							) }
						</g>
					);
				} ) }
				{ hover !== null && (
					<line
						className="gpi-linechart__cursor"
						x1={ x( hover ) }
						x2={ x( hover ) }
						y1={ pad.top }
						y2={ pad.top + innerHeight }
					/>
				) }
			</svg>
			{ hover !== null && (
				<div
					className="gpi-tooltip"
					style={ { left: `${ ( x( hover ) / width ) * 100 }%` } }
				>
					<div className="gpi-tooltip__title">
						{ fmtX( data[ hover ][ xKey ] ) }
					</div>
					{ visible.map( ( item ) =>
						data[ hover ][ item.key ] === undefined ||
						data[ hover ][ item.key ] === null ? null : (
							<div key={ item.key } className="gpi-tooltip__row">
								<span
									className="gpi-dot"
									style={ { background: item.color } }
								/>
								<span>{ item.label }</span>
								<strong>
									{ formatY( data[ hover ][ item.key ] ) }
								</strong>
							</div>
						)
					) }
				</div>
			) }
		</div>
	);
}

export function historyDateLabel( value ) {
	if ( typeof value === 'number' ) {
		return formatDate( value, false );
	}
	const date = new Date( value + 'T00:00:00Z' );
	return new Intl.DateTimeFormat( undefined, {
		month: 'short',
		day: 'numeric',
		timeZone: 'UTC',
	} ).format( date );
}

/* -------------------------------------------------------------------------
 * Donut chart
 * ---------------------------------------------------------------------- */

export function DonutChart( {
	segments,
	size = 168,
	stroke = 22,
	centerValue,
	centerLabel,
} ) {
	const [ active, setActive ] = useState( null );
	const total = segments.reduce( ( sum, segment ) => sum + segment.value, 0 );
	const radius = ( size - stroke ) / 2;
	const circumference = 2 * Math.PI * radius;
	let offset = 0;

	return (
		<div className="gpi-donut">
			<svg
				width={ size }
				height={ size }
				viewBox={ `0 0 ${ size } ${ size }` }
				role="img"
				aria-label={ segments
					.map(
						( segment ) => `${ segment.label }: ${ segment.value }`
					)
					.join( ', ' ) }
			>
				<circle
					className="gpi-donut__track"
					cx={ size / 2 }
					cy={ size / 2 }
					r={ radius }
					strokeWidth={ stroke }
					fill="none"
				/>
				{ total > 0 &&
					segments.map( ( segment, index ) => {
						const length =
							( segment.value / total ) * circumference;
						const gap =
							segments.filter( ( item ) => item.value > 0 )
								.length > 1
								? 2
								: 0;
						const element = segment.value > 0 && (
							<circle
								key={ segment.key }
								className={ `gpi-donut__segment gpi-donut__segment--${ segment.key }${ active === index ? ' is-active' : '' }` }
								cx={ size / 2 }
								cy={ size / 2 }
								r={ radius }
								strokeWidth={ stroke }
								fill="none"
								strokeDasharray={ `${ Math.max( 0, length - gap ) } ${ circumference }` }
								strokeDashoffset={ -offset }
								transform={ `rotate(-90 ${ size / 2 } ${ size / 2 })` }
								onMouseEnter={ () => setActive( index ) }
								onMouseLeave={ () => setActive( null ) }
							/>
						);
						offset += length;
						return element;
					} ) }
				<text
					className="gpi-donut__value"
					x="50%"
					y="46%"
					dominantBaseline="central"
					textAnchor="middle"
				>
					{ active !== null ? segments[ active ].value : centerValue }
				</text>
				<text
					className="gpi-donut__label"
					x="50%"
					y="62%"
					dominantBaseline="central"
					textAnchor="middle"
				>
					{ active !== null ? segments[ active ].label : centerLabel }
				</text>
			</svg>
			<ul className="gpi-legend">
				{ segments.map( ( segment, index ) => (
					<li
						key={ segment.key }
						onMouseEnter={ () => setActive( index ) }
						onMouseLeave={ () => setActive( null ) }
					>
						<span
							className={ `gpi-dot gpi-dot--${ segment.key }` }
						/>
						<span className="gpi-legend__label">
							{ segment.label }
						</span>
						<span className="gpi-legend__value">
							{ segment.value }
						</span>
						<span className="gpi-legend__share">
							{ total
								? Math.round( ( segment.value / total ) * 100 )
								: 0 }
							%
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

/* -------------------------------------------------------------------------
 * Horizontal bar list
 * ---------------------------------------------------------------------- */

export function BarList( {
	items,
	max,
	valueLabel,
	renderLabel,
	renderExtra,
} ) {
	const top = max || Math.max( 1, ...items.map( ( item ) => item.value ) );
	return (
		<ul className="gpi-barlist">
			{ items.map( ( item ) => (
				<li key={ item.key } className="gpi-barlist__item">
					<div className="gpi-barlist__head">
						<span className="gpi-barlist__label">
							{ renderLabel ? renderLabel( item ) : item.label }
						</span>
						<span className="gpi-barlist__value">
							{ valueLabel ? valueLabel( item ) : item.value }
						</span>
					</div>
					<div className="gpi-barlist__track">
						<div
							className={ `gpi-barlist__fill gpi-barlist__fill--${ item.rating || 'accent' }` }
							style={ {
								width: `${ Math.max( 2, ( item.value / top ) * 100 ) }%`,
							} }
						/>
					</div>
					{ renderExtra && renderExtra( item ) }
				</li>
			) ) }
		</ul>
	);
}

/* -------------------------------------------------------------------------
 * Good / needs improvement / poor distribution (Chrome UX Report)
 * ---------------------------------------------------------------------- */

export function DistributionBar( { distributions = [] } ) {
	const keys = [ 'good', 'average', 'poor' ];
	return (
		<div
			className="gpi-distribution"
			role="img"
			aria-label={ distributions
				.map(
					( bucket, index ) =>
						`${ RATING_LABELS[ keys[ index ] ] } ${ Math.round( bucket.proportion * 100 ) }%`
				)
				.join( ', ' ) }
		>
			{ distributions.map( ( bucket, index ) => (
				<div
					key={ index }
					className={ `gpi-distribution__part gpi-distribution__part--${ keys[ index ] }` }
					style={ {
						flexGrow: Math.max( bucket.proportion, 0.0001 ),
					} }
					title={ `${ RATING_LABELS[ keys[ index ] ] }: ${ Math.round( bucket.proportion * 100 ) }%` }
				>
					{ bucket.proportion >= 0.08 && (
						<span>{ Math.round( bucket.proportion * 100 ) }%</span>
					) }
				</div>
			) ) }
		</div>
	);
}

/* -------------------------------------------------------------------------
 * Threshold scale with marker (lab metrics)
 * ---------------------------------------------------------------------- */

export function ThresholdScale( { value, good, poor } ) {
	const max = poor * 1.6;
	const position =
		value === null || value === undefined
			? null
			: Math.min( 100, ( value / max ) * 100 );
	return (
		<div className="gpi-scale" aria-hidden="true">
			<div
				className="gpi-scale__part gpi-scale__part--good"
				style={ { width: `${ ( good / max ) * 100 }%` } }
			/>
			<div
				className="gpi-scale__part gpi-scale__part--average"
				style={ { width: `${ ( ( poor - good ) / max ) * 100 }%` } }
			/>
			<div className="gpi-scale__part gpi-scale__part--poor" />
			{ position !== null && (
				<div
					className="gpi-scale__marker"
					style={ { left: `${ position }%` } }
				/>
			) }
		</div>
	);
}

/* -------------------------------------------------------------------------
 * Grouped bars for comparing two values (snapshots)
 * ---------------------------------------------------------------------- */

export function CompareBars( { rows, leftLabel, rightLabel } ) {
	return (
		<div className="gpi-compare">
			<div className="gpi-compare__legend">
				<span>
					<span className="gpi-dot gpi-dot--before" />
					{ leftLabel }
				</span>
				<span>
					<span className="gpi-dot gpi-dot--after" />
					{ rightLabel }
				</span>
			</div>
			{ rows.map( ( row ) => {
				const delta =
					row.left !== null && row.right !== null
						? row.right - row.left
						: null;
				return (
					<div className="gpi-compare__row" key={ row.key }>
						<div className="gpi-compare__label">{ row.label }</div>
						<div className="gpi-compare__bars">
							<div
								className="gpi-compare__bar gpi-compare__bar--before"
								style={ { width: `${ row.left ?? 0 }%` } }
							>
								<span>{ row.left ?? '–' }</span>
							</div>
							<div
								className="gpi-compare__bar gpi-compare__bar--after"
								style={ { width: `${ row.right ?? 0 }%` } }
							>
								<span>{ row.right ?? '–' }</span>
							</div>
						</div>
						<div
							className={ `gpi-delta${ delta > 0 ? ' is-up' : '' }${ delta < 0 ? ' is-down' : '' }` }
						>
							{ delta === null
								? '–'
								: ( delta > 0 ? '+' : '' ) + delta }
						</div>
					</div>
				);
			} ) }
		</div>
	);
}

export function useChartColors() {
	return useMemo(
		() => ( {
			performance: 'var(--gpi-c-performance)',
			accessibility: 'var(--gpi-c-accessibility)',
			'best-practices': 'var(--gpi-c-best-practices)',
			seo: 'var(--gpi-c-seo)',
			'agentic-browsing': 'var(--gpi-c-agentic)',
		} ),
		[]
	);
}
