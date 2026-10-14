import { createRoot, render } from '@wordpress/element';
import App from './App';
import ErrorBoundary from './components/ErrorBoundary';
import './style.scss';

const root = document.getElementById( 'gpi-app' );

if ( root ) {
	// The outer boundary catches anything the per-screen one in App does not (header, notices).
	const app = (
		<ErrorBoundary>
			<App />
		</ErrorBoundary>
	);
	// createRoot exists from WordPress 6.2 (React 18).
	if ( createRoot ) {
		createRoot( root ).render( app );
	} else {
		render( app, root );
	}
}
