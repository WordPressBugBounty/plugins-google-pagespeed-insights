import { Button, CheckboxControl, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { AppContext, navigate, useApp, useAppState, useRoute } from './store';
import { ErrorMessage, Loading } from './components/ui';
import ErrorBoundary from './components/ErrorBoundary';
import Dashboard from './screens/Dashboard';
import Reports from './screens/Reports';
import ReportDetail from './screens/ReportDetail';
import Snapshots from './screens/Snapshots';
import SnapshotView from './screens/SnapshotView';
import CustomUrls from './screens/CustomUrls';
import Ignored from './screens/Ignored';
import Settings from './screens/Settings';
import Logs from './screens/Logs';

const NAV = [
	{ id: '', label: __( 'Overview', 'google-pagespeed-insights' ) },
	{ id: 'reports', label: __( 'Reports', 'google-pagespeed-insights' ) },
	{ id: 'snapshots', label: __( 'Snapshots', 'google-pagespeed-insights' ) },
	{ id: 'urls', label: __( 'Custom URLs', 'google-pagespeed-insights' ) },
	{ id: 'ignored', label: __( 'Ignored', 'google-pagespeed-insights' ) },
	{ id: 'settings', label: __( 'Settings', 'google-pagespeed-insights' ) },
];

function StrategyToggle() {
	const { app, view, setView } = useApp();
	if ( ! app || app.strategies.length < 2 ) {
		return null;
	}
	const labels = {
		desktop: __( 'Desktop', 'google-pagespeed-insights' ),
		mobile: __( 'Mobile', 'google-pagespeed-insights' ),
	};
	const icons = { desktop: 'desktop', mobile: 'smartphone' };
	return (
		<div
			className="gpi-segmented"
			role="radiogroup"
			aria-label={ __( 'Report type', 'google-pagespeed-insights' ) }
		>
			{ app.strategies.map( ( strategy ) => (
				<button
					key={ strategy }
					type="button"
					role="radio"
					aria-checked={ view === strategy }
					className={ view === strategy ? 'is-active' : '' }
					onClick={ () => setView( strategy ) }
				>
					<span
						className={ `dashicons dashicons-${ icons[ strategy ] }` }
						aria-hidden="true"
					/>
					{ labels[ strategy ] }
				</button>
			) ) }
		</div>
	);
}

function scanLabel( status ) {
	if ( status.aborting ) {
		return __( 'Stopping…', 'google-pagespeed-insights' );
	}
	if ( ! status.total ) {
		return __( 'Starting check…', 'google-pagespeed-insights' );
	}
	// "done" counts finished pages, so the page being checked is done + 1.
	return sprintf(
		/* translators: 1: number of the page being checked, 2: total pages */
		__( 'Checking page %1$d of %2$d', 'google-pagespeed-insights' ),
		Math.min( status.done + 1, status.total ),
		status.total
	);
}

function ScanControl() {
	const { app, running, status, startScan, abortScan } = useApp();
	const [ recheck, setRecheck ] = useState( false );
	const [ busy, setBusy ] = useState( false );

	if ( ! app ) {
		return null;
	}

	if ( running ) {
		const percent = status.total
			? Math.round( ( status.done / status.total ) * 100 )
			: 0;
		return (
			<div className="gpi-scan gpi-scan--running">
				<div className="gpi-scan__progress">
					<div className="gpi-scan__label">
						<span className="gpi-pulse" aria-hidden="true" />
						{ scanLabel( status ) }
					</div>
					<div
						className="gpi-progress"
						role="progressbar"
						aria-valuenow={ percent }
						aria-valuemin="0"
						aria-valuemax="100"
					>
						<div
							className="gpi-progress__bar"
							style={ { width: `${ Math.max( 4, percent ) }%` } }
						/>
					</div>
				</div>
				<Button
					variant="secondary"
					onClick={ abortScan }
					disabled={ status.aborting }
				>
					{ __( 'Stop', 'google-pagespeed-insights' ) }
				</Button>
			</div>
		);
	}

	const start = () => {
		setBusy( true );
		startScan( recheck ).finally( () => setBusy( false ) );
	};

	return (
		<div className="gpi-scan">
			<CheckboxControl
				__nextHasNoMarginBottom
				className="gpi-scan__recheck"
				label={ __( 'Recheck all', 'google-pagespeed-insights' ) }
				help={ null }
				checked={ recheck }
				onChange={ setRecheck }
				title={ __(
					'Ignore the report expiration and check every page again',
					'google-pagespeed-insights'
				) }
			/>
			<Button
				variant="primary"
				icon="update"
				onClick={ start }
				isBusy={ busy }
				disabled={ busy || ! app.hasKey }
			>
				{ __( 'Start scan', 'google-pagespeed-insights' ) }
			</Button>
		</div>
	);
}

function Header( { section } ) {
	const logo = window.gpiApp && window.gpiApp.logo;
	return (
		<header className="gpi-header">
			<div className="gpi-header__top">
				<div className="gpi-brand">
					{ logo && (
						<img className="gpi-brand__logo" src={ logo } alt="" />
					) }
					<div>
						<div className="gpi-brand__name">
							{ __(
								'Insights from Google PageSpeed',
								'google-pagespeed-insights'
							) }
						</div>
						<div className="gpi-brand__meta">
							{
								/* translators: %s: plugin version */ sprintf(
									__(
										'Version %s',
										'google-pagespeed-insights'
									),
									window.gpiApp ? window.gpiApp.version : ''
								)
							}
						</div>
					</div>
				</div>
				<div className="gpi-header__controls">
					<StrategyToggle />
					<ScanControl />
				</div>
			</div>
			<nav
				className="gpi-nav"
				aria-label={ __(
					'PageSpeed sections',
					'google-pagespeed-insights'
				) }
			>
				{ NAV.map( ( item ) => (
					<a
						key={ item.id }
						href={ `#/${ item.id }` }
						className={ section === item.id ? 'is-active' : '' }
						aria-current={
							section === item.id ? 'page' : undefined
						}
					>
						{ item.label }
					</a>
				) ) }
			</nav>
		</header>
	);
}

const NOTICE_ACTIONS = {
	settings: __( 'Open Settings', 'google-pagespeed-insights' ),
	logs: __( 'View logs', 'google-pagespeed-insights' ),
	ignored: __( 'View Ignored URLs', 'google-pagespeed-insights' ),
	site_health: __( 'Open Site Health', 'google-pagespeed-insights' ),
};

function openNoticeAction( action ) {
	if ( action === 'site_health' ) {
		// The app runs on a wp-admin page, so this opens Tools > Site Health.
		window.location.assign( 'site-health.php' );
		return;
	}
	navigate( action );
}

function Notices() {
	const { app, dismissNotice } = useApp();
	if ( ! app ) {
		return null;
	}
	return (
		<div className="gpi-notices">
			{ ! app.hasKey && (
				<Notice
					status="warning"
					isDismissible={ false }
					actions={ [
						{
							label: __(
								'Open Settings',
								'google-pagespeed-insights'
							),
							onClick: () => navigate( 'settings' ),
							variant: 'primary',
						},
					] }
				>
					{ __(
						'Add your Google PageSpeed API key to start checking your pages. The key is free.',
						'google-pagespeed-insights'
					) }
				</Notice>
			) }
			{ app.upgradeRecheck && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'New in 5.0: reports now include Accessibility, Best Practices and SEO scores, plus Core Web Vitals. To add these to your existing reports, tick "Recheck all" and click Start scan.',
						'google-pagespeed-insights'
					) }
				</Notice>
			) }
			{ app.notices.map( ( notice ) => (
				<Notice
					key={ notice.id }
					status={ notice.status }
					onRemove={ () => dismissNotice( notice.id ) }
					actions={
						NOTICE_ACTIONS[ notice.action ]
							? [
									{
										label: NOTICE_ACTIONS[ notice.action ],
										onClick: () =>
											openNoticeAction( notice.action ),
									},
								]
							: []
					}
				>
					{ notice.message }
				</Notice>
			) ) }
		</div>
	);
}

function Toasts() {
	const { toasts } = useApp();
	return (
		<div className="gpi-toasts" aria-live="polite">
			{ toasts.map( ( toast ) => (
				<div
					key={ toast.id }
					className={ `gpi-toast gpi-toast--${ toast.status }` }
				>
					<span
						className={ `dashicons dashicons-${ toast.status === 'error' ? 'warning' : 'yes-alt' }` }
						aria-hidden="true"
					/>
					{ toast.message }
				</div>
			) ) }
		</div>
	);
}

function Screen( { route } ) {
	const [ section, id, sub, otherId ] = route.parts;

	switch ( section ) {
		case 'reports':
			return id ? (
				<ReportDetail id={ parseInt( id, 10 ) } />
			) : (
				<Reports />
			);
		case 'snapshots':
			if ( id ) {
				return (
					<SnapshotView
						id={ parseInt( id, 10 ) }
						compareId={
							sub === 'compare' ? parseInt( otherId, 10 ) : null
						}
					/>
				);
			}
			return <Snapshots />;
		case 'urls':
			return <CustomUrls />;
		case 'ignored':
			return <Ignored />;
		case 'settings':
			return <Settings />;
		case 'logs':
			return <Logs />;
		default:
			return <Dashboard />;
	}
}

export default function App() {
	const store = useAppState();
	const route = useRoute();
	if ( store.error && ! store.app ) {
		return (
			<div className="gpi-app">
				<ErrorMessage
					message={ store.error }
					onRetry={ store.refresh }
				/>
			</div>
		);
	}

	if ( ! store.app ) {
		return (
			<div className="gpi-app">
				<Loading />
			</div>
		);
	}

	let section = '';
	if ( route.parts[ 0 ] === 'logs' ) {
		section = 'settings';
	} else if ( NAV.some( ( item ) => item.id === route.parts[ 0 ] ) ) {
		section = route.parts[ 0 ];
	}

	return (
		<AppContext.Provider value={ store }>
			<div className="gpi-app">
				<Header section={ section } />
				<Notices />
				<main className="gpi-main">
					{ /* Keyed by route so moving to another screen clears the error. */ }
					<ErrorBoundary key={ route.parts.join( '/' ) }>
						<Screen route={ route } />
					</ErrorBoundary>
				</main>
				<Toasts />
			</div>
		</AppContext.Provider>
	);
}
