import { Button } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../api';
import { navigate, useApp, useResource } from '../store';
import {
	DistributionBar,
	LineChart,
	ScoreBadge,
	useChartColors,
} from '../components/charts';
import {
	Card,
	ErrorMessage,
	ExternalLink,
	Html,
	PageHeader,
	Pill,
	Skeleton,
} from '../components/ui';
import {
	CategoryRings,
	MetricTile,
	ShortPixelOffer,
} from '../components/shared';
import AuditDetails from '../components/AuditDetails';
import {
	CATEGORY_LABELS,
	CATEGORY_ORDER,
	FIELD_METRICS,
	METRIC_ORDER,
	RATING_LABELS,
	formatDate,
	formatMetric,
	formatMs,
	metricRating,
	splitUrl,
	timeAgo,
	typeLabel,
} from '../utils';

const GROUP_LABELS = {
	insights: __( 'Insights', 'google-pagespeed-insights' ),
	diagnostics: __( 'Diagnostics', 'google-pagespeed-insights' ),
	'a11y-aria': __( 'ARIA', 'google-pagespeed-insights' ),
	'a11y-names-labels': __( 'Names and labels', 'google-pagespeed-insights' ),
	'a11y-best-practices': __( 'Best practices', 'google-pagespeed-insights' ),
	'a11y-color-contrast': __( 'Contrast', 'google-pagespeed-insights' ),
	'a11y-tables-lists': __( 'Tables and lists', 'google-pagespeed-insights' ),
	'a11y-navigation': __( 'Navigation', 'google-pagespeed-insights' ),
	'a11y-language': __(
		'Internationalization and localization',
		'google-pagespeed-insights'
	),
	'a11y-audio-video': __( 'Audio and video', 'google-pagespeed-insights' ),
	'best-practices-trust-safety': __(
		'Trust and safety',
		'google-pagespeed-insights'
	),
	'best-practices-ux': __( 'User experience', 'google-pagespeed-insights' ),
	'best-practices-browser-compat': __(
		'Browser compatibility',
		'google-pagespeed-insights'
	),
	'best-practices-general': __( 'General', 'google-pagespeed-insights' ),
	'seo-content': __( 'Content', 'google-pagespeed-insights' ),
	'seo-crawl': __( 'Crawling and indexing', 'google-pagespeed-insights' ),
	'seo-mobile': __( 'Mobile friendly', 'google-pagespeed-insights' ),
	'agent-accessibility': __(
		'Agent accessibility',
		'google-pagespeed-insights'
	),
	'agent-discoverability': __(
		'Agent discoverability',
		'google-pagespeed-insights'
	),
	webmcp: __( 'WebMCP', 'google-pagespeed-insights' ),
	'': __( 'Other checks', 'google-pagespeed-insights' ),
};

const SAVINGS_LABELS = {
	LCP: 'LCP',
	FCP: 'FCP',
	TBT: 'TBT',
	CLS: 'CLS',
	INP: 'INP',
};

export function auditStatus( audit ) {
	const mode = audit.scoreDisplayMode;
	if ( mode === 'notApplicable' ) {
		return 'na';
	}
	if ( mode === 'manual' ) {
		return 'manual';
	}
	if ( mode === 'informative' || audit.score === null ) {
		return 'info';
	}
	if ( audit.score >= 0.9 ) {
		return 'pass';
	}
	if ( audit.score >= 0.5 && mode !== 'binary' ) {
		return 'average';
	}
	return 'fail';
}

function Savings( { savings } ) {
	const entries = Object.entries( savings || {} ).filter(
		( [ key, value ] ) => SAVINGS_LABELS[ key ] && value > 0
	);
	if ( ! entries.length ) {
		return null;
	}
	return (
		<span className="gpi-savings">
			{ entries.map( ( [ key, value ] ) => (
				<span key={ key } className="gpi-savings__chip">
					{ SAVINGS_LABELS[ key ] }{ ' ' }
					{ key === 'CLS'
						? `−${ value.toFixed( 3 ) }`
						: `−${ formatMs( value ) }` }
				</span>
			) ) }
		</span>
	);
}

function AuditItem( { audit } ) {
	const [ open, setOpen ] = useState( false );
	const status = auditStatus( audit );
	const hasBody =
		audit.description ||
		audit.details ||
		audit.stackTip ||
		audit.imageOffer;

	return (
		<li
			className={ `gpi-audit gpi-audit--${ status }${ open ? ' is-open' : '' }` }
		>
			<button
				type="button"
				className="gpi-audit__head"
				aria-expanded={ open }
				onClick={ () => hasBody && setOpen( ! open ) }
				disabled={ ! hasBody }
			>
				<span
					className={ `gpi-status gpi-status--${ status }` }
					aria-hidden="true"
				/>
				<span className="gpi-audit__title">{ audit.title }</span>
				{ audit.displayValue && (
					<span className="gpi-audit__value">
						{ audit.displayValue }
					</span>
				) }
				<Savings savings={ audit.metricSavings } />
				{ hasBody && (
					<span
						className="gpi-audit__chevron dashicons dashicons-arrow-down-alt2"
						aria-hidden="true"
					/>
				) }
			</button>
			{ open && (
				<div className="gpi-audit__body">
					<Html
						className="gpi-audit__description"
						html={ audit.description }
					/>
					{ audit.stackTip && (
						<div className="gpi-tip">
							<span
								className="dashicons dashicons-wordpress"
								aria-hidden="true"
							/>
							<div>
								<strong>
									{ __(
										'WordPress tip',
										'google-pagespeed-insights'
									) }
								</strong>
								<Html html={ audit.stackTip } />
							</div>
						</div>
					) }
					{ audit.imageOffer && <ShortPixelOffer /> }
					<AuditDetails details={ audit.details } />
				</div>
			) }
		</li>
	);
}

function AuditGroup( { title, audits, defaultOpen = true, tone } ) {
	const [ open, setOpen ] = useState( defaultOpen );
	if ( ! audits.length ) {
		return null;
	}
	return (
		<div
			className={ `gpi-auditgroup${ tone ? ` gpi-auditgroup--${ tone }` : '' }` }
		>
			<button
				type="button"
				className="gpi-auditgroup__head"
				aria-expanded={ open }
				onClick={ () => setOpen( ! open ) }
			>
				<span>{ title }</span>
				<span className="gpi-auditgroup__count">{ audits.length }</span>
				<span
					className={ `dashicons dashicons-arrow-${ open ? 'up' : 'down' }-alt2` }
					aria-hidden="true"
				/>
			</button>
			{ open && (
				<ul className="gpi-auditlist">
					{ audits.map( ( audit ) => (
						<AuditItem key={ audit.id } audit={ audit } />
					) ) }
				</ul>
			) }
		</div>
	);
}

function sortAudits( audits ) {
	const order = { fail: 0, average: 1, info: 2, manual: 3, pass: 4, na: 5 };
	return [ ...audits ].sort( ( a, b ) => {
		const statusOrder =
			order[ auditStatus( a ) ] - order[ auditStatus( b ) ];
		if ( statusOrder ) {
			return statusOrder;
		}
		const savings = ( audit ) =>
			Object.values( audit.metricSavings || {} ).reduce(
				( sum, value ) => sum + ( value > 1 ? value : 0 ),
				0
			);
		return (
			savings( b ) - savings( a ) || ( b.weight || 0 ) - ( a.weight || 0 )
		);
	} );
}

function AuditsCard( { audits, categories } ) {
	const ids = CATEGORY_ORDER.filter( ( id ) =>
		audits.some( ( audit ) => audit.category === id )
	);
	const [ tab, setTab ] = useState( ids[ 0 ] );
	const active = ids.includes( tab ) ? tab : ids[ 0 ];

	const grouped = useMemo( () => {
		const list = sortAudits(
			audits.filter( ( audit ) => audit.category === active )
		);
		const failing = list.filter(
			( audit ) =>
				[ 'fail', 'average' ].includes( auditStatus( audit ) ) ||
				( auditStatus( audit ) === 'info' &&
					audit.group === 'insights' &&
					audit.details )
		);
		const rest = list.filter( ( audit ) => ! failing.includes( audit ) );
		const groups = {};
		failing.forEach( ( audit ) => {
			groups[ audit.group || '' ] = groups[ audit.group || '' ] || [];
			groups[ audit.group || '' ].push( audit );
		} );
		return {
			groups,
			passed: rest.filter( ( audit ) => auditStatus( audit ) === 'pass' ),
			info: rest.filter( ( audit ) => auditStatus( audit ) === 'info' ),
			manual: rest.filter(
				( audit ) => auditStatus( audit ) === 'manual'
			),
			na: rest.filter( ( audit ) => auditStatus( audit ) === 'na' ),
		};
	}, [ audits, active ] );

	if ( ! ids.length ) {
		return null;
	}

	const failingCount = ( id ) =>
		audits.filter(
			( audit ) =>
				audit.category === id &&
				[ 'fail', 'average' ].includes( auditStatus( audit ) )
		).length;
	// Insights first, then the other groups in their Lighthouse order.
	const groupOrder = Object.keys( grouped.groups ).sort(
		( a, b ) => ( b === 'insights' ) - ( a === 'insights' )
	);

	return (
		<Card
			title={ __( 'Audits', 'google-pagespeed-insights' ) }
			description={ __(
				'What Lighthouse found on this page, ordered by impact. Expand an item for details and how to fix it.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-12"
		>
			<div className="gpi-tabs" role="tablist">
				{ ids.map( ( id ) => (
					<button
						key={ id }
						type="button"
						role="tab"
						aria-selected={ active === id }
						className={ active === id ? 'is-active' : '' }
						onClick={ () => setTab( id ) }
					>
						{ CATEGORY_LABELS[ id ] }
						{ categories[ id ] && (
							<ScoreBadge
								score={ categories[ id ].score }
								size="sm"
							/>
						) }
						{ failingCount( id ) > 0 && (
							<span className="gpi-tabs__count">
								{ failingCount( id ) }
							</span>
						) }
					</button>
				) ) }
			</div>
			{ ! groupOrder.length && (
				<p className="gpi-allgood">
					<span
						className="dashicons dashicons-yes-alt"
						aria-hidden="true"
					/>
					{ __(
						'No issues found in this category.',
						'google-pagespeed-insights'
					) }
				</p>
			) }
			{ groupOrder.map( ( group ) => (
				<AuditGroup
					key={ group }
					title={ GROUP_LABELS[ group ] || group }
					audits={ grouped.groups[ group ] }
					tone="fail"
				/>
			) ) }
			<AuditGroup
				title={ __(
					'Additional information',
					'google-pagespeed-insights'
				) }
				audits={ grouped.info }
				defaultOpen={ false }
			/>
			<AuditGroup
				title={ __(
					'Items to check manually',
					'google-pagespeed-insights'
				) }
				audits={ grouped.manual }
				defaultOpen={ false }
			/>
			<AuditGroup
				title={ __( 'Passed audits', 'google-pagespeed-insights' ) }
				audits={ grouped.passed }
				defaultOpen={ false }
				tone="pass"
			/>
			<AuditGroup
				title={ __( 'Not applicable', 'google-pagespeed-insights' ) }
				audits={ grouped.na }
				defaultOpen={ false }
			/>
		</Card>
	);
}

function FieldDataCard( { field } ) {
	const hasPage = Object.keys( field.page || {} ).length > 0;
	const hasOrigin = Object.keys( field.origin || {} ).length > 0;
	const [ scope, setScope ] = useState( hasPage ? 'page' : 'origin' );
	const metrics = ( scope === 'page' ? field.page : field.origin ) || {};
	const overall =
		scope === 'page' ? field.page_overall : field.origin_overall;
	const assessment = { fast: 'good', average: 'average', slow: 'poor' }[
		overall
	];

	return (
		<Card
			title={ __( 'Real-user experience', 'google-pagespeed-insights' ) }
			description={ __(
				'Core Web Vitals from the Chrome UX Report: how real Chrome users experienced the page over the last 28 days.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-12"
			actions={
				hasPage &&
				hasOrigin && (
					<div className="gpi-segmented gpi-segmented--small">
						<button
							type="button"
							className={ scope === 'page' ? 'is-active' : '' }
							onClick={ () => setScope( 'page' ) }
						>
							{ __( 'This page', 'google-pagespeed-insights' ) }
						</button>
						<button
							type="button"
							className={ scope === 'origin' ? 'is-active' : '' }
							onClick={ () => setScope( 'origin' ) }
						>
							{ __( 'Whole site', 'google-pagespeed-insights' ) }
						</button>
					</div>
				)
			}
		>
			{ ! hasPage && ! hasOrigin ? (
				<div className="gpi-fieldempty">
					<span
						className="dashicons dashicons-groups"
						aria-hidden="true"
					/>
					<p>
						{ __(
							'Not enough real-user data yet. Google only publishes Chrome UX Report data for pages and sites with enough visitors, so smaller sites often have none. The lab results above still show what to improve.',
							'google-pagespeed-insights'
						) }
					</p>
				</div>
			) : (
				<>
					{ assessment && (
						<div
							className={ `gpi-assessment gpi-assessment--${ assessment }` }
						>
							<span
								className="dashicons dashicons-chart-area"
								aria-hidden="true"
							/>
							{ assessment === 'good'
								? __(
										'Core Web Vitals assessment: Passed',
										'google-pagespeed-insights'
									)
								: __(
										'Core Web Vitals assessment: Failed',
										'google-pagespeed-insights'
									) }
						</div>
					) }
					<div className="gpi-field">
						{ Object.keys( FIELD_METRICS )
							.filter( ( id ) => metrics[ id ] )
							.map( ( id ) => {
								const metric = metrics[ id ];
								const config = FIELD_METRICS[ id ];
								const rating = metricRating(
									id,
									id === 'CUMULATIVE_LAYOUT_SHIFT_SCORE'
										? metric.percentile / 100
										: metric.percentile
								);
								return (
									<div key={ id } className="gpi-field__row">
										<div className="gpi-field__name">
											<strong>{ config.acronym }</strong>
											<span>{ config.label }</span>
											{ config.core && (
												<Pill tone="accent">
													{ __(
														'Core Web Vital',
														'google-pagespeed-insights'
													) }
												</Pill>
											) }
										</div>
										<div
											className={ `gpi-field__value gpi-text--${ rating }` }
										>
											{ formatMetric(
												id,
												metric.percentile
											) }
											<span className="gpi-field__rating">
												{ RATING_LABELS[ rating ] }
											</span>
										</div>
										<DistributionBar
											distributions={
												metric.distributions
											}
										/>
									</div>
								);
							} ) }
					</div>
				</>
			) }
		</Card>
	);
}

function HistoryCard( { history } ) {
	const colors = useChartColors();
	const data = history.map( ( point ) => ( {
		time: point.time,
		...point.categories,
		performance: point.categories.performance ?? point.score,
	} ) );
	const series = CATEGORY_ORDER.map( ( id ) => ( {
		key: id,
		label: CATEGORY_LABELS[ id ],
		color: colors[ id ],
		area: id === 'performance',
	} ) );
	return (
		<Card
			title={ __( 'Score history', 'google-pagespeed-insights' ) }
			description={ __(
				'Each point is one check of this page.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-12"
		>
			<LineChart
				series={ series }
				data={ data }
				xKey="time"
				formatX={ ( value ) => formatDate( value ) }
				height={ 220 }
				emptyText={ __(
					'History starts with this version of the plugin. Each new check adds a point.',
					'google-pagespeed-insights'
				) }
			/>
		</Card>
	);
}

function Filmstrip( { frames } ) {
	if ( ! frames.length ) {
		return null;
	}
	return (
		<Card
			title={ __( 'Page load', 'google-pagespeed-insights' ) }
			description={ __(
				'How the page renders while it loads.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-12"
		>
			<div className="gpi-filmstrip">
				{ frames.map( ( frame, index ) => (
					<figure key={ index }>
						{ frame.data &&
							frame.data.startsWith( 'data:image/' ) && (
								<img src={ frame.data } alt="" loading="lazy" />
							) }
						<figcaption>{ formatMs( frame.timing ) }</figcaption>
					</figure>
				) ) }
			</div>
		</Card>
	);
}

export default function ReportDetail( { id } ) {
	const { app, view, revision, notify, invalidate, expectScan } = useApp();
	const { data, loading, error, reload } = useResource(
		`/reports/${ id }`,
		{ strategy: view },
		[ revision ]
	);
	const [ busy, setBusy ] = useState( false );

	const recheck = () => {
		setBusy( true );
		post( '/reports/batch', { action: 'recheck', ids: [ id ] } )
			.then( () => {
				notify(
					__(
						'This page will be rechecked. The report updates when the check is done.',
						'google-pagespeed-insights'
					)
				);
				invalidate();
				expectScan();
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const back = {
		href: '#/reports',
		label: __( 'Reports', 'google-pagespeed-insights' ),
	};

	if ( error ) {
		return (
			<>
				<PageHeader
					back={ back }
					title={ __( 'Report', 'google-pagespeed-insights' ) }
				/>
				<ErrorMessage message={ error } onRetry={ reload } />
			</>
		);
	}

	if ( ! data ) {
		return (
			<>
				<PageHeader
					back={ back }
					title={ __(
						'Loading report…',
						'google-pagespeed-insights'
					) }
				/>
				<div className="gpi-grid">
					<Card className="gpi-span-12">
						<Skeleton rows={ 3 } height={ 24 } />
					</Card>
					<Card className="gpi-span-12">
						<Skeleton rows={ 8 } />
					</Card>
				</div>
			</>
		);
	}

	const parts = splitUrl( data.url );
	const noData = data.score === null;

	return (
		<>
			<PageHeader
				back={ back }
				title={ parts.path }
				description={
					<>
						<span>{ parts.host }</span>
						<span className="gpi-sep" aria-hidden="true">
							·
						</span>
						<span>{ typeLabel( data.type, app.filters ) }</span>
						<span className="gpi-sep" aria-hidden="true">
							·
						</span>
						<span title={ formatDate( data.last_checked ) }>
							{
								/* translators: %s: relative time */ sprintf(
									__(
										'Checked %s',
										'google-pagespeed-insights'
									),
									timeAgo( data.last_checked )
								)
							}
						</span>
					</>
				}
				actions={
					<>
						<Button
							variant="secondary"
							href={ data.url }
							target="_blank"
							rel="noopener noreferrer"
							icon="external"
						>
							{ __( 'Open page', 'google-pagespeed-insights' ) }
						</Button>
						<Button
							variant="primary"
							icon="update"
							onClick={ recheck }
							isBusy={ busy }
							disabled={ busy }
						>
							{ __( 'Recheck', 'google-pagespeed-insights' ) }
						</Button>
					</>
				}
			/>

			{ noData ? (
				<Card>
					<p className="gpi-pad">
						{ view === 'mobile'
							? __(
									'There is no mobile report for this page yet. Switch to Desktop, or set the report type to "Both" in the Settings.',
									'google-pagespeed-insights'
								)
							: __(
									'There is no desktop report for this page yet. Switch to Mobile, or set the report type to "Both" in the Settings.',
									'google-pagespeed-insights'
								) }
					</p>
				</Card>
			) : (
				<div
					className={ `gpi-grid${ loading ? ' is-refreshing' : '' }` }
				>
					{ data.legacy && (
						<div className="gpi-span-12 gpi-banner">
							<span
								className="dashicons dashicons-info-outline"
								aria-hidden="true"
							/>
							{ __(
								'This report was created by an older version of the plugin. Recheck it to get Accessibility, Best Practices, SEO and AI agent results and Core Web Vitals.',
								'google-pagespeed-insights'
							) }
						</div>
					) }

					<Card
						className={ `gpi-span-12 gpi-card--hero${ data.screenshot ? ' has-shot' : '' }` }
					>
						<div className="gpi-hero">
							<CategoryRings
								categories={ data.categories }
								order={ CATEGORY_ORDER }
								size={ 112 }
							/>
							{ data.screenshot && (
								<img
									className="gpi-hero__shot"
									src={ data.screenshot }
									alt={ __(
										'Screenshot of the page',
										'google-pagespeed-insights'
									) }
								/>
							) }
						</div>
					</Card>

					<Card
						title={ __(
							'Lab metrics',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Measured by Lighthouse on Google’s servers. These metrics make up the Performance score.',
							'google-pagespeed-insights'
						) }
						className="gpi-span-12"
						actions={
							<ExternalLink
								href={ `https://pagespeed.web.dev/analysis?url=${ encodeURIComponent( data.url ) }&form_factor=${ view }` }
							>
								{ __(
									'Open in PageSpeed Insights',
									'google-pagespeed-insights'
								) }
							</ExternalLink>
						}
					>
						<div className="gpi-metrics-wrap">
							<div className="gpi-metrics">
								{ METRIC_ORDER.map( ( metricId ) => {
									const metric = data.lab.find(
										( item ) => item.id === metricId
									);
									return metric ? (
										<MetricTile
											key={ metricId }
											id={ metricId }
											value={ metric.numericValue }
											display={ metric.displayValue }
										/>
									) : null;
								} ) }
							</div>
						</div>
					</Card>

					<FieldDataCard field={ data.field } />
					<HistoryCard history={ data.history } />
					<Filmstrip frames={ data.filmstrip } />
					<AuditsCard
						audits={ data.audits }
						categories={ data.categories }
					/>
				</div>
			) }
			<div className="gpi-footnav">
				<Button variant="link" onClick={ () => navigate( 'reports' ) }>
					{ __(
						'← Back to all reports',
						'google-pagespeed-insights'
					) }
				</Button>
				<span className="gpi-muted">
					{
						/* translators: %d: number of audits */ sprintf(
							_n(
								'%d audit',
								'%d audits',
								data.audits.length,
								'google-pagespeed-insights'
							),
							data.audits.length
						)
					}
				</span>
			</div>
		</>
	);
}
