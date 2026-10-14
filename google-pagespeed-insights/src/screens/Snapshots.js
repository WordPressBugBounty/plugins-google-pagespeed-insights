import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../api';
import { navigate, useApp, useResource } from '../store';
import { LineChart, ScoreBadge, historyDateLabel } from '../components/charts';
import {
	Card,
	Check,
	ConfirmButton,
	EmptyState,
	ErrorMessage,
	PageHeader,
	Pill,
	Skeleton,
} from '../components/ui';
import { formatDate, typeLabel } from '../utils';

export default function Snapshots() {
	const { app, revision, invalidate, notify } = useApp();
	const { data, loading, error, reload } = useResource( '/snapshots', {}, [
		revision,
	] );
	const [ selected, setSelected ] = useState( [] );

	const remove = ( ids ) =>
		post( '/snapshots/delete', { ids } )
			.then( ( response ) => {
				notify(
					sprintf(
						/* translators: %d: number of snapshots */ _n(
							'%d snapshot deleted.',
							'%d snapshots deleted.',
							response.count,
							'google-pagespeed-insights'
						),
						response.count
					)
				);
				setSelected( [] );
				invalidate();
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) );

	const list = data || [];
	const canCompare =
		selected.length === 2 &&
		list
			.filter( ( item ) => selected.includes( item.id ) )
			.every(
				( item, index, all ) => item.strategy === all[ 0 ].strategy
			);
	const trend = [ ...list ]
		.reverse()
		.filter( ( item ) => item.score !== null )
		.map( ( item ) => ( { time: item.time, score: item.score } ) );

	return (
		<>
			<PageHeader
				title={ __( 'Snapshots', 'google-pagespeed-insights' ) }
				description={ __(
					'Saved copies of the overview. Save one before a change (a new theme, a caching plugin) and compare it with one taken after.',
					'google-pagespeed-insights'
				) }
				actions={
					<>
						<Button
							variant="secondary"
							disabled={ ! canCompare }
							onClick={ () =>
								navigate(
									`snapshots/${ selected[ 0 ] }/compare/${ selected[ 1 ] }`
								)
							}
							title={ __(
								'Select two snapshots of the same report type to compare them',
								'google-pagespeed-insights'
							) }
						>
							{ __(
								'Compare selected',
								'google-pagespeed-insights'
							) }
						</Button>
						{ selected.length > 0 && (
							<ConfirmButton
								variant="secondary"
								title={ __(
									'Delete snapshots?',
									'google-pagespeed-insights'
								) }
								message={ __(
									'The selected snapshots will be deleted permanently.',
									'google-pagespeed-insights'
								) }
								confirmLabel={ __(
									'Delete',
									'google-pagespeed-insights'
								) }
								onConfirm={ () => remove( selected ) }
							>
								{ __( 'Delete', 'google-pagespeed-insights' ) }
							</ConfirmButton>
						) }
					</>
				}
			/>

			{ error && <ErrorMessage message={ error } onRetry={ reload } /> }
			{ loading && ! data && (
				<Card>
					<Skeleton rows={ 5 } height={ 24 } />
				</Card>
			) }

			{ data && ! list.length && (
				<Card>
					<EmptyState
						icon="camera"
						title={ __(
							'No snapshots yet',
							'google-pagespeed-insights'
						) }
						action={
							<Button
								variant="primary"
								onClick={ () => navigate( '' ) }
							>
								{ __(
									'Go to the overview',
									'google-pagespeed-insights'
								) }
							</Button>
						}
					>
						{ __(
							'Use “Save snapshot” on the overview to keep a copy of your current results.',
							'google-pagespeed-insights'
						) }
					</EmptyState>
				</Card>
			) }

			{ list.length > 0 && (
				<div className="gpi-grid">
					{ trend.length > 1 && (
						<Card
							title={ __(
								'Average Performance by snapshot',
								'google-pagespeed-insights'
							) }
							className="gpi-span-12"
						>
							<LineChart
								series={ [
									{
										key: 'score',
										label: __(
											'Performance',
											'google-pagespeed-insights'
										),
										color: 'var(--gpi-c-performance)',
										area: true,
									},
								] }
								data={ trend }
								xKey="time"
								formatX={ historyDateLabel }
								height={ 200 }
							/>
						</Card>
					) }
					<Card padded={ false } className="gpi-span-12">
						<table className="gpi-table">
							<thead>
								<tr>
									<th className="gpi-table__check">
										<span className="screen-reader-text">
											{ __(
												'Select',
												'google-pagespeed-insights'
											) }
										</span>
									</th>
									<th>
										{ __(
											'Date',
											'google-pagespeed-insights'
										) }
									</th>
									<th className="gpi-table__score">
										{ __(
											'Performance',
											'google-pagespeed-insights'
										) }
									</th>
									<th>
										{ __(
											'Reports',
											'google-pagespeed-insights'
										) }
									</th>
									<th>
										{ __(
											'Type',
											'google-pagespeed-insights'
										) }
									</th>
									<th>
										{ __(
											'Note',
											'google-pagespeed-insights'
										) }
									</th>
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
								{ list.map( ( item ) => (
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
												label={ formatDate(
													item.time
												) }
												checked={ selected.includes(
													item.id
												) }
												onChange={ () =>
													setSelected(
														selected.includes(
															item.id
														)
															? selected.filter(
																	( id ) =>
																		id !==
																		item.id
																)
															: [
																	...selected,
																	item.id,
																]
													)
												}
											/>
										</td>
										<td>
											<a
												className="gpi-strong-link"
												href={ `#/snapshots/${ item.id }` }
											>
												{ formatDate( item.time ) }
											</a>
										</td>
										<td className="gpi-table__score">
											<ScoreBadge score={ item.score } />
										</td>
										<td>{ item.pages ?? '–' }</td>
										<td>
											<Pill>
												{ item.strategy === 'mobile'
													? __(
															'Mobile',
															'google-pagespeed-insights'
														)
													: __(
															'Desktop',
															'google-pagespeed-insights'
														) }
											</Pill>{ ' ' }
											<Pill>
												{ typeLabel(
													item.filter,
													app.filters
												) === 'all'
													? __(
															'All reports',
															'google-pagespeed-insights'
														)
													: typeLabel(
															item.filter,
															app.filters
														) }
											</Pill>
										</td>
										<td className="gpi-muted">
											{ item.comment || '–' }
										</td>
										<td className="gpi-table__actions">
											<Button
												size="compact"
												variant="tertiary"
												href={ `#/snapshots/${ item.id }` }
											>
												{ __(
													'View',
													'google-pagespeed-insights'
												) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</Card>
				</div>
			) }
		</>
	);
}
