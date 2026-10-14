import { Button, Modal, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { isHttpUrl, splitUrl } from '../utils';

export function Card( {
	title,
	description,
	actions,
	children,
	className = '',
	padded = true,
} ) {
	return (
		<section className={ `gpi-card ${ className }` }>
			{ ( title || actions ) && (
				<header className="gpi-card__header">
					<div>
						{ title && (
							<h2 className="gpi-card__title">{ title }</h2>
						) }
						{ description && (
							<p className="gpi-card__description">
								{ description }
							</p>
						) }
					</div>
					{ actions && (
						<div className="gpi-card__actions">{ actions }</div>
					) }
				</header>
			) }
			<div
				className={
					padded
						? 'gpi-card__body'
						: 'gpi-card__body gpi-card__body--flush'
				}
			>
				{ children }
			</div>
		</section>
	);
}

export function PageHeader( { title, description, actions, back } ) {
	return (
		<div className="gpi-pagehead">
			<div className="gpi-pagehead__text">
				{ back && (
					<a className="gpi-back" href={ back.href }>
						<span
							className="dashicons dashicons-arrow-left-alt2"
							aria-hidden="true"
						/>
						{ back.label }
					</a>
				) }
				<h1 className="gpi-pagehead__title">{ title }</h1>
				{ description && (
					<p className="gpi-pagehead__description">{ description }</p>
				) }
			</div>
			{ actions && (
				<div className="gpi-pagehead__actions">{ actions }</div>
			) }
		</div>
	);
}

export function EmptyState( { icon = 'chart-bar', title, children, action } ) {
	return (
		<div className="gpi-empty">
			<span
				className={ `gpi-empty__icon dashicons dashicons-${ icon }` }
				aria-hidden="true"
			/>
			<h3 className="gpi-empty__title">{ title }</h3>
			{ children && <div className="gpi-empty__text">{ children }</div> }
			{ action && <div className="gpi-empty__action">{ action }</div> }
		</div>
	);
}

export function Loading( { label } ) {
	return (
		<div className="gpi-loading">
			<Spinner />
			<span>
				{ label || __( 'Loading…', 'google-pagespeed-insights' ) }
			</span>
		</div>
	);
}

export function Skeleton( { rows = 3, height = 16 } ) {
	return (
		<div className="gpi-skeleton" aria-hidden="true">
			{ Array.from( { length: rows } ).map( ( _, index ) => (
				<div
					key={ index }
					className="gpi-skeleton__row"
					style={ { height, width: `${ 100 - index * 12 }%` } }
				/>
			) ) }
		</div>
	);
}

export function ErrorMessage( { message, onRetry } ) {
	return (
		<div className="gpi-error">
			<span className="dashicons dashicons-warning" aria-hidden="true" />
			<span>{ message }</span>
			{ onRetry && (
				<Button variant="link" onClick={ onRetry }>
					{ __( 'Try again', 'google-pagespeed-insights' ) }
				</Button>
			) }
		</div>
	);
}

export function Pill( { children, tone = 'neutral', title } ) {
	return (
		<span className={ `gpi-pill gpi-pill--${ tone }` } title={ title }>
			{ children }
		</span>
	);
}

export function UrlCell( { url, href } ) {
	const parts = splitUrl( url );
	return (
		<div className="gpi-url">
			{ href ? (
				<a className="gpi-url__path" href={ href }>
					{ parts.path }
				</a>
			) : (
				<span className="gpi-url__path">{ parts.path }</span>
			) }
			<span className="gpi-url__host">{ parts.host }</span>
		</div>
	);
}

export function ExternalLink( { href, children, className = '' } ) {
	if ( ! isHttpUrl( href ) ) {
		return <span className={ className }>{ children }</span>;
	}
	return (
		<a
			className={ `gpi-external ${ className }` }
			href={ href }
			target="_blank"
			rel="noopener noreferrer"
		>
			{ children }
			<span className="screen-reader-text">
				{ __( '(opens in a new tab)', 'google-pagespeed-insights' ) }
			</span>
			<span className="dashicons dashicons-external" aria-hidden="true" />
		</a>
	);
}

export function ConfirmDialog( {
	title,
	message,
	confirmLabel,
	onConfirm,
	onClose,
	isDestructive = true,
} ) {
	const [ busy, setBusy ] = useState( false );

	const confirm = () => {
		setBusy( true );
		Promise.resolve( onConfirm() ).finally( () => {
			setBusy( false );
			onClose();
		} );
	};

	return (
		<Modal
			title={ title }
			onRequestClose={ () => ! busy && onClose() }
			size="small"
			className="gpi-modal"
		>
			<p>{ message }</p>
			<div className="gpi-modal__actions">
				<Button
					variant="tertiary"
					onClick={ onClose }
					disabled={ busy }
				>
					{ __( 'Cancel', 'google-pagespeed-insights' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive={ isDestructive }
					onClick={ confirm }
					isBusy={ busy }
					disabled={ busy }
				>
					{ confirmLabel ||
						__( 'Confirm', 'google-pagespeed-insights' ) }
				</Button>
			</div>
		</Modal>
	);
}

export function ConfirmButton( {
	children,
	title,
	message,
	confirmLabel,
	onConfirm,
	isDestructive = true,
	...props
} ) {
	const [ open, setOpen ] = useState( false );

	return (
		<>
			<Button
				{ ...props }
				isDestructive={ isDestructive }
				onClick={ () => setOpen( true ) }
			>
				{ children }
			</Button>
			{ open && (
				<ConfirmDialog
					title={ title }
					message={ message }
					confirmLabel={ confirmLabel }
					onConfirm={ onConfirm }
					onClose={ () => setOpen( false ) }
					isDestructive={ isDestructive }
				/>
			) }
		</>
	);
}

export function Pagination( { page, pages, total, onChange } ) {
	if ( pages <= 1 ) {
		return (
			<div className="gpi-pagination">
				<span className="gpi-pagination__total">
					{
						/* translators: %d: number of items */ sprintf(
							__( '%d items', 'google-pagespeed-insights' ),
							total
						)
					}
				</span>
			</div>
		);
	}
	return (
		<div className="gpi-pagination">
			<span className="gpi-pagination__total">
				{
					/* translators: %d: number of items */ sprintf(
						__( '%d items', 'google-pagespeed-insights' ),
						total
					)
				}
			</span>
			<div className="gpi-pagination__controls">
				<Button
					size="compact"
					icon="arrow-left-alt2"
					label={ __( 'Previous page', 'google-pagespeed-insights' ) }
					disabled={ page <= 1 }
					onClick={ () => onChange( page - 1 ) }
				/>
				<span className="gpi-pagination__page">
					{
						/* translators: 1: current page, 2: number of pages */ sprintf(
							__(
								'Page %1$d of %2$d',
								'google-pagespeed-insights'
							),
							page,
							pages
						)
					}
				</span>
				<Button
					size="compact"
					icon="arrow-right-alt2"
					label={ __( 'Next page', 'google-pagespeed-insights' ) }
					disabled={ page >= pages }
					onClick={ () => onChange( page + 1 ) }
				/>
			</div>
		</div>
	);
}

/**
 * Plain checkbox for table rows (the label is only for screen readers).
 * @param root0
 * @param root0.label
 * @param root0.checked
 * @param root0.onChange
 */
export function Check( { label, checked, onChange } ) {
	return (
		<input
			type="checkbox"
			className="gpi-check"
			aria-label={ label }
			checked={ !! checked }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
	);
}

export function Stat( { label, value, hint, tone } ) {
	return (
		<div className={ `gpi-stat${ tone ? ` gpi-stat--${ tone }` : '' }` }>
			<div className="gpi-stat__label">{ label }</div>
			<div className="gpi-stat__value">{ value }</div>
			{ hint && <div className="gpi-stat__hint">{ hint }</div> }
		</div>
	);
}

/**
 * HTML coming from the REST API is already filtered with wp_kses_post() on the server.
 * @param root0
 * @param root0.html
 * @param root0.className
 * @param root0.tag
 */
export function Html( { html, className = '', tag: Tag = 'div' } ) {
	if ( ! html ) {
		return null;
	}
	return (
		<Tag
			className={ className }
			dangerouslySetInnerHTML={ { __html: html } }
		/>
	);
}
