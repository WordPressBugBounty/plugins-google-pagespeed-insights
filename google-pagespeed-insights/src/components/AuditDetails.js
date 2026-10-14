import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { formatBytes, formatMs, isHttpUrl } from '../utils';

const MAX_ROWS = 15;

function shortUrl( url ) {
	try {
		const parsed = new URL( url );
		const path =
			parsed.pathname.length > 64
				? '…' + parsed.pathname.slice( -60 )
				: parsed.pathname;
		return {
			host: parsed.host,
			path: path + ( parsed.search ? '?…' : '' ),
		};
	} catch {
		return { host: '', path: url };
	}
}

function UrlValue( { url } ) {
	if ( ! isHttpUrl( url ) ) {
		return <span className="gpi-cell-text">{ url }</span>;
	}
	const parts = shortUrl( url );
	return (
		<a
			className="gpi-cell-url"
			href={ url }
			target="_blank"
			rel="noopener noreferrer"
			title={ url }
		>
			<span className="gpi-cell-url__path">{ parts.path }</span>
			<span className="gpi-cell-url__host">{ parts.host }</span>
		</a>
	);
}

function Value( { value, type } ) {
	if ( value === null || value === undefined || value === '' ) {
		return <span className="gpi-muted">–</span>;
	}

	if ( typeof value === 'object' && ! Array.isArray( value ) ) {
		switch ( value.type ) {
			case 'node':
				return (
					<div className="gpi-cell-node">
						{ value.nodeLabel && (
							<span className="gpi-cell-node__label">
								{ value.nodeLabel }
							</span>
						) }
						{ value.snippet && (
							<code className="gpi-cell-node__snippet">
								{ value.snippet }
							</code>
						) }
					</div>
				);
			case 'source-location':
				return (
					<span className="gpi-cell-source">
						<UrlValue url={ value.url } />
						{ value.line !== undefined && (
							<code>
								:{ value.line + 1 }:{ value.column }
							</code>
						) }
					</span>
				);
			case 'url':
				return <UrlValue url={ value.value } />;
			case 'code':
				return <code>{ value.value }</code>;
			case 'link':
				return isHttpUrl( value.url ) ? (
					<a
						href={ value.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ value.text }
					</a>
				) : (
					<span>{ value.text }</span>
				);
			case 'text':
				return <span>{ value.value }</span>;
			case 'numeric':
				return <span>{ value.value }</span>;
			default:
				return value.value !== undefined ? (
					<span>{ String( value.value ) }</span>
				) : null;
		}
	}

	switch ( type ) {
		case 'url':
			return <UrlValue url={ String( value ) } />;
		case 'bytes':
			return (
				<span className="gpi-cell-num">
					{ formatBytes( Number( value ) ) }
				</span>
			);
		case 'ms':
		case 'timespanMs':
			return (
				<span className="gpi-cell-num">
					{ formatMs( Number( value ) ) }
				</span>
			);
		case 'numeric':
			return (
				<span className="gpi-cell-num">
					{ Number( value ).toLocaleString( undefined, {
						maximumFractionDigits: 3,
					} ) }
				</span>
			);
		case 'code':
			return <code>{ String( value ) }</code>;
		case 'thumbnail':
			return null;
		default:
			if ( typeof value === 'boolean' ) {
				return (
					<span>
						{ value
							? __( 'Yes', 'google-pagespeed-insights' )
							: __( 'No', 'google-pagespeed-insights' ) }
					</span>
				);
			}
			return isHttpUrl( value ) ? (
				<UrlValue url={ value } />
			) : (
				<span className="gpi-cell-text">{ String( value ) }</span>
			);
	}
}

function TableDetails( { details } ) {
	const [ showAll, setShowAll ] = useState( false );
	const headings = ( details.headings || [] ).filter(
		( heading ) =>
			heading.key && heading.label && heading.valueType !== 'thumbnail'
	);
	const items = details.items || [];

	if ( ! headings.length || ! items.length ) {
		return null;
	}

	const rows = showAll ? items : items.slice( 0, MAX_ROWS );
	const numeric = ( heading ) =>
		[ 'bytes', 'ms', 'timespanMs', 'numeric' ].includes(
			heading.valueType
		);

	return (
		<div className="gpi-details-table-wrap">
			<table className="gpi-details-table">
				<thead>
					<tr>
						{ headings.map( ( heading ) => (
							<th
								key={ heading.key }
								className={ numeric( heading ) ? 'is-num' : '' }
							>
								{ heading.label }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( item, index ) => [
						<tr key={ index }>
							{ headings.map( ( heading ) => (
								<td
									key={ heading.key }
									className={
										numeric( heading ) ? 'is-num' : ''
									}
								>
									<Value
										value={ item[ heading.key ] }
										type={ heading.valueType }
									/>
								</td>
							) ) }
						</tr>,
						...( ( item.subItems && item.subItems.items ) || [] )
							.slice( 0, 5 )
							.map( ( sub, subIndex ) => (
								<tr
									key={ `${ index }-${ subIndex }` }
									className="is-sub"
								>
									{ headings.map( ( heading ) => {
										const key =
											heading.subItemsHeading &&
											heading.subItemsHeading.key;
										return (
											<td
												key={ heading.key }
												className={
													numeric( heading )
														? 'is-num'
														: ''
												}
											>
												{ key ? (
													<Value
														value={ sub[ key ] }
														type={
															heading
																.subItemsHeading
																.valueType ||
															heading.valueType
														}
													/>
												) : null }
											</td>
										);
									} ) }
								</tr>
							) ),
					] ) }
				</tbody>
			</table>
			{ items.length > MAX_ROWS && (
				<Button
					variant="link"
					onClick={ () => setShowAll( ! showAll ) }
				>
					{ showAll
						? __( 'Show fewer', 'google-pagespeed-insights' )
						: /* translators: %d: number of rows */ sprintf(
								__(
									'Show all %d rows',
									'google-pagespeed-insights'
								),
								items.length
							) }
				</Button>
			) }
		</div>
	);
}

function ChecklistDetails( { details } ) {
	const items = Object.values( details.items || {} );
	if ( ! items.length ) {
		return null;
	}
	return (
		<ul className="gpi-checklist">
			{ items.map( ( item, index ) => (
				<li
					key={ index }
					className={ item.value ? 'is-pass' : 'is-fail' }
				>
					<span
						className={ `dashicons dashicons-${ item.value ? 'yes' : 'no-alt' }` }
						aria-hidden="true"
					/>
					{ item.label }
				</li>
			) ) }
		</ul>
	);
}

function TreeNode( { node, depth } ) {
	const children = Object.values( node.children || {} );
	return (
		<li className={ node.isLongest ? 'is-longest' : '' }>
			<div className="gpi-tree__row">
				{ node.url && <UrlValue url={ node.url } /> }
				<span className="gpi-tree__meta">
					{ node.navStartToEndTime !== undefined &&
						formatMs( node.navStartToEndTime ) }
					{ node.transferSize !== undefined &&
						` · ${ formatBytes( node.transferSize ) }` }
				</span>
			</div>
			{ children.length > 0 && depth < 6 && (
				<ul>
					{ children.map( ( child, index ) => (
						<TreeNode
							key={ index }
							node={ child }
							depth={ depth + 1 }
						/>
					) ) }
				</ul>
			) }
		</li>
	);
}

function NetworkTree( { details } ) {
	const chains = Object.values( details.chains || {} );
	if ( ! chains.length ) {
		return null;
	}
	return (
		<ul className="gpi-tree">
			{ chains.map( ( chain, index ) => (
				<TreeNode key={ index } node={ chain } depth={ 0 } />
			) ) }
		</ul>
	);
}

export default function AuditDetails( { details } ) {
	if ( ! details || typeof details !== 'object' ) {
		return null;
	}

	switch ( details.type ) {
		case 'table':
		case 'opportunity':
			return <TableDetails details={ details } />;
		case 'checklist':
			return <ChecklistDetails details={ details } />;
		case 'network-tree':
			return <NetworkTree details={ details } />;
		case 'list':
			return (
				<div className="gpi-details-list">
					{ ( details.items || [] ).map( ( item, index ) => {
						const inner =
							item && item.type === 'list-section'
								? item.value
								: item;
						return (
							<div
								key={ index }
								className="gpi-details-list__item"
							>
								{ item && item.title && (
									<h5>{ item.title }</h5>
								) }
								{ item && item.description && (
									<p className="gpi-muted">
										{ item.description }
									</p>
								) }
								<AuditDetails details={ inner } />
							</div>
						);
					} ) }
				</div>
			);
		case 'criticalrequestchain':
			return <NetworkTree details={ details } />;
		default:
			return null;
	}
}
