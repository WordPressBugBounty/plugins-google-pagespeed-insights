import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Score buckets used by Lighthouse: 90-100 good, 50-89 needs improvement, 0-49 poor.
 * @param score
 */
export function scoreRating( score ) {
	if ( score === null || score === undefined || Number.isNaN( score ) ) {
		return 'none';
	}
	if ( score >= 90 ) {
		return 'good';
	}
	if ( score >= 50 ) {
		return 'average';
	}
	return 'poor';
}

export const RATING_LABELS = {
	good: __( 'Good', 'google-pagespeed-insights' ),
	average: __( 'Needs improvement', 'google-pagespeed-insights' ),
	poor: __( 'Poor', 'google-pagespeed-insights' ),
	none: __( 'No data', 'google-pagespeed-insights' ),
};

export const CATEGORY_ORDER = [
	'performance',
	'accessibility',
	'best-practices',
	'seo',
	'agentic-browsing',
];

export const CATEGORY_LABELS = {
	performance: __( 'Performance', 'google-pagespeed-insights' ),
	accessibility: __( 'Accessibility', 'google-pagespeed-insights' ),
	'best-practices': __( 'Best Practices', 'google-pagespeed-insights' ),
	seo: __( 'SEO', 'google-pagespeed-insights' ),
	'agentic-browsing': __( 'AI Agents', 'google-pagespeed-insights' ),
};

export const CATEGORY_HINTS = {
	performance: __(
		'Loading speed and responsiveness, measured in a simulated browser.',
		'google-pagespeed-insights'
	),
	accessibility: __(
		'How usable the page is for people using assistive technology.',
		'google-pagespeed-insights'
	),
	'best-practices': __(
		'Security, modern web standards and browser health.',
		'google-pagespeed-insights'
	),
	seo: __(
		'Whether search engines can crawl and understand the page.',
		'google-pagespeed-insights'
	),
	'agentic-browsing': __(
		'How well AI agents and answer engines can read and use the page (llms.txt, accessibility tree, WebMCP). Google still marks this category as experimental.',
		'google-pagespeed-insights'
	),
};

/**
 * Lab metric thresholds (good / poor), from web.dev and the Lighthouse scoring docs.
 */
export const METRICS = {
	'largest-contentful-paint': {
		acronym: 'LCP',
		unit: 'ms',
		good: 2500,
		poor: 4000,
		label: __( 'Largest Contentful Paint', 'google-pagespeed-insights' ),
	},
	'total-blocking-time': {
		acronym: 'TBT',
		unit: 'ms',
		good: 200,
		poor: 600,
		label: __( 'Total Blocking Time', 'google-pagespeed-insights' ),
	},
	'cumulative-layout-shift': {
		acronym: 'CLS',
		unit: '',
		good: 0.1,
		poor: 0.25,
		label: __( 'Cumulative Layout Shift', 'google-pagespeed-insights' ),
	},
	'first-contentful-paint': {
		acronym: 'FCP',
		unit: 'ms',
		good: 1800,
		poor: 3000,
		label: __( 'First Contentful Paint', 'google-pagespeed-insights' ),
	},
	'speed-index': {
		acronym: 'SI',
		unit: 'ms',
		good: 3400,
		poor: 5800,
		label: __( 'Speed Index', 'google-pagespeed-insights' ),
	},
};

export const METRIC_ORDER = [
	'largest-contentful-paint',
	'total-blocking-time',
	'cumulative-layout-shift',
	'first-contentful-paint',
	'speed-index',
];

/**
 * Chrome UX Report (real user) metrics.
 */
export const FIELD_METRICS = {
	LARGEST_CONTENTFUL_PAINT_MS: {
		acronym: 'LCP',
		unit: 'ms',
		good: 2500,
		poor: 4000,
		label: __( 'Largest Contentful Paint', 'google-pagespeed-insights' ),
		core: true,
	},
	INTERACTION_TO_NEXT_PAINT: {
		acronym: 'INP',
		unit: 'ms',
		good: 200,
		poor: 500,
		label: __( 'Interaction to Next Paint', 'google-pagespeed-insights' ),
		core: true,
	},
	CUMULATIVE_LAYOUT_SHIFT_SCORE: {
		acronym: 'CLS',
		unit: 'cls',
		good: 0.1,
		poor: 0.25,
		label: __( 'Cumulative Layout Shift', 'google-pagespeed-insights' ),
		core: true,
	},
	FIRST_CONTENTFUL_PAINT_MS: {
		acronym: 'FCP',
		unit: 'ms',
		good: 1800,
		poor: 3000,
		label: __( 'First Contentful Paint', 'google-pagespeed-insights' ),
	},
	EXPERIMENTAL_TIME_TO_FIRST_BYTE: {
		acronym: 'TTFB',
		unit: 'ms',
		good: 800,
		poor: 1800,
		label: __( 'Time to First Byte', 'google-pagespeed-insights' ),
	},
};

export function metricRating( id, value ) {
	const metric = METRICS[ id ] || FIELD_METRICS[ id ];
	if ( ! metric || value === null || value === undefined ) {
		return 'none';
	}
	if ( value <= metric.good ) {
		return 'good';
	}
	if ( value <= metric.poor ) {
		return 'average';
	}
	return 'poor';
}

export function formatMetric( id, value ) {
	const metric = METRICS[ id ] || FIELD_METRICS[ id ];
	if ( value === null || value === undefined ) {
		return '–';
	}
	if ( ! metric ) {
		return String( value );
	}
	if ( metric.unit === 'ms' ) {
		return formatMs( value );
	}
	if ( metric.unit === 'cls' ) {
		// CrUX reports CLS multiplied by 100.
		return ( value / 100 ).toFixed( 2 );
	}
	return (
		Number( value )
			.toFixed( 3 )
			.replace( /0+$/, '' )
			.replace( /\.$/, '' ) || '0'
	);
}

export function formatMs( value ) {
	if ( value === null || value === undefined ) {
		return '–';
	}
	if ( value >= 1000 ) {
		return sprintf(
			/* translators: %s: number of seconds */
			__( '%s s', 'google-pagespeed-insights' ),
			( value / 1000 ).toFixed( 1 )
		);
	}
	return sprintf(
		/* translators: %s: number of milliseconds */
		__( '%s ms', 'google-pagespeed-insights' ),
		Math.round( value )
	);
}

export function formatBytes( bytes ) {
	if (
		bytes === null ||
		bytes === undefined ||
		Number.isNaN( Number( bytes ) )
	) {
		return '–';
	}
	if ( bytes >= 1048576 ) {
		return ( bytes / 1048576 ).toFixed( 1 ) + ' MiB';
	}
	return ( bytes / 1024 ).toFixed( 1 ) + ' KiB';
}

/**
 * Timestamps stored by the plugin are in site local time (current_time( 'timestamp' )),
 * so they are formatted as UTC to show the site's local time.
 * @param timestamp
 * @param withTime
 */
export function formatDate( timestamp, withTime = true ) {
	if ( ! timestamp ) {
		return '–';
	}
	const options = {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		timeZone: 'UTC',
	};
	if ( withTime ) {
		options.hour = 'numeric';
		options.minute = '2-digit';
	}
	return new Intl.DateTimeFormat( undefined, options ).format(
		new Date( timestamp * 1000 )
	);
}

export function siteNow() {
	const offset = ( window.gpiApp && window.gpiApp.gmtOffset ) || 0;
	return Math.floor( Date.now() / 1000 ) + offset * 3600;
}

export function timeAgo( timestamp ) {
	if ( ! timestamp ) {
		return __( 'Never', 'google-pagespeed-insights' );
	}
	const seconds = Math.max( 0, siteNow() - timestamp );
	if ( seconds < 60 ) {
		return __( 'Just now', 'google-pagespeed-insights' );
	}
	const minutes = Math.floor( seconds / 60 );
	if ( minutes < 60 ) {
		return sprintf(
			/* translators: %d: number of minutes */
			_n(
				'%d minute ago',
				'%d minutes ago',
				minutes,
				'google-pagespeed-insights'
			),
			minutes
		);
	}
	const hours = Math.floor( minutes / 60 );
	if ( hours < 24 ) {
		return sprintf(
			/* translators: %d: number of hours */
			_n(
				'%d hour ago',
				'%d hours ago',
				hours,
				'google-pagespeed-insights'
			),
			hours
		);
	}
	const days = Math.floor( hours / 24 );
	if ( days < 45 ) {
		return sprintf(
			/* translators: %d: number of days */
			_n(
				'%d day ago',
				'%d days ago',
				days,
				'google-pagespeed-insights'
			),
			days
		);
	}
	return formatDate( timestamp, false );
}

/**
 * Splits a URL into a short path and host for compact display.
 * @param url
 */
export function splitUrl( url ) {
	try {
		const parsed = new URL( url );
		const home = ( window.gpiApp && window.gpiApp.homeHost ) || '';
		const path = parsed.pathname + parsed.search;
		return {
			host: parsed.host,
			path:
				path === '/'
					? __( '(homepage)', 'google-pagespeed-insights' )
					: decodeURI( path ),
			external: home !== '' && parsed.host !== home,
		};
	} catch {
		return { host: '', path: url, external: false };
	}
}

export function isHttpUrl( value ) {
	return typeof value === 'string' && /^https?:\/\//i.test( value );
}

export function typeLabel( type, filters = [] ) {
	const match = filters.find( ( filter ) => filter.value === type );
	return match ? match.label : type;
}

/**
 * Parses CSV text (comma, semicolon or tab separated, with quoted cells) into rows.
 * @param text
 */
export function parseCsv( text ) {
	const source = String( text ).replace( /^\uFEFF/, '' );
	const firstLine = source.split( /\r?\n/, 1 )[ 0 ];
	const counts = [ ',', ';', '\t' ].map(
		( char ) => firstLine.split( char ).length
	);
	const delimiter = [ ',', ';', '\t' ][
		counts.indexOf( Math.max( ...counts ) )
	];
	const rows = [];
	let row = [];
	let cell = '';
	let quoted = false;

	for ( let i = 0; i < source.length; i++ ) {
		const char = source[ i ];
		if ( quoted ) {
			if ( char === '"' && source[ i + 1 ] === '"' ) {
				cell += '"';
				i++;
			} else if ( char === '"' ) {
				quoted = false;
			} else {
				cell += char;
			}
		} else if ( char === '"' && cell === '' ) {
			quoted = true;
		} else if ( char === delimiter ) {
			row.push( cell.trim() );
			cell = '';
		} else if ( char === '\n' || char === '\r' ) {
			if ( char === '\r' && source[ i + 1 ] === '\n' ) {
				i++;
			}
			row.push( cell.trim() );
			rows.push( row );
			row = [];
			cell = '';
		} else {
			cell += char;
		}
	}
	row.push( cell.trim() );
	rows.push( row );

	return rows.filter( ( cells ) => cells.some( Boolean ) );
}

/**
 * Finds the URLs in parsed CSV rows. The URL is the first http(s) cell of each row;
 * a column with the header "label" (or "group") sets the label for that row.
 * Returns a map of label ('' for none) to URLs.
 * @param rows
 */
export function urlsFromCsv( rows ) {
	const header = ( rows[ 0 ] || [] ).map( ( cell ) => cell.toLowerCase() );
	const hasHeader = ! header.some( isHttpUrl );
	const labelColumn = hasHeader
		? header.findIndex( ( cell ) => cell === 'label' || cell === 'group' )
		: -1;
	const groups = {};

	( hasHeader ? rows.slice( 1 ) : rows ).forEach( ( cells ) => {
		const url = cells.find( isHttpUrl );
		if ( ! url ) {
			return;
		}
		const label = labelColumn >= 0 ? cells[ labelColumn ] || '' : '';
		groups[ label ] = groups[ label ] || [];
		if ( ! groups[ label ].includes( url ) ) {
			groups[ label ].push( url );
		}
	} );

	return groups;
}
