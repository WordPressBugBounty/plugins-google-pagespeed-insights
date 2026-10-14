import {
	Button,
	CheckboxControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { post, errorMessage } from '../api';
import { navigate, useApp, useResource } from '../store';
import {
	Card,
	ConfirmButton,
	ErrorMessage,
	ExternalLink,
	PageHeader,
	Pill,
	Skeleton,
} from '../components/ui';

const LANGUAGES = [
	[ 'ar', 'Arabic' ],
	[ 'bg', 'Bulgarian' ],
	[ 'ca', 'Catalan' ],
	[ 'zh_TW', 'Traditional Chinese (Taiwan)' ],
	[ 'zh_CN', 'Simplified Chinese' ],
	[ 'hr', 'Croatian' ],
	[ 'cs', 'Czech' ],
	[ 'da', 'Danish' ],
	[ 'nl', 'Dutch' ],
	[ 'en_US', 'English' ],
	[ 'en_GB', 'English UK' ],
	[ 'fil', 'Filipino' ],
	[ 'fi', 'Finnish' ],
	[ 'fr', 'French' ],
	[ 'de', 'German' ],
	[ 'el', 'Greek' ],
	[ 'iw', 'Hebrew' ],
	[ 'hi', 'Hindi' ],
	[ 'hu', 'Hungarian' ],
	[ 'id', 'Indonesian' ],
	[ 'it', 'Italian' ],
	[ 'ja', 'Japanese' ],
	[ 'ko', 'Korean' ],
	[ 'lv', 'Latvian' ],
	[ 'lt', 'Lithuanian' ],
	[ 'no', 'Norwegian' ],
	[ 'pl', 'Polish' ],
	[ 'pt_BR', 'Portuguese (Brazilian)' ],
	[ 'pt_PT', 'Portuguese (Portugal)' ],
	[ 'ro', 'Romanian' ],
	[ 'ru', 'Russian' ],
	[ 'sr', 'Serbian' ],
	[ 'sk', 'Slovakian' ],
	[ 'sl', 'Slovenian' ],
	[ 'es', 'Spanish' ],
	[ 'sv', 'Swedish' ],
	[ 'th', 'Thai' ],
	[ 'tr', 'Turkish' ],
	[ 'uk', 'Ukrainian' ],
	[ 'vi', 'Vietnamese' ],
];

function Row( { title, description, children } ) {
	return (
		<div className="gpi-setting">
			<div className="gpi-setting__info">
				<div className="gpi-setting__title">{ title }</div>
				{ description && (
					<div className="gpi-setting__description">
						{ description }
					</div>
				) }
			</div>
			<div className="gpi-setting__control">{ children }</div>
		</div>
	);
}

function StrategyCards( { value, onChange } ) {
	const options = [
		{
			value: 'desktop',
			icon: 'desktop',
			label: __( 'Desktop', 'google-pagespeed-insights' ),
		},
		{
			value: 'mobile',
			icon: 'smartphone',
			label: __( 'Mobile', 'google-pagespeed-insights' ),
		},
		{
			value: 'both',
			icon: 'image-flip-horizontal',
			label: __( 'Both', 'google-pagespeed-insights' ),
		},
	];
	return (
		<div
			className="gpi-choice"
			role="radiogroup"
			aria-label={ __( 'Report type', 'google-pagespeed-insights' ) }
		>
			{ options.map( ( option ) => (
				<button
					key={ option.value }
					type="button"
					role="radio"
					aria-checked={ value === option.value }
					className={ value === option.value ? 'is-active' : '' }
					onClick={ () => onChange( option.value ) }
				>
					<span
						className={ `dashicons dashicons-${ option.icon }` }
						aria-hidden="true"
					/>
					{ option.label }
				</button>
			) ) }
		</div>
	);
}

export default function Settings() {
	const { running, notify, refresh, invalidate } = useApp();
	const { data, loading, error, reload } = useResource( '/settings', {} );
	const [ values, setValues ] = useState( null );
	const [ showKey, setShowKey ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ keyCheck, setKeyCheck ] = useState( null );
	const [ checkingKey, setCheckingKey ] = useState( false );

	useEffect( () => {
		if ( data ) {
			setValues( data );
		}
	}, [ data ] );

	if ( error ) {
		return <ErrorMessage message={ error } onRetry={ reload } />;
	}

	if ( loading || ! values ) {
		return (
			<>
				<PageHeader
					title={ __( 'Settings', 'google-pagespeed-insights' ) }
				/>
				<Card>
					<Skeleton rows={ 8 } height={ 24 } />
				</Card>
			</>
		);
	}

	const set = ( key ) => ( value ) =>
		setValues( { ...values, [ key ]: value } );
	const dirty = JSON.stringify( values ) !== JSON.stringify( data );

	// A new API key must pass "Check key" before the settings can be saved.
	const keyValue = ( values.google_developer_key || '' ).trim();
	const keyResult =
		keyCheck && keyCheck.key === keyValue ? keyCheck.result : null;
	const keyNeedsCheck =
		keyValue !== '' &&
		keyValue !== ( data.google_developer_key || '' ).trim() &&
		! ( keyResult && keyResult.valid );

	const checkKey = () => {
		setCheckingKey( true );
		post( '/settings/check-key', { key: keyValue } )
			.then( ( result ) => setKeyCheck( { key: keyValue, result } ) )
			.catch( ( e ) =>
				setKeyCheck( {
					key: keyValue,
					result: {
						status: 'error',
						valid: false,
						message: errorMessage( e ),
					},
				} )
			)
			.finally( () => setCheckingKey( false ) );
	};

	let saveText = __( 'All changes saved.', 'google-pagespeed-insights' );
	if ( dirty ) {
		saveText = keyNeedsCheck
			? __(
					'Check the new API key before saving.',
					'google-pagespeed-insights'
				)
			: __( 'You have unsaved changes.', 'google-pagespeed-insights' );
	}

	const save = () => {
		setSaving( true );
		post( '/settings', values )
			.then( ( saved ) => {
				setValues( saved );
				notify( __( 'Settings saved.', 'google-pagespeed-insights' ) );
				reload();
				refresh();
				invalidate();
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) )
			.finally( () => setSaving( false ) );
	};

	const purge = ( type ) =>
		post( '/settings/purge', { type } )
			.then( () => {
				notify(
					type === 'everything'
						? __(
								'All plugin data was deleted.',
								'google-pagespeed-insights'
							)
						: __(
								'All reports were deleted.',
								'google-pagespeed-insights'
							)
				);
				refresh();
				invalidate();
			} )
			.catch( ( e ) => notify( errorMessage( e ), 'error' ) );

	const seconds = ( value ) =>
		value === 0
			? __( 'No limit', 'google-pagespeed-insights' )
			: sprintf(
					/* translators: %d: seconds */ __(
						'%d seconds',
						'google-pagespeed-insights'
					),
					value
				);

	return (
		<>
			<PageHeader
				title={ __( 'Settings', 'google-pagespeed-insights' ) }
				description={ __(
					'Connect your Google API key and choose what gets checked.',
					'google-pagespeed-insights'
				) }
			/>
			<div className="gpi-stack">
				{ running && (
					<div className="gpi-banner gpi-banner--warning">
						<span
							className="dashicons dashicons-lock"
							aria-hidden="true"
						/>
						{ __(
							'Settings can’t be saved while a scan is running.',
							'google-pagespeed-insights'
						) }
					</div>
				) }

				<Card
					title={ __(
						'Google PageSpeed API',
						'google-pagespeed-insights'
					) }
				>
					<Row
						title={ __( 'API key', 'google-pagespeed-insights' ) }
						description={
							<>
								{ __(
									'Required. The key is free and lets this site use the PageSpeed Insights API.',
									'google-pagespeed-insights'
								) }{ ' ' }
								<span className="gpi-links">
									<ExternalLink href="https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com">
										{ __(
											'Get an API key',
											'google-pagespeed-insights'
										) }
									</ExternalLink>
									<ExternalLink href="https://plugstack.dev/docs/google-pagespeed-insights/google-api-key/">
										{ __(
											'Step-by-step guide',
											'google-pagespeed-insights'
										) }
									</ExternalLink>
								</span>
							</>
						}
					>
						<div className="gpi-keyfield">
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'API key',
									'google-pagespeed-insights'
								) }
								hideLabelFromVision
								type={ showKey ? 'text' : 'password' }
								autoComplete="off"
								spellCheck={ false }
								value={ values.google_developer_key }
								onChange={ set( 'google_developer_key' ) }
							/>
							<Button
								variant="secondary"
								__next40pxDefaultSize
								icon={ showKey ? 'hidden' : 'visibility' }
								label={
									showKey
										? __(
												'Hide key',
												'google-pagespeed-insights'
											)
										: __(
												'Show key',
												'google-pagespeed-insights'
											)
								}
								onClick={ () => setShowKey( ! showKey ) }
							/>
							<Button
								variant={
									keyNeedsCheck ? 'primary' : 'secondary'
								}
								__next40pxDefaultSize
								onClick={ checkKey }
								isBusy={ checkingKey }
								disabled={ checkingKey || keyValue === '' }
							>
								{ __(
									'Check key',
									'google-pagespeed-insights'
								) }
							</Button>
						</div>
						{ keyResult && (
							<p
								className={ `gpi-keystatus ${
									keyResult.valid ? 'is-valid' : 'is-invalid'
								}` }
								role="status"
							>
								<span
									className={ `dashicons dashicons-${
										keyResult.valid ? 'yes-alt' : 'warning'
									}` }
									aria-hidden="true"
								/>
								{ keyResult.message }
							</p>
						) }
						{ ! keyResult && keyNeedsCheck && (
							<p className="gpi-keystatus" role="status">
								{ __(
									'Check the new key before saving.',
									'google-pagespeed-insights'
								) }
							</p>
						) }
					</Row>
					<Row
						title={ __(
							'Report type',
							'google-pagespeed-insights'
						) }
						description={ __(
							'“Both” runs two checks per page and uses twice as many API requests.',
							'google-pagespeed-insights'
						) }
					>
						<StrategyCards
							value={ values.strategy }
							onChange={ set( 'strategy' ) }
						/>
					</Row>
					<Row
						title={ __(
							'Report language',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Language of the audit titles and descriptions returned by Google. Recheck pages after changing it.',
							'google-pagespeed-insights'
						) }
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Report language',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ values.response_language }
							options={ LANGUAGES.map( ( [ value, label ] ) => ( {
								value,
								label,
							} ) ) }
							onChange={ set( 'response_language' ) }
						/>
					</Row>
					<Row
						title={ __(
							'Page screenshots',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Keep the page screenshot and load filmstrip with each report. Uses about 50–150 KB per report.',
							'google-pagespeed-insights'
						) }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Store screenshots',
								'google-pagespeed-insights'
							) }
							checked={ values.store_screenshots }
							onChange={ set( 'store_screenshots' ) }
						/>
					</Row>
				</Card>

				<Card
					title={ __( 'What to check', 'google-pagespeed-insights' ) }
					description={ __(
						'Google loads every page to create its report, so more pages means longer scans.',
						'google-pagespeed-insights'
					) }
				>
					<div className="gpi-checkgrid">
						<ToggleControl
							__nextHasNoMarginBottom
							label={
								<>
									{ __(
										'Pages',
										'google-pagespeed-insights'
									) }{ ' ' }
									<Pill>{ values.counts.pages }</Pill>
								</>
							}
							checked={ values.check_pages }
							onChange={ set( 'check_pages' ) }
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={
								<>
									{ __(
										'Posts',
										'google-pagespeed-insights'
									) }{ ' ' }
									<Pill>{ values.counts.posts }</Pill>
								</>
							}
							checked={ values.check_posts }
							onChange={ set( 'check_posts' ) }
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={
								<>
									{ __(
										'Category archives',
										'google-pagespeed-insights'
									) }{ ' ' }
									<Pill>{ values.counts.categories }</Pill>
								</>
							}
							checked={ values.check_categories }
							onChange={ set( 'check_categories' ) }
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={
								<>
									{ __(
										'Custom URLs',
										'google-pagespeed-insights'
									) }{ ' ' }
									<Pill>{ values.counts.custom_urls }</Pill>
								</>
							}
							checked={ values.check_custom_urls }
							onChange={ set( 'check_custom_urls' ) }
						/>
					</div>
					{ values.post_types.length > 0 && (
						<div className="gpi-subsection">
							<div className="gpi-subsection__title">
								{ __(
									'Custom post types',
									'google-pagespeed-insights'
								) }
							</div>
							<div className="gpi-checkgrid">
								{ values.post_types.map( ( type ) => (
									<CheckboxControl
										__nextHasNoMarginBottom
										key={ type.value }
										label={
											<>
												{ type.label }{ ' ' }
												<Pill>{ type.count }</Pill>
											</>
										}
										checked={ values.cpt_whitelist.includes(
											type.value
										) }
										onChange={ ( on ) =>
											set( 'cpt_whitelist' )(
												on
													? [
															...values.cpt_whitelist,
															type.value,
														]
													: values.cpt_whitelist.filter(
															( item ) =>
																item !==
																type.value
														)
											)
										}
									/>
								) ) }
							</div>
						</div>
					) }
				</Card>

				<Card title={ __( 'Schedule', 'google-pagespeed-insights' ) }>
					<Row
						title={ __(
							'Automatic rechecks',
							'google-pagespeed-insights'
						) }
						description={
							values.next_scheduled
								? sprintf(
										/* translators: %s: date and time */ __(
											'Next scheduled check: %s',
											'google-pagespeed-insights'
										),
										values.next_scheduled
									)
								: __(
										'Recheck all pages automatically with WP-Cron.',
										'google-pagespeed-insights'
									)
						}
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Recheck on a schedule',
								'google-pagespeed-insights'
							) }
							checked={ values.use_schedule }
							onChange={ set( 'use_schedule' ) }
						/>
					</Row>
					<Row
						title={ __(
							'Report expiration',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Reports newer than this are skipped during scans, unless “Recheck all” is used. It also sets how often the schedule runs.',
							'google-pagespeed-insights'
						) }
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Report expiration',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ values.recheck_interval }
							options={ [
								{
									value: 86400,
									label: __(
										'1 day',
										'google-pagespeed-insights'
									),
								},
								{
									value: 604800,
									label: __(
										'7 days',
										'google-pagespeed-insights'
									),
								},
								{
									value: 1296000,
									label: __(
										'15 days',
										'google-pagespeed-insights'
									),
								},
								{
									value: 2592000,
									label: __(
										'30 days',
										'google-pagespeed-insights'
									),
								},
							] }
							onChange={ ( value ) =>
								set( 'recheck_interval' )(
									parseInt( value, 10 )
								)
							}
						/>
					</Row>
					{ values.cron_disabled && (
						<p className="gpi-hint">
							<span
								className="dashicons dashicons-info-outline"
								aria-hidden="true"
							/>
							{ __(
								'WP-Cron is disabled on this site (DISABLE_WP_CRON). Scheduled checks only run if your host runs cron for you.',
								'google-pagespeed-insights'
							) }
						</p>
					) }
				</Card>

				<Card
					title={ __( 'Advanced', 'google-pagespeed-insights' ) }
					description={ __(
						'The defaults work for most sites. Change these if scans stop before they finish.',
						'google-pagespeed-insights'
					) }
				>
					<Row
						title={ __(
							'Maximum execution time',
							'google-pagespeed-insights'
						) }
						description={
							values.set_time_limit_disabled
								? sprintf(
										/* translators: %d: seconds */ __(
											'Your server does not allow changing this; its limit is %d seconds. Use the maximum run time below instead.',
											'google-pagespeed-insights'
										),
										values.server_max_execution_time
									)
								: __(
										'How long one scan request may run.',
										'google-pagespeed-insights'
									)
						}
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Maximum execution time',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ values.max_execution_time }
							options={ [ 60, 300, 600, 900, 1800 ].map(
								( value ) => ( {
									value,
									label: sprintf(
										/* translators: %d: minutes */ __(
											'%d minutes',
											'google-pagespeed-insights'
										),
										value / 60
									),
								} )
							) }
							onChange={ ( value ) =>
								set( 'max_execution_time' )(
									parseInt( value, 10 )
								)
							}
						/>
					</Row>
					<Row
						title={ __(
							'Maximum script run time',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Stop and start a fresh scan request after this time. Helps on hosts with strict time limits.',
							'google-pagespeed-insights'
						) }
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Maximum script run time',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ values.max_run_time }
							options={ [
								0, 15, 30, 45, 60, 90, 120, 150, 180,
							].map( ( value ) => ( {
								value,
								label: seconds( value ),
							} ) ) }
							onChange={ ( value ) =>
								set( 'max_run_time' )( parseInt( value, 10 ) )
							}
						/>
					</Row>
					<Row
						title={ __(
							'Delay between pages',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Pause between checks to lower the load on slow servers.',
							'google-pagespeed-insights'
						) }
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Delay between pages',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ values.sleep_time }
							options={ [ 0, 1, 2, 3, 4, 5, 10 ].map(
								( value ) => ( {
									value,
									label:
										value === 0
											? __(
													'None',
													'google-pagespeed-insights'
												)
											: seconds( value ),
								} )
							) }
							onChange={ ( value ) =>
								set( 'sleep_time' )( parseInt( value, 10 ) )
							}
						/>
					</Row>
					<Row
						title={ __(
							'Progress updates',
							'google-pagespeed-insights'
						) }
						description={ __(
							'How often this screen asks for scan progress.',
							'google-pagespeed-insights'
						) }
					>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Progress updates',
								'google-pagespeed-insights'
							) }
							hideLabelFromVision
							value={ values.heartbeat }
							options={ [
								{
									value: 'fast',
									label: __(
										'Fast',
										'google-pagespeed-insights'
									),
								},
								{
									value: 'standard',
									label: __(
										'Standard',
										'google-pagespeed-insights'
									),
								},
								{
									value: 'slow',
									label: __(
										'Slow',
										'google-pagespeed-insights'
									),
								},
								{
									value: 'disabled',
									label: __(
										'Off (refresh the page to update)',
										'google-pagespeed-insights'
									),
								},
							] }
							onChange={ set( 'heartbeat' ) }
						/>
					</Row>
					<Row
						title={ __(
							'Log API errors',
							'google-pagespeed-insights'
						) }
						description={
							<>
								{ __(
									'Keep API errors for 7 days to help with troubleshooting.',
									'google-pagespeed-insights'
								) }{ ' ' }
								<Button
									variant="link"
									onClick={ () => navigate( 'logs' ) }
								>
									{ __(
										'View logs',
										'google-pagespeed-insights'
									) }
								</Button>
							</>
						}
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Log API errors',
								'google-pagespeed-insights'
							) }
							checked={ values.log_api_errors }
							onChange={ set( 'log_api_errors' ) }
						/>
					</Row>
				</Card>

				<Card
					title={ __( 'Delete data', 'google-pagespeed-insights' ) }
					className="gpi-card--danger"
				>
					<Row
						title={ __(
							'Delete reports',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Removes all reports and their history. Custom URLs, snapshots and settings are kept.',
							'google-pagespeed-insights'
						) }
					>
						<ConfirmButton
							variant="secondary"
							disabled={ running }
							title={ __(
								'Delete all reports?',
								'google-pagespeed-insights'
							) }
							message={ __(
								'All reports and score history will be deleted. This can’t be undone.',
								'google-pagespeed-insights'
							) }
							confirmLabel={ __(
								'Delete reports',
								'google-pagespeed-insights'
							) }
							onConfirm={ () => purge( 'reports' ) }
						>
							{ __(
								'Delete reports',
								'google-pagespeed-insights'
							) }
						</ConfirmButton>
					</Row>
					<Row
						title={ __(
							'Delete everything',
							'google-pagespeed-insights'
						) }
						description={ __(
							'Also removes custom URLs, ignored URLs, snapshots and logs. Settings are kept.',
							'google-pagespeed-insights'
						) }
					>
						<ConfirmButton
							variant="secondary"
							disabled={ running }
							title={ __(
								'Delete all plugin data?',
								'google-pagespeed-insights'
							) }
							message={ __(
								'Reports, history, custom URLs, ignored URLs, snapshots and logs will be deleted. This can’t be undone.',
								'google-pagespeed-insights'
							) }
							confirmLabel={ __(
								'Delete everything',
								'google-pagespeed-insights'
							) }
							onConfirm={ () => purge( 'everything' ) }
						>
							{ __(
								'Delete everything',
								'google-pagespeed-insights'
							) }
						</ConfirmButton>
					</Row>
				</Card>
			</div>

			<div className={ `gpi-savebar${ dirty ? ' is-dirty' : '' }` }>
				<span className="gpi-savebar__text">{ saveText }</span>
				{ dirty && (
					<Button
						variant="tertiary"
						onClick={ () => setValues( data ) }
					>
						{ __( 'Discard', 'google-pagespeed-insights' ) }
					</Button>
				) }
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ saving || ! dirty || running || keyNeedsCheck }
				>
					{ __( 'Save settings', 'google-pagespeed-insights' ) }
				</Button>
			</div>
		</>
	);
}
