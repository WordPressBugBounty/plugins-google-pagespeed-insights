import { SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { navigate, useResource } from '../store';
import {
	BarList,
	CompareBars,
	DonutChart,
	ScoreBadge,
} from '../components/charts';
import {
	Card,
	ErrorMessage,
	PageHeader,
	Pill,
	Skeleton,
	UrlCell,
} from '../components/ui';
import { CategoryRings, MetricTile } from '../components/shared';
import {
	CATEGORY_LABELS,
	CATEGORY_ORDER,
	METRIC_ORDER,
	formatDate,
	formatMetric,
	metricRating,
	scoreRating,
} from '../utils';

function SummaryBody( { summary } ) {
	const distribution =
		summary.distribution && Object.keys( summary.distribution ).length
			? [
					{
						key: 'good',
						label: __(
							'Good (90–100)',
							'google-pagespeed-insights'
						),
						value: summary.distribution.good || 0,
					},
					{
						key: 'average',
						label: __(
							'Needs work (50–89)',
							'google-pagespeed-insights'
						),
						value: summary.distribution.average || 0,
					},
					{
						key: 'poor',
						label: __( 'Poor (0–49)', 'google-pagespeed-insights' ),
						value: summary.distribution.poor || 0,
					},
				]
			: null;

	return (
		<div className="gpi-grid">
			<Card className="gpi-span-12 gpi-card--hero">
				<CategoryRings
					categories={ summary.categories || {} }
					order={ CATEGORY_ORDER }
					size={ 104 }
				/>
				{ summary.format === 1 && (
					<p className="gpi-hint">
						{ __(
							'Saved by an older version of the plugin, so only the Performance score is available.',
							'google-pagespeed-insights'
						) }
					</p>
				) }
			</Card>
			{ summary.metrics && Object.keys( summary.metrics ).length > 0 && (
				<Card
					title={ __(
						'Lab metrics (75th percentile)',
						'google-pagespeed-insights'
					) }
					className="gpi-span-12"
				>
					<div className="gpi-metrics-wrap">
						<div className="gpi-metrics gpi-metrics--compact">
							{ METRIC_ORDER.filter(
								( id ) => summary.metrics[ id ]
							).map( ( id ) => (
								<MetricTile
									key={ id }
									id={ id }
									value={ summary.metrics[ id ].p75 }
								/>
							) ) }
						</div>
					</div>
				</Card>
			) }
			{ distribution && (
				<Card
					title={ __(
						'Performance distribution',
						'google-pagespeed-insights'
					) }
					className="gpi-span-4"
				>
					<DonutChart
						segments={ distribution }
						centerValue={ summary.score }
						centerLabel={ __(
							'avg. score',
							'google-pagespeed-insights'
						) }
					/>
				</Card>
			) }
			{ summary.worst && summary.worst.length > 0 && (
				<Card
					title={ __(
						'Lowest scoring pages',
						'google-pagespeed-insights'
					) }
					className="gpi-span-8"
				>
					<ul className="gpi-pagelist">
						{ summary.worst.map( ( page, index ) => (
							<li key={ index }>
								<ScoreBadge score={ page.score } />
								<UrlCell url={ page.url } />
							</li>
						) ) }
					</ul>
				</Card>
			) }
			{ summary.issues && summary.issues.length > 0 && (
				<Card
					title={ __(
						'Top opportunities',
						'google-pagespeed-insights'
					) }
					className="gpi-span-12"
				>
					<BarList
						items={ summary.issues.map( ( issue ) => ( {
							key: issue.id,
							label: issue.title,
							value: issue.pages,
							rating: scoreRating( issue.score ),
						} ) ) }
						valueLabel={ ( item ) =>
							sprintf(
								/* translators: %d: number of pages */ __(
									'%d pages',
									'google-pagespeed-insights'
								),
								item.value
							)
						}
					/>
				</Card>
			) }
		</div>
	);
}

function Compare( { left, right } ) {
	const a = left.summary;
	const b = right.summary;
	const categoryRows = CATEGORY_ORDER.filter(
		( id ) =>
			( a.categories && a.categories[ id ] ) ||
			( b.categories && b.categories[ id ] )
	).map( ( id ) => ( {
		key: id,
		label: CATEGORY_LABELS[ id ],
		left:
			a.categories && a.categories[ id ]
				? a.categories[ id ].score
				: null,
		right:
			b.categories && b.categories[ id ]
				? b.categories[ id ].score
				: null,
	} ) );
	const metricIds = METRIC_ORDER.filter(
		( id ) =>
			( a.metrics && a.metrics[ id ] ) || ( b.metrics && b.metrics[ id ] )
	);

	return (
		<div className="gpi-grid">
			<Card
				title={ __( 'Category scores', 'google-pagespeed-insights' ) }
				className="gpi-span-7"
			>
				<CompareBars
					rows={ categoryRows }
					leftLabel={ formatDate( left.time ) }
					rightLabel={ formatDate( right.time ) }
				/>
			</Card>
			<Card
				title={ __(
					'Lab metrics (75th percentile)',
					'google-pagespeed-insights'
				) }
				className="gpi-span-5"
			>
				{ metricIds.length ? (
					<table className="gpi-table gpi-table--compact">
						<thead>
							<tr>
								<th>
									{ __(
										'Metric',
										'google-pagespeed-insights'
									) }
								</th>
								<th className="gpi-table__num">
									{ __(
										'Before',
										'google-pagespeed-insights'
									) }
								</th>
								<th className="gpi-table__num">
									{ __(
										'After',
										'google-pagespeed-insights'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ metricIds.map( ( id ) => {
								const before =
									a.metrics && a.metrics[ id ]
										? a.metrics[ id ].p75
										: null;
								const after =
									b.metrics && b.metrics[ id ]
										? b.metrics[ id ].p75
										: null;
								const better =
									before !== null &&
									after !== null &&
									after < before;
								const worse =
									before !== null &&
									after !== null &&
									after > before;
								return (
									<tr key={ id }>
										<td>
											{ ( a.metrics &&
												a.metrics[ id ] &&
												a.metrics[ id ].acronym ) ||
												( b.metrics[ id ] &&
													b.metrics[ id ].acronym ) }
										</td>
										<td
											className={ `gpi-table__num gpi-text--${ metricRating( id, before ) }` }
										>
											{ formatMetric( id, before ) }
										</td>
										<td
											className={ `gpi-table__num gpi-text--${ metricRating( id, after ) }` }
										>
											{ formatMetric( id, after ) }
											{ better && (
												<span className="gpi-delta is-up">
													▲
												</span>
											) }
											{ worse && (
												<span className="gpi-delta is-down">
													▼
												</span>
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				) : (
					<p className="gpi-muted">
						{ __(
							'One of these snapshots was saved by an older version and has no metrics.',
							'google-pagespeed-insights'
						) }
					</p>
				) }
			</Card>
			<Card
				title={ formatDate( left.time ) }
				description={ left.comment }
				className="gpi-span-6"
			>
				<CategoryRings
					categories={ a.categories || {} }
					order={ CATEGORY_ORDER }
					size={ 80 }
				/>
			</Card>
			<Card
				title={ formatDate( right.time ) }
				description={ right.comment }
				className="gpi-span-6"
			>
				<CategoryRings
					categories={ b.categories || {} }
					order={ CATEGORY_ORDER }
					size={ 80 }
				/>
			</Card>
		</div>
	);
}

export default function SnapshotView( { id, compareId } ) {
	const first = useResource( `/snapshots/${ id }`, {} );
	const second = useResource(
		compareId ? `/snapshots/${ compareId }` : `/snapshots/${ id }`,
		{}
	);
	const list = useResource( '/snapshots', {} );
	const back = {
		href: '#/snapshots',
		label: __( 'Snapshots', 'google-pagespeed-insights' ),
	};

	const error = first.error || ( compareId && second.error );
	if ( error ) {
		return (
			<>
				<PageHeader
					back={ back }
					title={ __( 'Snapshot', 'google-pagespeed-insights' ) }
				/>
				<ErrorMessage message={ error } />
			</>
		);
	}

	if ( ! first.data || ( compareId && ! second.data ) ) {
		return (
			<>
				<PageHeader
					back={ back }
					title={ __(
						'Loading snapshot…',
						'google-pagespeed-insights'
					) }
				/>
				<Card>
					<Skeleton rows={ 6 } />
				</Card>
			</>
		);
	}

	const snapshot = first.data;
	const similar = ( list.data || [] ).filter(
		( item ) =>
			item.id !== snapshot.id && item.strategy === snapshot.strategy
	);
	const ordered =
		compareId && second.data.time < snapshot.time
			? [ second.data, snapshot ]
			: [ snapshot, compareId ? second.data : null ];

	return (
		<>
			<PageHeader
				back={ back }
				title={
					compareId
						? __( 'Compare snapshots', 'google-pagespeed-insights' )
						: formatDate( snapshot.time )
				}
				description={
					compareId ? (
						sprintf(
							/* translators: 1: first date, 2: second date */ __(
								'%1$s → %2$s',
								'google-pagespeed-insights'
							),
							formatDate( ordered[ 0 ].time ),
							formatDate( ordered[ 1 ].time )
						)
					) : (
						<>
							<Pill>
								{ snapshot.strategy === 'mobile'
									? __(
											'Mobile',
											'google-pagespeed-insights'
										)
									: __(
											'Desktop',
											'google-pagespeed-insights'
										) }
							</Pill>
							{ snapshot.comment && (
								<span className="gpi-quote">
									“{ snapshot.comment }”
								</span>
							) }
						</>
					)
				}
				actions={
					similar.length > 0 && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Compare with',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ compareId || '' }
							onChange={ ( value ) =>
								navigate(
									value
										? `snapshots/${ snapshot.id }/compare/${ value }`
										: `snapshots/${ snapshot.id }`
								)
							}
						>
							<option value="">
								{ __(
									'Compare with…',
									'google-pagespeed-insights'
								) }
							</option>
							{ similar.map( ( item ) => (
								<option key={ item.id } value={ item.id }>
									{ formatDate( item.time ) }
									{ item.comment
										? ` – ${ item.comment }`
										: '' }
								</option>
							) ) }
						</SelectControl>
					)
				}
			/>
			{ compareId ? (
				<Compare left={ ordered[ 0 ] } right={ ordered[ 1 ] } />
			) : (
				<SummaryBody summary={ snapshot.summary } />
			) }
		</>
	);
}
