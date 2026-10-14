import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../api';
import { useApp, useResource } from '../store';
import {
	Card,
	Check,
	ConfirmButton,
	EmptyState,
	ErrorMessage,
	PageHeader,
	Pill,
	Skeleton,
	UrlCell,
} from '../components/ui';
import { typeLabel } from '../utils';

export default function Ignored() {
	const { app, revision, invalidate, notify, refresh, expectScan } = useApp();
	const { data, loading, error, reload } = useResource( '/ignored', {}, [
		revision,
	] );
	const [ selected, setSelected ] = useState( [] );
	const list = data || [];

	const act = ( action, ids ) =>
		post( `/ignored/${ action }`, { ids } )
			.then( ( response ) => {
				notify(
					action === 'reactivate'
						? sprintf(
								/* translators: %d: number of URLs */ _n(
									'%d URL reactivated and is being checked again.',
									'%d URLs reactivated and are being checked again.',
									response.count,
									'google-pagespeed-insights'
								),
								response.count
							)
						: sprintf(
								/* translators: %d: number of URLs */ _n(
									'%d URL removed.',
									'%d URLs removed.',
									response.count,
									'google-pagespeed-insights'
								),
								response.count
							)
				);
				setSelected( [] );
				invalidate();
				refresh().then(
					() =>
						action === 'reactivate' &&
						response.count &&
						expectScan()
				);
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) );

	return (
		<>
			<PageHeader
				title={ __( 'Ignored URLs', 'google-pagespeed-insights' ) }
				description={ __(
					'Pages that are skipped during scans. URLs that Google could not load are added here automatically.',
					'google-pagespeed-insights'
				) }
				actions={
					selected.length > 0 && (
						<>
							<Button
								variant="secondary"
								onClick={ () => act( 'reactivate', selected ) }
							>
								{ __(
									'Reactivate',
									'google-pagespeed-insights'
								) }
							</Button>
							<ConfirmButton
								variant="secondary"
								title={ __(
									'Remove from the list?',
									'google-pagespeed-insights'
								) }
								message={ __(
									'The URLs are removed from this list. WordPress pages will be checked again on the next scan.',
									'google-pagespeed-insights'
								) }
								confirmLabel={ __(
									'Remove',
									'google-pagespeed-insights'
								) }
								onConfirm={ () => act( 'delete', selected ) }
							>
								{ __( 'Remove', 'google-pagespeed-insights' ) }
							</ConfirmButton>
						</>
					)
				}
			/>
			<Card padded={ false }>
				{ error && (
					<ErrorMessage message={ error } onRetry={ reload } />
				) }
				{ loading && ! data && (
					<div className="gpi-pad">
						<Skeleton rows={ 4 } />
					</div>
				) }
				{ data && ! list.length && (
					<EmptyState
						icon="hidden"
						title={ __(
							'Nothing is ignored',
							'google-pagespeed-insights'
						) }
					>
						{ __(
							'Use “Ignore” on a report to skip a page in future scans.',
							'google-pagespeed-insights'
						) }
					</EmptyState>
				) }
				{ list.length > 0 && (
					<table className="gpi-table">
						<thead>
							<tr>
								<th className="gpi-table__check">
									<Check
										label={ __(
											'Select all',
											'google-pagespeed-insights'
										) }
										checked={
											selected.length === list.length
										}
										onChange={ () =>
											setSelected(
												selected.length === list.length
													? []
													: list.map(
															( item ) => item.id
														)
											)
										}
									/>
								</th>
								<th>
									{ __( 'URL', 'google-pagespeed-insights' ) }
								</th>
								<th>
									{ __(
										'Type',
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
											label={ item.url }
											checked={ selected.includes(
												item.id
											) }
											onChange={ () =>
												setSelected(
													selected.includes( item.id )
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
										<UrlCell url={ item.url } />
									</td>
									<td>
										<Pill>
											{ typeLabel(
												item.type,
												app.filters
											) }
										</Pill>
									</td>
									<td className="gpi-table__actions">
										<Button
											size="compact"
											variant="tertiary"
											onClick={ () =>
												act( 'reactivate', [ item.id ] )
											}
										>
											{ __(
												'Reactivate',
												'google-pagespeed-insights'
											) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</Card>
		</>
	);
}
