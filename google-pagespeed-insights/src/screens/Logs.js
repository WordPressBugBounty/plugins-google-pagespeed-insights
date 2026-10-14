import { __ } from '@wordpress/i18n';
import { navigate, useApp, useResource } from '../store';
import {
	Card,
	EmptyState,
	ErrorMessage,
	PageHeader,
	Pill,
	Skeleton,
	UrlCell,
} from '../components/ui';
import { formatDate } from '../utils';

export default function Logs() {
	const { app, revision } = useApp();
	const { data, loading, error, reload } = useResource( '/logs', {}, [
		revision,
	] );
	const list = data || [];

	return (
		<>
			<PageHeader
				back={ {
					href: '#/settings',
					label: __( 'Settings', 'google-pagespeed-insights' ),
				} }
				title={ __( 'API error logs', 'google-pagespeed-insights' ) }
				description={ __(
					'Errors returned by the Google PageSpeed API in the last 7 days.',
					'google-pagespeed-insights'
				) }
			/>
			{ ! app.logging && (
				<div className="gpi-banner gpi-banner--warning">
					<span
						className="dashicons dashicons-info-outline"
						aria-hidden="true"
					/>
					{ __(
						'Error logging is turned off, so new errors are not recorded.',
						'google-pagespeed-insights'
					) }{ ' ' }
					<a
						href="#/settings"
						onClick={ ( event ) => {
							event.preventDefault();
							navigate( 'settings' );
						} }
					>
						{ __(
							'Turn it on in the Settings',
							'google-pagespeed-insights'
						) }
					</a>
				</div>
			) }
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
						icon="yes-alt"
						title={ __(
							'No errors logged',
							'google-pagespeed-insights'
						) }
					>
						{ __(
							'The PageSpeed API has not returned any errors recently.',
							'google-pagespeed-insights'
						) }
					</EmptyState>
				) }
				{ list.length > 0 && (
					<table className="gpi-table">
						<thead>
							<tr>
								<th>
									{ __( 'URL', 'google-pagespeed-insights' ) }
								</th>
								<th>
									{ __(
										'Error',
										'google-pagespeed-insights'
									) }
								</th>
								<th>
									{ __(
										'Report type',
										'google-pagespeed-insights'
									) }
								</th>
								<th>
									{ __(
										'Time',
										'google-pagespeed-insights'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ list.map( ( log, index ) => (
								<tr key={ index }>
									<td>
										<UrlCell url={ log.url } />
									</td>
									<td>
										{ log.errors.map(
											( item, errorIndex ) => (
												<div
													key={ errorIndex }
													className="gpi-logerror"
												>
													{ item.reason && (
														<code>
															{ item.reason }
														</code>
													) }
													<span>
														{ item.message }
													</span>
												</div>
											)
										) }
									</td>
									<td>
										<Pill>{ log.strategy }</Pill>
									</td>
									<td className="gpi-muted">
										{ formatDate( log.time ) }
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
