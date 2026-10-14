import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { get, post, errorMessage } from './api';

/* -------------------------------------------------------------------------
 * Hash router
 * ---------------------------------------------------------------------- */

function parseHash() {
	const hash = window.location.hash.replace( /^#\/?/, '' );
	const [ path, search = '' ] = hash.split( '?' );
	const query = {};
	new URLSearchParams( search ).forEach( ( value, key ) => {
		query[ key ] = value;
	} );
	return { parts: path.split( '/' ).filter( Boolean ), query };
}

export function navigate( to ) {
	window.location.hash = to.startsWith( '#' )
		? to
		: '#/' + to.replace( /^\//, '' );
}

export function useRoute() {
	const [ route, setRoute ] = useState( parseHash );

	useEffect( () => {
		const onChange = () => {
			setRoute( parseHash() );
			window.scrollTo( 0, 0 );
		};
		window.addEventListener( 'hashchange', onChange );
		return () => window.removeEventListener( 'hashchange', onChange );
	}, [] );

	return route;
}

/* -------------------------------------------------------------------------
 * App state
 * ---------------------------------------------------------------------- */

export const AppContext = createContext( null );

export function useApp() {
	return useContext( AppContext );
}

export function useAppState() {
	const [ app, setApp ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ view, setViewState ] = useState( 'desktop' );
	const [ filter, setFilter ] = useState( 'all' );
	const [ toasts, setToasts ] = useState( [] );
	const [ revision, setRevision ] = useState( 0 );
	const wasRunning = useRef( false );

	const notify = useCallback( ( message, status = 'success' ) => {
		const id = Date.now() + Math.random();
		setToasts( ( current ) => [ ...current, { id, message, status } ] );
		setTimeout(
			() =>
				setToasts( ( current ) =>
					current.filter( ( toast ) => toast.id !== id )
				),
			5000
		);
	}, [] );

	const refresh = useCallback( () => {
		return get( '/app' )
			.then( ( data ) => {
				setApp( data );
				setError( null );
				return data;
			} )
			.catch( ( e ) => setError( errorMessage( e ) ) );
	}, [] );

	// Bump to make screens reload their data (after a scan finished, data deleted, etc.).
	const invalidate = useCallback(
		() => setRevision( ( value ) => value + 1 ),
		[]
	);

	useEffect( () => {
		refresh().then( ( data ) => data && setViewState( data.view ) );
	}, [ refresh ] );

	const setView = useCallback( ( next ) => {
		setViewState( next );
		post( '/preferences', { view_preference: next } ).catch( () => {} );
	}, [] );

	const status = app ? app.status : null;
	const running = !! ( status && status.running );

	// Poll the scan status while a scan runs; refresh everything once it ends.
	useEffect( () => {
		if ( ! running ) {
			if ( wasRunning.current ) {
				wasRunning.current = false;
				refresh();
				invalidate();
			}
			return undefined;
		}
		wasRunning.current = true;
		const heartbeat = app && app.heartbeat;
		if ( heartbeat === 'disabled' ) {
			return undefined;
		}
		const delay =
			{ fast: 3000, standard: 5000, slow: 15000 }[ heartbeat ] || 5000;
		const timer = setInterval( () => {
			get( '/status' )
				.then( ( next ) =>
					setApp( ( current ) =>
						current ? { ...current, status: next } : current
					)
				)
				.catch( () => {} );
		}, delay );
		return () => clearInterval( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ running, refresh, invalidate ] );

	const startScan = useCallback(
		( recheck = false ) =>
			post( '/scan', { recheck } )
				.then( ( response ) => {
					setApp( ( current ) => ( {
						...current,
						status: { ...response.status, running: true },
					} ) );
					notify(
						__(
							'Scan started. Reports appear as each page is checked; you can leave this page.',
							'google-pagespeed-insights'
						)
					);
				} )
				.catch( ( e ) => notify( errorMessage( e ), 'error' ) ),
		[ notify ]
	);

	// Shows the progress bar right away after an action that starts a background check
	// (recheck, new custom URLs, reactivated URLs); polling then reports the real status.
	const expectScan = useCallback( () => {
		setApp( ( current ) =>
			current && current.heartbeat !== 'disabled'
				? {
						...current,
						status: {
							...current.status,
							running: true,
							aborting: false,
							done: 0,
							total: 0,
						},
					}
				: current
		);
	}, [] );

	const abortScan = useCallback(
		() =>
			post( '/scan/abort' )
				.then( ( next ) => {
					setApp( ( current ) => ( { ...current, status: next } ) );
					notify(
						__(
							'Stopping the scan after the current page.',
							'google-pagespeed-insights'
						),
						'info'
					);
				} )
				.catch( ( e ) => notify( errorMessage( e ), 'error' ) ),
		[ notify ]
	);

	const dismissNotice = useCallback( ( id ) => {
		setApp( ( current ) => ( {
			...current,
			notices: current.notices.filter( ( notice ) => notice.id !== id ),
		} ) );
		post( '/notices/dismiss', { id } ).catch( () => {} );
	}, [] );

	return {
		app,
		error,
		view,
		setView,
		filter,
		setFilter,
		refresh,
		revision,
		invalidate,
		running,
		status,
		startScan,
		expectScan,
		abortScan,
		toasts,
		notify,
		dismissNotice,
	};
}

/* -------------------------------------------------------------------------
 * Data loading hook
 * ---------------------------------------------------------------------- */

export function useResource( path, query, deps = [] ) {
	const [ state, setState ] = useState( {
		data: null,
		loading: true,
		error: null,
	} );
	const key = path + JSON.stringify( query || {} );

	const load = useCallback( () => {
		let cancelled = false;
		setState( ( current ) => ( {
			...current,
			loading: true,
			error: null,
		} ) );
		get( path, query || {} )
			.then(
				( data ) =>
					! cancelled &&
					setState( { data, loading: false, error: null } )
			)
			.catch(
				( e ) =>
					! cancelled &&
					setState( {
						data: null,
						loading: false,
						error: errorMessage( e ),
					} )
			);
		return () => {
			cancelled = true;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key, ...deps ] );

	useEffect( () => load(), [ load ] );

	return { ...state, reload: load };
}
