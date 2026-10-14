import {
	Button,
	FormFileUpload,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { post, upload, errorMessage } from '../api';
import { useApp, useResource } from '../store';
import { parseCsv, urlsFromCsv } from '../utils';
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

function addedMessage( result ) {
	const added = sprintf(
		/* translators: %d: number of URLs */ _n(
			'%d URL added.',
			'%d URLs added.',
			result.added,
			'google-pagespeed-insights'
		),
		result.added
	);
	const parts = [ added ];
	if ( result.skipped ) {
		parts.push(
			sprintf(
				/* translators: %d: number of URLs */ _n(
					'%d was already tracked.',
					'%d were already tracked.',
					result.skipped,
					'google-pagespeed-insights'
				),
				result.skipped
			)
		);
	}
	if ( result.invalid ) {
		parts.push(
			sprintf(
				/* translators: %d: number of URLs */ _n(
					'%d was not a valid public URL.',
					'%d were not valid public URLs.',
					result.invalid,
					'google-pagespeed-insights'
				),
				result.invalid
			)
		);
	}
	return parts.join( ' ' );
}

// Matches the maxItems of the /custom-urls route.
const BATCH_SIZE = 500;
const CSV_MAX_URLS = 5000;
const CSV_MAX_BYTES = 2 * 1024 * 1024;

/**
 * Sends the URLs of each label group in batches and adds up the results.
 * @param groups
 * @param defaultLabel
 */
async function addGroups( groups, defaultLabel ) {
	const total = { added: 0, skipped: 0, invalid: 0 };
	for ( const [ label, urls ] of Object.entries( groups ) ) {
		for ( let i = 0; i < urls.length; i += BATCH_SIZE ) {
			const result = await post( '/custom-urls', {
				urls: urls.slice( i, i + BATCH_SIZE ),
				label: label || defaultLabel,
			} );
			total.added += result.added;
			total.skipped += result.skipped;
			total.invalid += result.invalid || 0;
		}
	}
	return total;
}

function AddUrls() {
	const { notify, invalidate, refresh, expectScan } = useApp();
	const [ urls, setUrls ] = useState( '' );
	const [ label, setLabel ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const lines = urls
		.split( /\s+/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean );

	const done = ( result ) => {
		notify( addedMessage( result ), result.added ? 'success' : 'info' );
		invalidate();
		// After refresh(), so the reloaded status does not hide the progress bar.
		refresh().then( () => result.added && expectScan() );
	};

	const add = () => {
		setBusy( true );
		post( '/custom-urls', { urls: lines, label } )
			.then( ( result ) => {
				done( result );
				setUrls( '' );
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => setBusy( false ) );
	};

	const importSitemap = ( event ) => {
		const file = event.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const data = new window.FormData();
		data.append( 'sitemap', file );
		data.append( 'label', label );
		setBusy( true );
		upload( '/custom-urls/sitemap', data )
			.then( done )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => {
				setBusy( false );
				event.target.value = '';
			} );
	};

	const importCsv = ( event ) => {
		const input = event.target;
		const file = input.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const fail = ( message ) => {
			notify( message, 'error' );
			input.value = '';
		};
		if ( file.size > CSV_MAX_BYTES ) {
			fail(
				__(
					'The CSV file is too large. The limit is 2 MB.',
					'google-pagespeed-insights'
				)
			);
			return;
		}
		file.text()
			.then( ( text ) => {
				const groups = urlsFromCsv( parseCsv( text ) );
				const count = Object.values( groups ).reduce(
					( sum, list ) => sum + list.length,
					0
				);
				if ( ! count ) {
					throw new Error(
						__(
							'No URLs were found in the CSV file. URLs must start with http:// or https://.',
							'google-pagespeed-insights'
						)
					);
				}
				if ( count > CSV_MAX_URLS ) {
					throw new Error(
						sprintf(
							/* translators: %d: maximum number of URLs */
							__(
								'The CSV file has too many URLs. Import up to %d at a time.',
								'google-pagespeed-insights'
							),
							CSV_MAX_URLS
						)
					);
				}
				setBusy( true );
				return addGroups( groups, label ).then( done );
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => {
				setBusy( false );
				input.value = '';
			} );
	};

	return (
		<Card
			title={ __( 'Add URLs', 'google-pagespeed-insights' ) }
			description={ __(
				'Track any public URL, including pages that are not part of WordPress or live on another site.',
				'google-pagespeed-insights'
			) }
			className="gpi-span-5"
		>
			<div className="gpi-form">
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __(
						'URLs (one per line)',
						'google-pagespeed-insights'
					) }
					placeholder={
						'https://example.com/\nhttps://example.com/pricing/'
					}
					rows={ 6 }
					value={ urls }
					onChange={ setUrls }
				/>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Label', 'google-pagespeed-insights' ) }
					help={ __(
						'Used to group and filter these reports. Lowercase letters, numbers and hyphens; up to 40 characters.',
						'google-pagespeed-insights'
					) }
					placeholder="landing-pages"
					value={ label }
					maxLength={ 40 }
					onChange={ setLabel }
				/>
				<div className="gpi-form__actions">
					<Button
						variant="primary"
						onClick={ add }
						isBusy={ busy }
						disabled={ busy || ! lines.length }
					>
						{ lines.length > 1
							? sprintf(
									/* translators: %d: number of URLs */ __(
										'Add %d URLs',
										'google-pagespeed-insights'
									),
									lines.length
								)
							: __( 'Add URL', 'google-pagespeed-insights' ) }
					</Button>
					<FormFileUpload
						accept=".xml,text/xml,application/xml"
						onChange={ importSitemap }
						render={ ( { openFileDialog } ) => (
							<Button
								variant="secondary"
								icon="upload"
								onClick={ openFileDialog }
								disabled={ busy }
							>
								{ __(
									'Import XML sitemap',
									'google-pagespeed-insights'
								) }
							</Button>
						) }
					/>
					<FormFileUpload
						accept=".csv,.txt,text/csv,text/plain"
						onChange={ importCsv }
						render={ ( { openFileDialog } ) => (
							<Button
								variant="secondary"
								icon="media-spreadsheet"
								onClick={ openFileDialog }
								disabled={ busy }
							>
								{ __(
									'Import CSV',
									'google-pagespeed-insights'
								) }
							</Button>
						) }
					/>
				</div>
				<ul className="gpi-muted gpi-small gpi-hints">
					<li>
						{ __(
							'Sitemaps must follow the sitemaps.org format. Sitemap index files are not supported; upload each sitemap file separately.',
							'google-pagespeed-insights'
						) }
					</li>
					<li>
						{ __(
							'CSV files: the first http(s) URL in each row is imported, so exports from Search Console or crawlers work as they are. An optional "label" column sets the label per row.',
							'google-pagespeed-insights'
						) }
					</li>
				</ul>
			</div>
		</Card>
	);
}

export default function CustomUrls() {
	const { revision, invalidate, notify, refresh } = useApp();
	const { data, loading, error, reload } = useResource( '/custom-urls', {}, [
		revision,
	] );
	const [ selected, setSelected ] = useState( [] );
	const list = data || [];

	const remove = ( ids ) =>
		post( '/custom-urls/delete', { ids } )
			.then( ( response ) => {
				notify(
					sprintf(
						/* translators: %d: number of URLs */ _n(
							'%d URL deleted.',
							'%d URLs deleted.',
							response.count,
							'google-pagespeed-insights'
						),
						response.count
					)
				);
				setSelected( [] );
				invalidate();
				refresh();
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) );

	return (
		<>
			<PageHeader
				title={ __( 'Custom URLs', 'google-pagespeed-insights' ) }
				description={ __(
					'Extra URLs that are checked together with your posts and pages.',
					'google-pagespeed-insights'
				) }
			/>
			<div className="gpi-grid">
				<AddUrls />
				<Card
					title={ __( 'Tracked URLs', 'google-pagespeed-insights' ) }
					className="gpi-span-7"
					padded={ false }
					actions={
						selected.length > 0 && (
							<ConfirmButton
								variant="secondary"
								size="compact"
								title={ __(
									'Delete URLs?',
									'google-pagespeed-insights'
								) }
								message={ __(
									'The selected URLs and their reports will be deleted.',
									'google-pagespeed-insights'
								) }
								confirmLabel={ __(
									'Delete',
									'google-pagespeed-insights'
								) }
								onConfirm={ () => remove( selected ) }
							>
								{
									/* translators: %d: number of URLs */ sprintf(
										__(
											'Delete %d',
											'google-pagespeed-insights'
										),
										selected.length
									)
								}
							</ConfirmButton>
						)
					}
				>
					{ error && (
						<ErrorMessage message={ error } onRetry={ reload } />
					) }
					{ loading && ! data && (
						<div className="gpi-pad">
							<Skeleton rows={ 5 } />
						</div>
					) }
					{ data && ! list.length && (
						<EmptyState
							icon="admin-links"
							title={ __(
								'No custom URLs',
								'google-pagespeed-insights'
							) }
						>
							{ __(
								'URLs you add appear here.',
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
													selected.length ===
														list.length
														? []
														: list.map(
																( item ) =>
																	item.id
															)
												)
											}
										/>
									</th>
									<th>
										{ __(
											'URL',
											'google-pagespeed-insights'
										) }
									</th>
									<th>
										{ __(
											'Label',
											'google-pagespeed-insights'
										) }
									</th>
									<th>
										{ __(
											'Report',
											'google-pagespeed-insights'
										) }
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
											<UrlCell url={ item.url } />
										</td>
										<td>
											<Pill>{ item.label }</Pill>
										</td>
										<td>
											{ item.reportId ? (
												<a
													href={ `#/reports/${ item.reportId }` }
												>
													{ __(
														'View report',
														'google-pagespeed-insights'
													) }
												</a>
											) : (
												<span className="gpi-muted">
													{ __(
														'Not checked yet',
														'google-pagespeed-insights'
													) }
												</span>
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</Card>
			</div>
		</>
	);
}
