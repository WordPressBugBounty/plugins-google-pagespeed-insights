import { Button, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useApp } from '../store';
import {
	formatMetric,
	metricRating,
	METRICS,
	RATING_LABELS,
	CATEGORY_LABELS,
	CATEGORY_HINTS,
} from '../utils';
import { ScoreRing, ThresholdScale } from './charts';

const SHORTPIXEL_URL = 'https://shortpixel.com/h/af/PCFTWNN142247';

/**
 * Report type filter (pages, posts, custom URL labels, ...).
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.label
 */
export function FilterSelect( { value, onChange, label } ) {
	const { app } = useApp();
	const groups = {};
	app.filters.forEach( ( filter ) => {
		const group = filter.group || '';
		groups[ group ] = groups[ group ] || [];
		groups[ group ].push( filter );
	} );

	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			className="gpi-filter"
			label={ label || __( 'Show', 'google-pagespeed-insights' ) }
			hideLabelFromVision
			value={ value }
			onChange={ onChange }
		>
			{ Object.keys( groups ).map( ( group ) =>
				group === '' ? (
					groups[ group ].map( ( filter ) => (
						<option key={ filter.value } value={ filter.value }>
							{ filter.label }
						</option>
					) )
				) : (
					<optgroup key={ group } label={ group }>
						{ groups[ group ].map( ( filter ) => (
							<option key={ filter.value } value={ filter.value }>
								{ filter.label }
							</option>
						) ) }
					</optgroup>
				)
			) }
		</SelectControl>
	);
}

/**
 * ShortPixel image optimization offer, shown next to image audits.
 * @param root0
 * @param root0.compact
 */
export function ShortPixelOffer( { compact = false } ) {
	const logo = window.gpiApp && window.gpiApp.shortpixelLogo;

	if ( compact ) {
		return (
			<a
				className="gpi-offer-link"
				href={ SHORTPIXEL_URL }
				target="_blank"
				rel="noopener noreferrer sponsored"
			>
				{ __(
					'Auto-optimize images with ShortPixel – 150 free credits',
					'google-pagespeed-insights'
				) }
				<span className="gpi-offer-link__tag">
					{ __( 'Affiliate', 'google-pagespeed-insights' ) }
				</span>
			</a>
		);
	}

	return (
		<div className="gpi-offer">
			{ logo && <img className="gpi-offer__logo" src={ logo } alt="" /> }
			<div className="gpi-offer__text">
				<strong>
					{ __(
						'Optimize every image automatically',
						'google-pagespeed-insights'
					) }
				</strong>
				<p>
					{ __(
						'Unoptimized images are one of the biggest factors in PageSpeed scores. ShortPixel compresses and converts your images to WebP/AVIF automatically. Sign up through this link to get 150 free credits.',
						'google-pagespeed-insights'
					) }
				</p>
				<span className="gpi-offer__disclosure">
					{ __(
						'Insights from Google PageSpeed is a ShortPixel affiliate; signing up through this link supports the plugin.',
						'google-pagespeed-insights'
					) }
				</span>
			</div>
			<Button
				variant="secondary"
				href={ SHORTPIXEL_URL }
				target="_blank"
				rel="noopener noreferrer sponsored"
			>
				{ __( 'Try ShortPixel free', 'google-pagespeed-insights' ) }
			</Button>
		</div>
	);
}

/**
 * Row of category score rings.
 * @param root0
 * @param root0.categories
 * @param root0.size
 * @param root0.order
 */
export function CategoryRings( { categories, size = 104, order } ) {
	const ids = ( order || Object.keys( CATEGORY_LABELS ) ).filter(
		( id ) => categories && categories[ id ]
	);
	if ( ! ids.length ) {
		return null;
	}
	return (
		<div
			className="gpi-rings"
			style={ {
				gridTemplateColumns: `repeat(auto-fit, minmax(${ size + 8 }px, 1fr))`,
			} }
		>
			{ ids.map( ( id ) => (
				<div
					className="gpi-rings__item"
					key={ id }
					title={ CATEGORY_HINTS[ id ] }
				>
					<ScoreRing
						size={ size }
						score={ categories[ id ].score }
						label={
							CATEGORY_LABELS[ id ] || categories[ id ].title
						}
					/>
					{ id === 'agentic-browsing' && (
						<span className="gpi-tag">
							{ __( 'Beta', 'google-pagespeed-insights' ) }
						</span>
					) }
				</div>
			) ) }
		</div>
	);
}

/**
 * Lab metric tile with value, rating and threshold scale.
 * @param root0
 * @param root0.id
 * @param root0.value
 * @param root0.display
 * @param root0.caption
 */
export function MetricTile( { id, value, display, caption } ) {
	const metric = METRICS[ id ];
	if ( ! metric ) {
		return null;
	}
	const rating = metricRating( id, value );
	return (
		<div className={ `gpi-metric gpi-metric--${ rating }` }>
			<div className="gpi-metric__head">
				<span className="gpi-metric__acronym">{ metric.acronym }</span>
				<span
					className={ `gpi-metric__rating gpi-metric__rating--${ rating }` }
				>
					{ RATING_LABELS[ rating ] }
				</span>
			</div>
			<div className="gpi-metric__value">
				{ display || formatMetric( id, value ) }
			</div>
			<div className="gpi-metric__label">{ metric.label }</div>
			<ThresholdScale
				value={ value }
				good={ metric.good }
				poor={ metric.poor }
			/>
			<div className="gpi-metric__thresholds">
				<span>
					{
						/* translators: %s: threshold value */ sprintf(
							__( 'Good ≤ %s', 'google-pagespeed-insights' ),
							formatMetric( id, metric.good )
						)
					}
				</span>
				<span>
					{
						/* translators: %s: threshold value */ sprintf(
							__( 'Poor > %s', 'google-pagespeed-insights' ),
							formatMetric( id, metric.poor )
						)
					}
				</span>
			</div>
			{ caption && (
				<div className="gpi-metric__caption">{ caption }</div>
			) }
		</div>
	);
}
