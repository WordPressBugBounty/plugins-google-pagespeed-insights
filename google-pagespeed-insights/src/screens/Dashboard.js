import { Button, Modal, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../api';
import { navigate, useApp, useResource } from '../store';
import {
	BarList,
	DonutChart,
	LineChart,
	ScoreBadge,
	historyDateLabel,
	useChartColors,
} from '../components/charts';
import {
	Card,
	EmptyState,
	ErrorMessage,
	PageHeader,
	Pill,
	Skeleton,
	UrlCell,
} from '../components/ui';
import {
	CategoryRings,
	FilterSelect,
	MetricTile,
	ShortPixelOffer,
} from '../components/shared';
import {
	CATEGORY_LABELS,
	CATEGORY_ORDER,
	METRIC_ORDER,
	scoreRating,
} from '../utils';

function SaveSnapshot( { view, filter } ) {
	const { notify, invalidate } = useApp();
	const [ open, setOpen ] = useState( false );
	const [ comment, setComment ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const save = () => {
		setBusy( true );
		post( '/snapshots', { strategy: view, filter, comment } )
			.then( () => {
				notify( __( 'Snapshot saved.', 'google-pagespeed-insights' ) );
				setOpen( false );
				setComment( '' );
				invalidate();
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<>
			<Button
				variant="secondary"
				icon="camera"
				onClick={ () => setOpen( true ) }
			>
				{ __( 'Save snapshot', 'google-pagespeed-insights' ) }
			</Button>
			{ open && (
				<Modal
					title={ __(
						'Save a snapshot',
						'google-pagespeed-insights'
					) }
					onRequestClose={ () => setOpen( false ) }
					className="gpi-modal"
				>
					<p className="gpi-muted">
						{ __(
							'A snapshot stores the current overview so you can compare it later, for example before and after installing a caching plugin.',
							'google-pagespeed-insights'
						) }
					</p>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Note (optional)',
							'google-pagespeed-insights'
						) }
						placeholder={ __(
							'e.g. Before enabling page cache',
							'google-pagespeed-insights'
						) }
						value={ comment }
						maxLength={ 200 }
						onChange={ setComment }
					/>
					<div className="gpi-modal__actions">
						<Button
							variant="tertiary"
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Cancel', 'google-pagespeed-insights' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ busy }
							disabled={ busy }
						>
							{ __(
								'Save snapshot',
								'google-pagespeed-insights'
							) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
}

function TrendCard( { trend } ) {
	const colors = useChartColors();
	const [ hidden, setHidden ] = useState( [ 'best-practices', 'seo' ] );
	const series = CATEGORY_ORDER.map( ( id ) => ( {
		key: id,
		label: CATEGORY_LABELS[ id ],
		color: colors[ id ],
		area: id === 'performance',
	} ) );
	const available = series.filter( ( item ) =>
		trend.some( ( point ) => point[ item.key ] !== undefined )
	);

	return (
		<Card
			title={ __( 'Score trend', 'google-pagespeed-insights' ) }
			description={ __(
				'Daily average of every scanned page.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-8"
		>
			<div
				className="gpi-chips"
				role="group"
				aria-label={ __(
					'Categories shown in the chart',
					'google-pagespeed-insights'
				) }
			>
				{ available.map( ( item ) => {
					const on = ! hidden.includes( item.key );
					return (
						<button
							type="button"
							key={ item.key }
							className={ `gpi-chip${ on ? ' is-on' : '' }` }
							aria-pressed={ on }
							onClick={ () =>
								setHidden(
									on
										? [ ...hidden, item.key ]
										: hidden.filter(
												( key ) => key !== item.key
											)
								)
							}
						>
							<span
								className="gpi-dot"
								style={ { background: item.color } }
							/>
							{ item.label }
						</button>
					);
				} ) }
			</div>
			<LineChart
				series={ series.filter(
					( item ) => ! hidden.includes( item.key )
				) }
				data={ trend }
				formatX={ historyDateLabel }
			/>
			{ trend.length === 1 && (
				<p className="gpi-hint">
					<span
						className="dashicons dashicons-info-outline"
						aria-hidden="true"
					/>
					{ __(
						'All reports so far are from one day. The trend line builds up as your pages are checked again over the next days.',
						'google-pagespeed-insights'
					) }
				</p>
			) }
		</Card>
	);
}

function IssuesCard( { issues, pages } ) {
	const items = issues.map( ( issue ) => ( {
		key: issue.id,
		label: issue.title,
		value: issue.pages,
		rating: scoreRating( issue.score ),
		imageOffer: issue.imageOffer,
	} ) );

	return (
		<Card
			title={ __( 'Top opportunities', 'google-pagespeed-insights' ) }
			description={ __(
				'Performance insights that fail on the most pages.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-7"
		>
			{ items.length ? (
				<BarList
					items={ items }
					max={ pages }
					valueLabel={ ( item ) =>
						sprintf(
							/* translators: 1: pages with the issue, 2: all pages */ __(
								'%1$d of %2$d pages',
								'google-pagespeed-insights'
							),
							item.value,
							pages
						)
					}
					renderExtra={ ( item ) =>
						item.imageOffer && <ShortPixelOffer compact />
					}
				/>
			) : (
				<EmptyState
					icon="yes-alt"
					title={ __(
						'No common issues',
						'google-pagespeed-insights'
					) }
				>
					{ __(
						'None of the performance insights fail on your pages. Nice work!',
						'google-pagespeed-insights'
					) }
				</EmptyState>
			) }
		</Card>
	);
}

function PagesCard( { worst, best } ) {
	const [ tab, setTab ] = useState( 'worst' );
	const list = tab === 'worst' ? worst : best;
	return (
		<Card
			title={ __( 'Pages', 'google-pagespeed-insights' ) }
			className="gpi-span-5"
			actions={
				<div className="gpi-segmented gpi-segmented--small">
					<button
						type="button"
						className={ tab === 'worst' ? 'is-active' : '' }
						onClick={ () => setTab( 'worst' ) }
					>
						{ __( 'Fix first', 'google-pagespeed-insights' ) }
					</button>
					<button
						type="button"
						className={ tab === 'best' ? 'is-active' : '' }
						onClick={ () => setTab( 'best' ) }
					>
						{ __( 'Fastest', 'google-pagespeed-insights' ) }
					</button>
				</div>
			}
		>
			<ul className="gpi-pagelist">
				{ list.map( ( page ) => (
					<li key={ page.id }>
						<ScoreBadge score={ page.score } />
						<UrlCell
							url={ page.url }
							href={ `#/reports/${ page.id }` }
						/>
						<span
							className="gpi-pagelist__go dashicons dashicons-arrow-right-alt2"
							aria-hidden="true"
						/>
					</li>
				) ) }
			</ul>
			<div className="gpi-card__footer">
				<Button variant="link" onClick={ () => navigate( 'reports' ) }>
					{ __( 'View all reports', 'google-pagespeed-insights' ) }
				</Button>
			</div>
		</Card>
	);
}

function CategoryIssuesCard( { categoryIssues, categories } ) {
	const ids = [
		'accessibility',
		'best-practices',
		'seo',
		'agentic-browsing',
	].filter( ( id ) => categories[ id ] );
	const [ tab, setTab ] = useState( ids[ 0 ] );
	if ( ! ids.length ) {
		return null;
	}
	const active = ids.includes( tab ) ? tab : ids[ 0 ];
	const issues = categoryIssues[ active ] || [];
	return (
		<Card
			title={ __( 'Beyond performance', 'google-pagespeed-insights' ) }
			description={ __(
				'Failing checks in the other Lighthouse categories.',
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
						<ScoreBadge
							score={ categories[ id ].score }
							size="sm"
						/>
					</button>
				) ) }
			</div>
			{ issues.length ? (
				<ul className="gpi-issuelist">
					{ issues.map( ( issue ) => (
						<li key={ issue.id }>
							<span
								className="gpi-status gpi-status--fail"
								aria-hidden="true"
							/>
							<span className="gpi-issuelist__title">
								{ issue.title }
							</span>
							<Pill>
								{
									/* translators: %d: number of pages */ sprintf(
										_n(
											'%d page',
											'%d pages',
											issue.pages,
											'google-pagespeed-insights'
										),
										issue.pages
									)
								}
							</Pill>
						</li>
					) ) }
				</ul>
			) : (
				<p className="gpi-muted gpi-pad">
					{ __(
						'Every check in this category passes.',
						'google-pagespeed-insights'
					) }
				</p>
			) }
		</Card>
	);
}

export default function Dashboard() {
	const { app, view, filter, setFilter, revision, startScan, running } =
		useApp();
	const { data, loading, error, reload } = useResource(
		'/summary',
		{ strategy: view, filter },
		[ revision ]
	);

	const header = (
		<PageHeader
			title={ __( 'Overview', 'google-pagespeed-insights' ) }
			description={
				data && data.pages
					? sprintf(
							/* translators: %d: number of pages */ _n(
								'Average results of %d scanned page.',
								'Average results of %d scanned pages.',
								data.pages,
								'google-pagespeed-insights'
							),
							data.pages
						)
					: __(
							'How your site performs in Google PageSpeed Insights.',
							'google-pagespeed-insights'
						)
			}
			actions={
				<>
					<FilterSelect value={ filter } onChange={ setFilter } />
					{ data && data.pages > 0 && (
						<SaveSnapshot view={ view } filter={ filter } />
					) }
				</>
			}
		/>
	);

	if ( error ) {
		return (
			<>
				{ header }
				<ErrorMessage message={ error } onRetry={ reload } />
			</>
		);
	}

	if ( loading && ! data ) {
		return (
			<>
				{ header }
				<div className="gpi-grid">
					<Card className="gpi-span-12">
						<Skeleton rows={ 3 } height={ 24 } />
					</Card>
					<Card className="gpi-span-8">
						<Skeleton rows={ 6 } />
					</Card>
					<Card className="gpi-span-4">
						<Skeleton rows={ 6 } />
					</Card>
				</div>
			</>
		);
	}

	if ( ! data.pages ) {
		return (
			<>
				{ header }
				<Card>
					<EmptyState
						icon="performance"
						title={
							running
								? __(
										'Your first reports are on the way',
										'google-pagespeed-insights'
									)
								: __(
										'No reports yet',
										'google-pagespeed-insights'
									)
						}
						action={
							! running &&
							app.hasKey && (
								<Button
									variant="primary"
									onClick={ () => startScan( false ) }
								>
									{ __(
										'Start your first scan',
										'google-pagespeed-insights'
									) }
								</Button>
							)
						}
					>
						{ running
							? __(
									'Google is checking your pages now. This overview fills in as each page is done.',
									'google-pagespeed-insights'
								)
							: __(
									'Run a scan to check your pages with Google PageSpeed Insights. Each page gets a Performance, Accessibility, Best Practices, SEO and AI agent score.',
									'google-pagespeed-insights'
								) }
					</EmptyState>
				</Card>
			</>
		);
	}

	const distribution = [
		{
			key: 'good',
			label: __( 'Good (90–100)', 'google-pagespeed-insights' ),
			value: data.distribution.good || 0,
		},
		{
			key: 'average',
			label: __( 'Needs work (50–89)', 'google-pagespeed-insights' ),
			value: data.distribution.average || 0,
		},
		{
			key: 'poor',
			label: __( 'Poor (0–49)', 'google-pagespeed-insights' ),
			value: data.distribution.poor || 0,
		},
	];

	return (
		<>
			{ header }
			<div className={ `gpi-grid${ loading ? ' is-refreshing' : '' }` }>
				<Card className="gpi-span-12 gpi-card--hero">
					<CategoryRings
						categories={ data.categories }
						order={ CATEGORY_ORDER }
						size={ 112 }
					/>
					{ data.legacy > 0 && (
						<p className="gpi-hint">
							<span
								className="dashicons dashicons-info-outline"
								aria-hidden="true"
							/>
							{ sprintf(
								/* translators: %d: number of reports */ _n(
									'%d report was created by an older version and only has a Performance score. Recheck it to see all categories.',
									'%d reports were created by an older version and only have a Performance score. Recheck them to see all categories.',
									data.legacy,
									'google-pagespeed-insights'
								),
								data.legacy
							) }
						</p>
					) }
				</Card>

				<TrendCard trend={ data.trend } />

				<Card
					title={ __(
						'Performance distribution',
						'google-pagespeed-insights'
					) }
					description={ __(
						'Pages by Performance score.',
						'google-pagespeed-insights'
					) }
					className="gpi-span-4"
				>
					<DonutChart
						segments={ distribution }
						centerValue={ data.score }
						centerLabel={ __(
							'avg. score',
							'google-pagespeed-insights'
						) }
					/>
				</Card>

				<Card
					title={ __( 'Lab metrics', 'google-pagespeed-insights' ) }
					description={ __(
						'75th percentile across pages, measured by Lighthouse in a simulated environment.',
						'google-pagespeed-insights'
					) }
					className="gpi-span-12"
				>
					<div className="gpi-metrics-wrap">
						<div className="gpi-metrics">
							{ METRIC_ORDER.filter(
								( id ) => data.metrics[ id ]
							).map( ( id ) => (
								<MetricTile
									key={ id }
									id={ id }
									value={ data.metrics[ id ].p75 }
								/>
							) ) }
						</div>
					</div>
				</Card>

				<IssuesCard issues={ data.issues } pages={ data.pages } />
				<PagesCard worst={ data.worst } best={ data.best } />
				<CategoryIssuesCard
					categoryIssues={ data.categoryIssues }
					categories={ data.categories }
				/>
			</div>
		</>
	);
}
