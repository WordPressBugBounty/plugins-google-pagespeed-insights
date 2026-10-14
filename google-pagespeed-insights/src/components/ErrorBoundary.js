import { Button } from '@wordpress/components';
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Catches errors thrown while a screen renders, so the user sees a message
 * with a way out instead of a blank page.
 */
export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { error: null };
		this.reset = this.reset.bind( this );
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error, info ) {
		// eslint-disable-next-line no-console
		console.error( 'Insights from Google PageSpeed:', error, info );
	}

	reset() {
		this.setState( { error: null } );
	}

	render() {
		const { error } = this.state;
		if ( ! error ) {
			return this.props.children;
		}

		const details =
			error && error.message ? error.message : String( error );

		return (
			<div className="gpi-empty gpi-crash" role="alert">
				<span
					className="gpi-empty__icon dashicons dashicons-warning"
					aria-hidden="true"
				/>
				<h2 className="gpi-empty__title">
					{ __(
						'This screen could not be displayed',
						'google-pagespeed-insights'
					) }
				</h2>
				<p className="gpi-empty__text">
					{ __(
						'Something went wrong while loading this page. Your reports and settings are not affected. Reloading the page usually fixes it, especially right after a plugin update.',
						'google-pagespeed-insights'
					) }
				</p>
				<div className="gpi-empty__action gpi-crash__actions">
					<Button
						variant="primary"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload page', 'google-pagespeed-insights' ) }
					</Button>
					<Button variant="secondary" onClick={ this.reset }>
						{ __( 'Try again', 'google-pagespeed-insights' ) }
					</Button>
				</div>
				<details className="gpi-crash__details">
					<summary>
						{ __(
							'Technical details',
							'google-pagespeed-insights'
						) }
					</summary>
					<code>{ details }</code>
				</details>
			</div>
		);
	}
}
