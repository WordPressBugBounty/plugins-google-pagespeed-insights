import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	SearchControl,
	SelectControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../api';
import { useApp, useResource } from '../store';
import { ScoreBadge, Sparkline } from '../components/charts';
import {
	Card,
	Check,
	ConfirmButton,
	ConfirmDialog,
	EmptyState,
	ErrorMessage,
	PageHeader,
	Pagination,
	Pill,
	Skeleton,
	UrlCell,
} from '../components/ui';
import { FilterSelect } from '../components/shared';
import { formatMetric, metricRating, timeAgo, typeLabel } from '../utils';

const COLUMNS = [
	{ id: 'largest-contentful-paint', label: 'LCP' },
	{ id: 'total-blocking-time', label: 'TBT' },
	{ id: 'cumulative-layout-shift', label: 'CLS' },
];

function useDebounced( value, delay = 350 ) {
	const [ debounced, setDebounced ] = useState( value );
	useEffect( () => {
		const timer = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( timer );
	}, [ value, delay ] );
	return debounced;
}

function SortHeader( {
	id,
	label,
	sort,
	setSort,
	className = '',
	firstOrder = 'asc',
} ) {
	const active = sort.orderby === id;
	let next = firstOrder;
	if ( active ) {
		next = sort.order === 'asc' ? 'desc' : 'asc';
	}
	let ariaSort = 'none';
	if ( active ) {
		ariaSort = sort.order === 'asc' ? 'ascending' : 'descending';
	}
	return (
		<th className={ className } aria-sort={ ariaSort }>
			<button
				type="button"
				className={ `gpi-sort${ active ? ' is-active' : '' }` }
				onClick={ () => setSort( { orderby: id, order: next } ) }
			>
				{ label }
				<span
					className={ `dashicons dashicons-arrow-${ active && sort.order === 'desc' ? 'down' : 'up' }-alt2` }
					aria-hidden="true"
				/>
			</button>
		</th>
	);
}

export default function Reports() {
	const {
		app,
		view,
		filter,
		setFilter,
		revision,
		invalidate,
		notify,
		expectScan,
	} = useApp();
	const [ search, setSearch ] = useState( '' );
	const [ sort, setSort ] = useState( { orderby: 'score', order: 'asc' } );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 25 );
	const [ selected, setSelected ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ pendingDelete, setPendingDelete ] = useState( null );
	const query = useDebounced( search );

	useEffect(
		() => setPage( 1 ),
		[ query, filter, view, perPage, sort.orderby, sort.order ]
	);
	useEffect(
		() => setSelected( [] ),
		[ query, filter, view, page, revision ]
	);

	const { data, loading, error, reload } = useResource(
		'/reports',
		{
			strategy: view,
			filter,
			search: query,
			orderby: sort.orderby,
			order: sort.order,
			page,
			per_page: perPage,
		},
		[ revision ]
	);

	const items = data ? data.items : [];
	const allSelected = items.length > 0 && selected.length === items.length;

	const run = ( action, ids ) => {
		setBusy( true );
		return post( '/reports/batch', { action, ids } )
			.then( ( response ) => {
				const messages = {
					recheck: sprintf(
						/* translators: %d: number of reports */
						_n(
							'%d report will be rechecked.',
							'%d reports will be rechecked.',
							response.count,
							'google-pagespeed-insights'
						),
						response.count
					),
					ignore: sprintf(
						/* translators: %d: number of reports */
						_n(
							'%d report moved to Ignored URLs.',
							'%d reports moved to Ignored URLs.',
							response.count,
							'google-pagespeed-insights'
						),
						response.count
					),
					delete: sprintf(
						/* translators: %d: number of reports */
						_n(
							'%d report deleted.',
							'%d reports deleted.',
							response.count,
							'google-pagespeed-insights'
						),
						response.count
					),
				};
				notify( messages[ action ] );
				setSelected( [] );
				invalidate();
				if ( action === 'recheck' && response.count ) {
					expectScan();
				}
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const toggle = ( id ) =>
		setSelected(
			selected.includes( id )
				? selected.filter( ( item ) => item !== id )
				: [ ...selected, id ]
		);

	return (
		<>
			<PageHeader
				title={ __( 'Reports', 'google-pagespeed-insights' ) }
				description={ __(
					'Every page that has been checked, with its latest scores.',
					'google-pagespeed-insights'
				) }
			/>
			<Card padded={ false }>
				<div className="gpi-toolbar">
					<SearchControl
						__nextHasNoMarginBottom
						className="gpi-toolbar__search"
						label={ __(
							'Search URLs',
							'google-pagespeed-insights'
						) }
						placeholder={ __(
							'Search URLs…',
							'google-pagespeed-insights'
						) }
						value={ search }
						onChange={ setSearch }
					/>
					<FilterSelect value={ filter } onChange={ setFilter } />
					<div className="gpi-toolbar__spacer" />
					{ selected.length > 0 && (
						<div className="gpi-bulk">
							<span className="gpi-bulk__count">
								{
									/* translators: %d: number of selected reports */ sprintf(
										_n(
											'%d selected',
											'%d selected',
											selected.length,
											'google-pagespeed-insights'
										),
										selected.length
									)
								}
							</span>
							<Button
								variant="secondary"
								size="compact"
								onClick={ () => run( 'recheck', selected ) }
								disabled={ busy }
							>
								{ __( 'Recheck', 'google-pagespeed-insights' ) }
							</Button>
							<Button
								variant="secondary"
								size="compact"
								onClick={ () => run( 'ignore', selected ) }
								disabled={ busy }
							>
								{ __( 'Ignore', 'google-pagespeed-insights' ) }
							</Button>
							<ConfirmButton
								variant="secondary"
								size="compact"
								disabled={ busy }
								title={ __(
									'Delete reports?',
									'google-pagespeed-insights'
								) }
								message={ __(
									'The selected reports and their history will be deleted. The pages are checked again on the next scan.',
									'google-pagespeed-insights'
								) }
								confirmLabel={ __(
									'Delete',
									'google-pagespeed-insights'
								) }
								onConfirm={ () => run( 'delete', selected ) }
							>
								{ __( 'Delete', 'google-pagespeed-insights' ) }
							</ConfirmButton>
						</div>
					) }
				</div>

				{ error && (
					<ErrorMessage message={ error } onRetry={ reload } />
				) }

				{ ! error && loading && ! data && (
					<div className="gpi-pad">
						<Skeleton rows={ 8 } height={ 28 } />
					</div>
				) }

				{ ! error && data && ! items.length && (
					<EmptyState
						icon="search"
						title={
							query
								? __(
										'No matching reports',
										'google-pagespeed-insights'
									)
								: __(
										'No reports yet',
										'google-pagespeed-insights'
									)
						}
					>
						{ query
							? __(
									'Try another search term or filter.',
									'google-pagespeed-insights'
								)
							: __(
									'Start a scan from the top of the page. Reports appear here as each page is checked.',
									'google-pagespeed-insights'
								) }
					</EmptyState>
				) }

				{ ! error && items.length > 0 && (
					<div
						className={ `gpi-table-wrap${ loading ? ' is-refreshing' : '' }` }
					>
						<table className="gpi-table">
							<thead>
								<tr>
									<th className="gpi-table__check">
										<Check
											label={ __(
												'Select all',
												'google-pagespeed-insights'
											) }
											checked={ allSelected }
											onChange={ () =>
												setSelected(
													allSelected
														? []
														: items.map(
																( item ) =>
																	item.id
															)
												)
											}
										/>
									</th>
									<SortHeader
										id="url"
										label={ __(
											'Page',
											'google-pagespeed-insights'
										) }
										sort={ sort }
										setSort={ setSort }
									/>
									<SortHeader
										id="score"
										label={ __(
											'Performance',
											'google-pagespeed-insights'
										) }
										sort={ sort }
										setSort={ setSort }
										className="gpi-table__score"
									/>
									<th className="gpi-hide-md">
										{ __(
											'Trend',
											'google-pagespeed-insights'
										) }
									</th>
									{ COLUMNS.map( ( column ) => (
										<th
											key={ column.id }
											className="gpi-table__num gpi-hide-sm"
										>
											{ column.label }
										</th>
									) ) }
									<th
										className="gpi-table__num gpi-hide-md"
										title={ __(
											'Accessibility',
											'google-pagespeed-insights'
										) }
									>
										{ __(
											'A11y',
											'google-pagespeed-insights'
										) }
									</th>
									<th className="gpi-table__num gpi-hide-md">
										{ __(
											'SEO',
											'google-pagespeed-insights'
										) }
									</th>
									<SortHeader
										id="last_checked"
										firstOrder="desc"
										label={ __(
											'Checked',
											'google-pagespeed-insights'
										) }
										sort={ sort }
										setSort={ setSort }
										className="gpi-hide-sm"
									/>
									<th className="gpi-table__actions">
										<span className="screen-reader-text">
											{ __(
												'Actions',
												'google-pagespeed-insights'
											) }
										</span>
									</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( item ) => (
									<tr
										key={ item.id }
										className={
											selected.includes( item.id )
												? 'is-selected'
												: ''
										}
									>
										<td className="gpi-table__check">
											<Check
												label={ item.url }
												checked={ selected.includes(
													item.id
												) }
												onChange={ () =>
													toggle( item.id )
												}
											/>
										</td>
										<td>
											<UrlCell
												url={ item.url }
												href={ `#/reports/${ item.id }` }
											/>
											<Pill>
												{ typeLabel(
													item.type,
													app.filters
												) }
											</Pill>
										</td>
										<td className="gpi-table__score">
											<ScoreBadge score={ item.score } />
										</td>
										<td className="gpi-hide-md">
											<Sparkline values={ item.trend } />
										</td>
										{ COLUMNS.map( ( column ) => {
											const metric =
												item.metrics[ column.id ];
											const value = metric
												? metric.value
												: null;
											return (
												<td
													key={ column.id }
													className={ `gpi-table__num gpi-hide-sm gpi-text--${ metricRating( column.id, value ) }` }
												>
													{ metric
														? formatMetric(
																column.id,
																value
															)
														: '–' }
												</td>
											);
										} ) }
										<td className="gpi-table__num gpi-hide-md">
											<ScoreBadge
												score={
													item.categories
														.accessibility ?? null
												}
												size="sm"
											/>
										</td>
										<td className="gpi-table__num gpi-hide-md">
											<ScoreBadge
												score={
													item.categories.seo ?? null
												}
												size="sm"
											/>
										</td>
										<td className="gpi-hide-sm gpi-muted">
											{ timeAgo( item.last_checked ) }
										</td>
										<td className="gpi-table__actions">
											<DropdownMenu
												icon="ellipsis"
												label={ __(
													'Actions',
													'google-pagespeed-insights'
												) }
												toggleProps={ {
													size: 'compact',
												} }
											>
												{ ( { onClose } ) => (
													<MenuGroup>
														<MenuItem
															icon="visibility"
															onClick={ () => {
																window.location.hash = `#/reports/${ item.id }`;
																onClose();
															} }
														>
															{ __(
																'View report',
																'google-pagespeed-insights'
															) }
														</MenuItem>
														<MenuItem
															icon="external"
															href={ item.url }
															target="_blank"
															rel="noopener noreferrer"
															onClick={ onClose }
														>
															{ __(
																'Open page',
																'google-pagespeed-insights'
															) }
														</MenuItem>
														<MenuItem
															icon="update"
															onClick={ () => {
																run(
																	'recheck',
																	[ item.id ]
																);
																onClose();
															} }
														>
															{ __(
																'Recheck',
																'google-pagespeed-insights'
															) }
														</MenuItem>
														<MenuItem
															icon="hidden"
															onClick={ () => {
																run( 'ignore', [
																	item.id,
																] );
																onClose();
															} }
														>
															{ __(
																'Ignore',
																'google-pagespeed-insights'
															) }
														</MenuItem>
														<MenuItem
															icon="trash"
															isDestructive
															onClick={ () => {
																setPendingDelete(
																	item
																);
																onClose();
															} }
														>
															{ __(
																'Delete',
																'google-pagespeed-insights'
															) }
														</MenuItem>
													</MenuGroup>
												) }
											</DropdownMenu>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }

				{ data && data.total > 0 && (
					<div className="gpi-tablefoot">
						<div className="gpi-perpage">
							<span aria-hidden="true">
								{ __(
									'Rows per page',
									'google-pagespeed-insights'
								) }
							</span>
							<SelectControl
								__nextHasNoMarginBottom
								label={ __(
									'Rows per page',
									'google-pagespeed-insights'
								) }
								hideLabelFromVision
								value={ perPage }
								options={ [ 25, 50, 100, 200 ].map(
									( value ) => ( {
										value,
										label: String( value ),
									} )
								) }
								onChange={ ( value ) =>
									setPerPage( parseInt( value, 10 ) )
								}
							/>
						</div>
						<Pagination
							page={ page }
							pages={ data.pages }
							total={ data.total }
							onChange={ setPage }
						/>
					</div>
				) }
			</Card>
			{ pendingDelete && (
				<ConfirmDialog
					title={ __(
						'Delete report?',
						'google-pagespeed-insights'
					) }
					message={ sprintf(
						/* translators: %s: page URL */
						__(
							'The report and history of %s will be deleted. The page is checked again on the next scan.',
							'google-pagespeed-insights'
						),
						pendingDelete.url
					) }
					confirmLabel={ __( 'Delete', 'google-pagespeed-insights' ) }
					onConfirm={ () => run( 'delete', [ pendingDelete.id ] ) }
					onClose={ () => setPendingDelete( null ) }
				/>
			) }
		</>
	);
}
