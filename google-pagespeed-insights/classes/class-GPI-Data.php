<?php
/**
 * =======================================
 * Google Pagespeed Insights Data
 * =======================================
 *
 * Read/write helpers shared by the REST API and the scanner.
 *
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	die();
}

class GPI_Data
{
	// Seconds a scan request may take to reach the site before it is reported as blocked.
	const LOOPBACK_TIMEOUT = 20;

	const STRATEGIES = array( 'desktop', 'mobile' );

	public static function table( $name )
	{
		global $wpdb;

		return $wpdb->prefix . 'gpi_' . $name;
	}

	public static function options()
	{
		$options = get_option( 'gpagespeedi_options' );

		return is_array( $options ) ? $options : array();
	}

	public static function strategy( $strategy )
	{
		return in_array( $strategy, self::STRATEGIES, true ) ? $strategy : 'desktop';
	}

	/**
	 * Strategies the user generates reports for.
	 */
	public static function enabled_strategies()
	{
		$options = self::options();
		$strategy = isset( $options['strategy'] ) ? $options['strategy'] : 'desktop';

		return 'both' === $strategy ? self::STRATEGIES : array( self::strategy( $strategy ) );
	}

	/* ---------------------------------------------------------------------
	 * Report types / filters
	 * ------------------------------------------------------------------- */

	public static function custom_url_labels()
	{
		global $wpdb;

		$table = self::table( 'custom_urls' );

		return $wpdb->get_col( "SELECT DISTINCT type FROM $table ORDER BY type" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.
	}

	public static function custom_post_type_whitelist()
	{
		$options = self::options();
		$whitelist = isset( $options['cpt_whitelist'] ) ? maybe_unserialize( $options['cpt_whitelist'] ) : array();

		return is_array( $whitelist ) ? array_values( array_filter( array_map( 'sanitize_key', $whitelist ) ) ) : array();
	}

	/**
	 * Filter choices for the report screens: array of array( value, label, group ).
	 */
	public static function filter_options()
	{
		$options = self::options();
		$filters = array(
			array( 'value' => 'all', 'label' => __( 'All Reports', 'google-pagespeed-insights' ), 'group' => '' )
		);

		if ( ! empty( $options['check_pages'] ) ) {
			$filters[] = array( 'value' => 'page', 'label' => __( 'Pages', 'google-pagespeed-insights' ), 'group' => '' );
		}

		if ( ! empty( $options['check_posts'] ) ) {
			$filters[] = array( 'value' => 'post', 'label' => __( 'Posts', 'google-pagespeed-insights' ), 'group' => '' );
		}

		if ( ! empty( $options['check_categories'] ) ) {
			$filters[] = array( 'value' => 'category', 'label' => __( 'Categories', 'google-pagespeed-insights' ), 'group' => '' );
		}

		$cpts = self::custom_post_type_whitelist();
		if ( $cpts ) {
			$group = __( 'Custom Post Types', 'google-pagespeed-insights' );
			$filters[] = array( 'value' => 'gpi_custom_posts', 'label' => __( 'All Custom Post Types', 'google-pagespeed-insights' ), 'group' => $group );
			foreach ( $cpts as $cpt ) {
				$object = get_post_type_object( $cpt );
				$filters[] = array( 'value' => $cpt, 'label' => $object ? $object->labels->name : $cpt, 'group' => $group );
			}
		}

		if ( ! empty( $options['check_custom_urls'] ) ) {
			$labels = self::custom_url_labels();
			if ( $labels ) {
				$group = __( 'Custom URLs', 'google-pagespeed-insights' );
				$filters[] = array( 'value' => 'gpi_custom_urls', 'label' => __( 'All Custom URLs', 'google-pagespeed-insights' ), 'group' => $group );
				foreach ( $labels as $label ) {
					$filters[] = array( 'value' => $label, 'label' => $label, 'group' => $group );
				}
			}
		}

		return apply_filters( 'gpi_rest_filter_options', $filters, $options );
	}

	/**
	 * Report "type" values covered by a filter value. Unknown filters match nothing.
	 */
	public static function filter_types( $filter )
	{
		$values = wp_list_pluck( self::filter_options(), 'value' );

		if ( 'all' === $filter || '' === $filter || null === $filter ) {
			return array_values( array_diff( $values, array( 'all', 'gpi_custom_posts', 'gpi_custom_urls' ) ) );
		}

		if ( 'gpi_custom_posts' === $filter ) {
			return self::custom_post_type_whitelist();
		}

		if ( 'gpi_custom_urls' === $filter ) {
			return self::custom_url_labels();
		}

		return in_array( $filter, $values, true ) ? array( $filter ) : array();
	}

	/**
	 * " AND type IN (...)" fragment, already prepared. Returns false when nothing can match.
	 */
	private static function types_clause( $filter, $column = 'type' )
	{
		global $wpdb;

		$types = self::filter_types( $filter );

		if ( empty( $types ) ) {
			return false;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		return $wpdb->prepare( " AND $column IN ( $placeholders )", $types ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $column is a fixed string from this class; $placeholders only contains %s.
	}

	/* ---------------------------------------------------------------------
	 * Decoding stored report data
	 * ------------------------------------------------------------------- */

	/**
	 * Lab metrics keyed by audit id.
	 */
	public static function decode_lab_data( $serialized )
	{
		$data = maybe_unserialize( $serialized );
		$metrics = array();

		if ( ! is_array( $data ) ) {
			return $metrics;
		}

		foreach ( $data as $metric ) {
			if ( ! is_array( $metric ) || empty( $metric['title'] ) ) {
				continue;
			}

			$id = ! empty( $metric['id'] ) ? sanitize_key( $metric['id'] ) : sanitize_key( $metric['title'] );

			$metrics[ $id ] = array(
				'id'			=> $id,
				'title'			=> sanitize_text_field( $metric['title'] ),
				'acronym'		=> isset( $metric['acronym'] ) ? sanitize_text_field( $metric['acronym'] ) : '',
				'score'			=> isset( $metric['score'] ) ? (float) $metric['score'] : null,
				'displayValue'	=> isset( $metric['displayValue'] ) ? sanitize_text_field( $metric['displayValue'] ) : '',
				'numericValue'	=> isset( $metric['numericValue'] ) && null !== $metric['numericValue'] ? (float) $metric['numericValue'] : null,
				'weight'		=> isset( $metric['weight'] ) ? (float) $metric['weight'] : 0,
				'description'	=> isset( $metric['description'] ) ? wp_kses_post( $metric['description'] ) : ''
			);
		}

		return $metrics;
	}

	/**
	 * Category scores keyed by category id (score 0-100 or null).
	 */
	public static function decode_categories( $serialized, $performance_score = null )
	{
		$data = maybe_unserialize( $serialized );
		$categories = array();

		if ( is_array( $data ) ) {
			foreach ( $data as $id => $category ) {
				$categories[ sanitize_key( $id ) ] = array(
					'title'	=> isset( $category['title'] ) ? sanitize_text_field( $category['title'] ) : '',
					'score'	=> isset( $category['score'] ) && null !== $category['score'] ? (int) $category['score'] : null
				);
			}
		}

		// Reports created before 5.0 only have the performance score.
		if ( ! isset( $categories['performance'] ) && null !== $performance_score && '' !== $performance_score ) {
			$categories['performance'] = array( 'title' => __( 'Performance', 'google-pagespeed-insights' ), 'score' => (int) $performance_score );
		}

		return $categories;
	}

	/**
	 * Field (Chrome UX Report) data: array( 'page' => metrics, 'origin' => metrics, overall ratings ).
	 */
	public static function decode_field_data( $serialized )
	{
		$data = maybe_unserialize( $serialized );
		$field = array( 'page' => array(), 'origin' => array(), 'page_overall' => '', 'origin_overall' => '' );

		if ( ! is_array( $data ) ) {
			return $field;
		}

		foreach ( $data as $key => $metric ) {
			if ( '_origin' === $key && is_array( $metric ) ) {
				$field['origin'] = self::clean_field_metrics( $metric );
			} else if ( '_overall' === $key ) {
				$field['page_overall'] = sanitize_key( $metric );
			} else if ( '_origin_overall' === $key ) {
				$field['origin_overall'] = sanitize_key( $metric );
			}
		}

		$field['page'] = self::clean_field_metrics( array_diff_key( $data, array_flip( array( '_origin', '_overall', '_origin_overall' ) ) ) );

		return $field;
	}

	private static function clean_field_metrics( $metrics )
	{
		$clean = array();

		foreach ( $metrics as $key => $metric ) {
			if ( ! is_array( $metric ) ) {
				continue;
			}

			$distributions = array();
			if ( ! empty( $metric['distributions'] ) && is_array( $metric['distributions'] ) ) {
				foreach ( $metric['distributions'] as $bucket ) {
					$distributions[] = array(
						'min'			=> isset( $bucket['min'] ) ? (float) $bucket['min'] : null,
						'max'			=> isset( $bucket['max'] ) ? (float) $bucket['max'] : null,
						'proportion'	=> isset( $bucket['proportion'] ) ? (float) $bucket['proportion'] : 0
					);
				}
			}

			$clean[ preg_replace( '/[^A-Z0-9_]/', '', strtoupper( $key ) ) ] = array(
				'percentile'	=> isset( $metric['percentile'] ) ? (float) $metric['percentile'] : null,
				'category'		=> isset( $metric['category'] ) ? sanitize_key( $metric['category'] ) : '',
				'distributions'	=> $distributions
			);
		}

		return $clean;
	}

	/**
	 * Recursively turns stored audit details into plain JSON-safe values.
	 * Strings are returned as text (the UI renders them as text, never as HTML).
	 */
	public static function clean_details( $value, $depth = 0 )
	{
		if ( $depth > 12 ) {
			return null;
		}

		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean[ is_int( $key ) ? $key : sanitize_text_field( $key ) ] = self::clean_details( $item, $depth + 1 );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		$value = (string) $value;

		// Screenshot thumbnails are kept as validated data URIs.
		if ( 0 === strpos( $value, 'data:image/' ) ) {
			return preg_match( '#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $value ) ? $value : '';
		}

		return wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );
	}

	/* ---------------------------------------------------------------------
	 * Reports
	 * ------------------------------------------------------------------- */

	private static function report_columns( $strategy )
	{
		$strategy = self::strategy( $strategy );

		return "ID, URL, type, object_id, term_id, custom_id, {$strategy}_score AS score, {$strategy}_lab_data AS lab_data, {$strategy}_categories AS categories, {$strategy}_last_modified AS last_modified";
	}

	private static function format_report_row( $row )
	{
		$lab = self::decode_lab_data( $row['lab_data'] );
		$metrics = array();

		foreach ( $lab as $id => $metric ) {
			$metrics[ $id ] = array(
				'value'		=> $metric['numericValue'],
				'display'	=> $metric['displayValue'],
				'score'		=> $metric['score']
			);
		}

		return array(
			'id'			=> (int) $row['ID'],
			'url'			=> esc_url_raw( $row['URL'] ),
			'type'			=> sanitize_text_field( $row['type'] ),
			'custom'		=> ! empty( $row['custom_id'] ),
			'score'			=> null === $row['score'] || '' === $row['score'] ? null : (int) $row['score'],
			'categories'	=> wp_list_pluck( self::decode_categories( $row['categories'], $row['score'] ), 'score' ),
			'metrics'		=> $metrics,
			'last_checked'	=> empty( $row['last_modified'] ) ? null : (int) $row['last_modified']
		);
	}

	/**
	 * Paged report list.
	 */
	public static function get_reports( $args )
	{
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'strategy'	=> 'desktop',
			'filter'	=> 'all',
			'search'	=> '',
			'orderby'	=> 'score',
			'order'		=> 'asc',
			'page'		=> 1,
			'per_page'	=> 25
		) );

		$strategy = self::strategy( $args['strategy'] );
		$table = self::table( 'page_stats' );
		$types = self::types_clause( $args['filter'] );

		if ( false === $types ) {
			return array( 'items' => array(), 'total' => 0, 'pages' => 0 );
		}

		$where = "WHERE 1=1 $types";
		if ( '' !== $args['search'] ) {
			$where .= $wpdb->prepare( ' AND URL LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$orderby_map = array(
			'score'			=> "{$strategy}_score IS NULL, {$strategy}_score",
			'url'			=> 'URL',
			'type'			=> 'type',
			'last_checked'	=> "{$strategy}_last_modified"
		);
		$orderby = isset( $orderby_map[ $args['orderby'] ] ) ? $orderby_map[ $args['orderby'] ] : $orderby_map['score'];
		$order = 'desc' === strtolower( $args['order'] ) ? 'DESC' : 'ASC';
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$page = max( 1, (int) $args['page'] );
		$offset = ( $page - 1 ) * $per_page;
		$columns = self::report_columns( $strategy );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; $where is built from prepared fragments; ORDER BY comes from a fixed map.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table $where" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT $columns FROM $table $where ORDER BY $orderby $order, ID ASC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$items = array_map( array( __CLASS__, 'format_report_row' ), (array) $rows );
		$trends = self::get_trends( wp_list_pluck( $items, 'id' ), $strategy, 12 );

		foreach ( $items as &$item ) {
			$item['trend'] = isset( $trends[ $item['id'] ] ) ? $trends[ $item['id'] ] : array();
		}

		return array(
			'items'	=> $items,
			'total'	=> $total,
			'pages'	=> (int) ceil( $total / $per_page )
		);
	}

	/**
	 * Full report for one page and strategy.
	 */
	public static function get_report( $id, $strategy )
	{
		global $wpdb;

		$strategy = self::strategy( $strategy );
		$stats_table = self::table( 'page_stats' );
		$reports_table = self::table( 'page_reports' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are $wpdb->prefix plus a fixed string; $strategy is allowlisted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT ID, URL, type, custom_id, {$strategy}_score AS score, {$strategy}_lab_data AS lab_data, {$strategy}_field_data AS field_data, {$strategy}_categories AS categories, {$strategy}_last_modified AS last_modified FROM $stats_table WHERE ID = %d", $id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$audit_rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT rule_key, rule_name, rule_type, rule_category, rule_group, rule_score, rule_blocks FROM $reports_table WHERE page_id = %d AND strategy = %s ORDER BY rule_score IS NULL, rule_score ASC", $id, $strategy ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$image_audits = self::image_audit_keys();
		$audits = array();
		$screenshot = '';
		$filmstrip = array();

		foreach ( (array) $audit_rows as $audit_row ) {
			$blocks = maybe_unserialize( $audit_row['rule_blocks'] );
			$blocks = is_array( $blocks ) ? $blocks : array();
			$details = isset( $blocks['details'] ) ? self::clean_details( $blocks['details'] ) : null;

			if ( 'final-screenshot' === $audit_row['rule_key'] ) {
				$screenshot = isset( $details['data'] ) ? $details['data'] : '';
				continue;
			}

			if ( 'screenshot-thumbnails' === $audit_row['rule_key'] ) {
				$filmstrip = isset( $details['items'] ) ? $details['items'] : array();
				continue;
			}

			$audits[] = array(
				'id'				=> sanitize_key( $audit_row['rule_key'] ),
				'title'				=> wp_strip_all_tags( html_entity_decode( $audit_row['rule_name'], ENT_QUOTES, 'UTF-8' ) ),
				'description'		=> isset( $blocks['description'] ) ? wp_kses_post( $blocks['description'] ) : '',
				'category'			=> $audit_row['rule_category'] ? sanitize_key( $audit_row['rule_category'] ) : 'performance',
				'group'				=> $audit_row['rule_group'] ? sanitize_key( $audit_row['rule_group'] ) : self::legacy_group( $audit_row ),
				'score'				=> null === $audit_row['rule_score'] ? null : (float) $audit_row['rule_score'],
				'scoreDisplayMode'	=> isset( $blocks['score_display_mode'] ) ? sanitize_key( $blocks['score_display_mode'] ) : '',
				'displayValue'		=> isset( $blocks['display_value'] ) ? sanitize_text_field( $blocks['display_value'] ) : '',
				'weight'			=> isset( $blocks['weight'] ) ? (float) $blocks['weight'] : 0,
				'metricSavings'		=> isset( $blocks['metric_savings'] ) ? array_map( 'floatval', (array) $blocks['metric_savings'] ) : new stdClass(),
				'stackTip'			=> isset( $blocks['stack_tip'] ) ? wp_kses_post( $blocks['stack_tip'] ) : '',
				'details'			=> $details,
				'imageOffer'		=> in_array( $audit_row['rule_key'], $image_audits, true ) && null !== $audit_row['rule_score'] && $audit_row['rule_score'] < 0.9
			);
		}

		$report = self::format_report_row( $row );

		return array_merge( $report, array(
			'strategy'		=> $strategy,
			'lab'			=> array_values( self::decode_lab_data( $row['lab_data'] ) ),
			'field'			=> self::decode_field_data( $row['field_data'] ),
			'categories'	=> self::decode_categories( $row['categories'], $row['score'] ),
			'audits'		=> $audits,
			'screenshot'	=> $screenshot,
			'filmstrip'		=> $filmstrip,
			'history'		=> self::get_page_history( $id, $strategy, 60 ),
			'legacy'		=> empty( $row['categories'] )
		) );
	}

	/**
	 * Group for audits stored before 5.0 (no rule_group column yet).
	 */
	private static function legacy_group( $audit_row )
	{
		if ( 'opportunity' === $audit_row['rule_type'] ) {
			return 'insights';
		}

		return 'diagnostics';
	}

	public static function image_audit_keys()
	{
		return apply_filters( 'gpi_image_audit_keys', array( 'uses-optimized-images', 'uses-webp-images', 'modern-image-formats', 'image-delivery-insight' ) );
	}

	/**
	 * Report ids for a list of page stats ids that actually exist.
	 */
	public static function existing_report_ids( $ids )
	{
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$table = self::table( 'page_stats' );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $table WHERE ID IN ( $placeholders )", $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; $placeholders only contains %d; custom plugin table.
	}

	public static function delete_reports( $ids )
	{
		global $wpdb;

		$ids = self::existing_report_ids( $ids );

		foreach ( $ids as $id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
			$wpdb->delete( self::table( 'page_stats' ), array( 'ID' => $id ), array( '%d' ) );
			$wpdb->delete( self::table( 'page_reports' ), array( 'page_id' => $id ), array( '%d' ) );
			$wpdb->delete( self::table( 'score_history' ), array( 'page_id' => $id ), array( '%d' ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return count( $ids );
	}

	public static function ignore_reports( $ids )
	{
		global $wpdb;

		$ids = self::existing_report_ids( $ids );
		$stats_table = self::table( 'page_stats' );

		foreach ( $ids as $id ) {
			$page = $wpdb->get_row( $wpdb->prepare( "SELECT URL, type, object_id, term_id, custom_id FROM $stats_table WHERE ID = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.

			if ( $page ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; WordPress has no API for it.
				$wpdb->insert( self::table( 'page_blacklist' ), $page );
			}
		}

		self::delete_reports( $ids );

		return count( $ids );
	}

	/**
	 * Marks reports for a forced recheck and starts a scan of only those pages, like the
	 * single recheck in 4.x. If a scan is already running, it picks up the flag instead.
	 */
	public static function recheck_reports( $ids )
	{
		global $wpdb;

		$ids = self::existing_report_ids( $ids );

		if ( empty( $ids ) ) {
			return 0;
		}

		$table = self::table( 'page_stats' );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( $wpdb->prepare( "UPDATE $table SET force_recheck = 1 WHERE ID IN ( $placeholders )", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; $placeholders only contains %d; custom plugin table.

		if ( apply_filters( 'gpi_check_status', false ) ) {
			return count( $ids );
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT URL, type, object_id, term_id, custom_id FROM $table WHERE ID IN ( $placeholders )", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; $placeholders only contains %d; custom plugin table.

		// Same structure as GPI_Core::get_urls_to_check(), limited to the selected pages.
		$urls = array( 'total_url_count' => 0 );
		foreach ( (array) $rows as $row ) {
			$item = array( 'url' => esc_url_raw( $row['URL'] ) );

			if ( ! empty( $row['custom_id'] ) ) {
				$item['objectid'] = (int) $row['custom_id'];
				$item['custom'] = 1;
			} else if ( ! empty( $row['term_id'] ) ) {
				$item['objectid'] = (int) $row['term_id'];
			} else {
				$item['objectid'] = (int) $row['object_id'];
			}

			if ( empty( $item['objectid'] ) ) {
				continue;
			}

			$urls[ (string) $row['type'] ][] = $item;
			$urls['total_url_count']++;
		}

		if ( $urls['total_url_count'] ) {
			delete_option( 'gpi_abort_scan' );
			update_option( 'gpi_recheck_urls', $urls, false );
			do_action( 'run_gpi', true, false, true );
		}

		return count( $ids );
	}

	/* ---------------------------------------------------------------------
	 * Score history
	 * ------------------------------------------------------------------- */

	public static function get_page_history( $page_id, $strategy, $limit = 60 )
	{
		global $wpdb;

		$table = self::table( 'score_history' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; placeholders only contain %d.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT score, categories, metrics, checked_at FROM $table WHERE page_id = %d AND strategy = %s ORDER BY checked_at DESC LIMIT %d", $page_id, self::strategy( $strategy ), $limit ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$history = array();
		foreach ( array_reverse( (array) $rows ) as $row ) {
			$history[] = array(
				'time'			=> (int) $row['checked_at'],
				'score'			=> null === $row['score'] ? null : (int) $row['score'],
				'categories'	=> (array) json_decode( (string) $row['categories'], true ),
				'metrics'		=> (array) json_decode( (string) $row['metrics'], true )
			);
		}

		return $history;
	}

	/**
	 * Last $points scores per page, for sparklines.
	 */
	public static function get_trends( $page_ids, $strategy, $points = 12 )
	{
		global $wpdb;

		$page_ids = array_values( array_filter( array_map( 'intval', (array) $page_ids ) ) );
		if ( empty( $page_ids ) ) {
			return array();
		}

		$table = self::table( 'score_history' );
		$placeholders = implode( ', ', array_fill( 0, count( $page_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; placeholders only contain %d.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT page_id, score FROM $table WHERE strategy = %s AND page_id IN ( $placeholders ) ORDER BY checked_at DESC", array_merge( array( self::strategy( $strategy ) ), $page_ids ) ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$trends = array();
		foreach ( (array) $rows as $row ) {
			$id = (int) $row['page_id'];
			if ( ! isset( $trends[ $id ] ) ) {
				$trends[ $id ] = array();
			}
			if ( count( $trends[ $id ] ) < $points && null !== $row['score'] ) {
				$trends[ $id ][] = (int) $row['score'];
			}
		}

		return array_map( 'array_reverse', $trends );
	}

	/**
	 * Daily averages of every category over the last $days days, for the pages in a filter.
	 */
	public static function get_site_trend( $strategy, $filter, $days = 90 )
	{
		global $wpdb;

		$types = self::types_clause( $filter, 's.type' );
		if ( false === $types ) {
			return array();
		}

		$history_table = self::table( 'score_history' );
		$stats_table = self::table( 'page_stats' );
		$since = current_time( 'timestamp' ) - $days * DAY_IN_SECONDS;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are $wpdb->prefix plus a fixed string; $types is a prepared fragment.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT h.score, h.categories, h.checked_at FROM $history_table h INNER JOIN $stats_table s ON s.ID = h.page_id WHERE h.strategy = %s AND h.checked_at >= %d $types ORDER BY h.checked_at ASC", self::strategy( $strategy ), $since ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$days_data = array();
		foreach ( (array) $rows as $row ) {
			$day = gmdate( 'Y-m-d', (int) $row['checked_at'] );
			if ( ! isset( $days_data[ $day ] ) ) {
				$days_data[ $day ] = array( 'sums' => array(), 'counts' => array() );
			}
			$categories = (array) json_decode( (string) $row['categories'], true );
			if ( ! isset( $categories['performance'] ) && null !== $row['score'] ) {
				$categories['performance'] = (int) $row['score'];
			}
			foreach ( $categories as $id => $score ) {
				if ( null === $score ) {
					continue;
				}
				$id = sanitize_key( $id );
				$days_data[ $day ]['sums'][ $id ] = ( isset( $days_data[ $day ]['sums'][ $id ] ) ? $days_data[ $day ]['sums'][ $id ] : 0 ) + $score;
				$days_data[ $day ]['counts'][ $id ] = ( isset( $days_data[ $day ]['counts'][ $id ] ) ? $days_data[ $day ]['counts'][ $id ] : 0 ) + 1;
			}
		}

		$trend = array();
		foreach ( $days_data as $day => $data ) {
			$point = array( 'date' => $day );
			foreach ( $data['sums'] as $id => $sum ) {
				$point[ $id ] = round( $sum / $data['counts'][ $id ] );
			}
			$trend[] = $point;
		}

		return $trend;
	}

	/* ---------------------------------------------------------------------
	 * Summary
	 * ------------------------------------------------------------------- */

	public static function get_summary( $strategy, $filter )
	{
		global $wpdb;

		$strategy = self::strategy( $strategy );
		$stats_table = self::table( 'page_stats' );
		$reports_table = self::table( 'page_reports' );
		$types = self::types_clause( $filter );
		$types_s = self::types_clause( $filter, 's.type' );

		$summary = array(
			'pages'				=> 0,
			'score'				=> null,
			'categories'		=> array(),
			'distribution'		=> array( 'good' => 0, 'average' => 0, 'poor' => 0 ),
			'metrics'			=> array(),
			'issues'			=> array(),
			'categoryIssues'	=> array(),
			'best'				=> array(),
			'worst'				=> array(),
			'trend'				=> array(),
			'legacy'			=> 0
		);

		if ( false === $types ) {
			return $summary;
		}

		$columns = self::report_columns( $strategy );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are $wpdb->prefix plus a fixed string; $strategy is allowlisted; $types is a prepared fragment.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$rows = $wpdb->get_results( "SELECT $columns FROM $stats_table WHERE {$strategy}_score IS NOT NULL $types", ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $rows ) ) {
			return $summary;
		}

		$category_sums = array();
		$category_counts = array();
		$category_titles = array();
		$metric_values = array();
		$pages = array();

		foreach ( $rows as $row ) {
			$report = self::format_report_row( $row );
			$pages[] = $report;
			$score = $report['score'];

			if ( $score >= 90 ) {
				$summary['distribution']['good']++;
			} else if ( $score >= 50 ) {
				$summary['distribution']['average']++;
			} else {
				$summary['distribution']['poor']++;
			}

			if ( empty( $row['categories'] ) ) {
				$summary['legacy']++;
			}

			foreach ( self::decode_categories( $row['categories'], $row['score'] ) as $id => $category ) {
				$category_titles[ $id ] = $category['title'];
				if ( null === $category['score'] ) {
					continue;
				}
				$category_sums[ $id ] = ( isset( $category_sums[ $id ] ) ? $category_sums[ $id ] : 0 ) + $category['score'];
				$category_counts[ $id ] = ( isset( $category_counts[ $id ] ) ? $category_counts[ $id ] : 0 ) + 1;
			}

			foreach ( self::decode_lab_data( $row['lab_data'] ) as $id => $metric ) {
				if ( null === $metric['numericValue'] ) {
					continue;
				}
				if ( ! isset( $metric_values[ $id ] ) ) {
					$metric_values[ $id ] = array( 'title' => $metric['title'], 'acronym' => $metric['acronym'], 'values' => array() );
				}
				$metric_values[ $id ]['values'][] = $metric['numericValue'];
			}
		}

		$summary['pages'] = count( $pages );

		foreach ( $category_sums as $id => $sum ) {
			$summary['categories'][ $id ] = array(
				'title'	=> $category_titles[ $id ],
				'score'	=> (int) round( $sum / $category_counts[ $id ] ),
				'pages'	=> $category_counts[ $id ]
			);
		}
		$summary['score'] = isset( $summary['categories']['performance'] ) ? $summary['categories']['performance']['score'] : null;

		foreach ( $metric_values as $id => $metric ) {
			$values = $metric['values'];
			sort( $values );
			$summary['metrics'][ $id ] = array(
				'title'		=> $metric['title'],
				'acronym'	=> $metric['acronym'],
				'average'	=> array_sum( $values ) / count( $values ),
				'median'	=> $values[ (int) floor( ( count( $values ) - 1 ) / 2 ) ],
				'p75'		=> $values[ max( 0, (int) ceil( count( $values ) * 0.75 ) - 1 ) ]
			);
		}

		usort( $pages, function ( $a, $b ) {
			return $b['score'] - $a['score'];
		} );
		$summary['best'] = array_slice( $pages, 0, 5 );
		$summary['worst'] = array_slice( array_reverse( $pages ), 0, 5 );

		// Most common failing performance insights / diagnostics.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are $wpdb->prefix plus a fixed string; $strategy is allowlisted; $types_s is a prepared fragment.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$issues = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.rule_key, MAX(r.rule_name) AS rule_name, MAX(r.rule_group) AS rule_group, MAX(r.rule_type) AS rule_type, COUNT(*) AS pages, AVG(r.rule_score) AS avg_score
				FROM $reports_table r
				INNER JOIN $stats_table s ON s.ID = r.page_id
				WHERE r.strategy = %s AND s.{$strategy}_score IS NOT NULL AND r.rule_score IS NOT NULL AND r.rule_score < 0.9
				AND ( r.rule_category = 'performance' OR r.rule_category IS NULL )
				AND ( r.rule_group IN ( 'insights', 'diagnostics' ) OR ( r.rule_group IS NULL AND r.rule_type = 'opportunity' ) )
				$types_s
				GROUP BY r.rule_key
				ORDER BY pages DESC, avg_score ASC
				LIMIT 10",
				$strategy
			),
			ARRAY_A
		);

		// Failing audits of the other categories.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$category_issues = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.rule_category, r.rule_key, MAX(r.rule_name) AS rule_name, COUNT(*) AS pages
				FROM $reports_table r
				INNER JOIN $stats_table s ON s.ID = r.page_id
				WHERE r.strategy = %s AND s.{$strategy}_score IS NOT NULL AND r.rule_score IS NOT NULL AND r.rule_score < 1
				AND r.rule_category IN ( 'accessibility', 'best-practices', 'seo', 'agentic-browsing' )
				$types_s
				GROUP BY r.rule_category, r.rule_key
				ORDER BY pages DESC",
				$strategy
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$image_audits = self::image_audit_keys();
		foreach ( (array) $issues as $issue ) {
			$summary['issues'][] = array(
				'id'			=> sanitize_key( $issue['rule_key'] ),
				'title'			=> wp_strip_all_tags( html_entity_decode( $issue['rule_name'], ENT_QUOTES, 'UTF-8' ) ),
				'group'			=> $issue['rule_group'] ? sanitize_key( $issue['rule_group'] ) : 'insights',
				'pages'			=> (int) $issue['pages'],
				'score'			=> round( (float) $issue['avg_score'] * 100 ),
				'imageOffer'	=> in_array( $issue['rule_key'], $image_audits, true )
			);
		}

		foreach ( (array) $category_issues as $issue ) {
			$category = sanitize_key( $issue['rule_category'] );
			if ( ! isset( $summary['categoryIssues'][ $category ] ) ) {
				$summary['categoryIssues'][ $category ] = array();
			}
			if ( count( $summary['categoryIssues'][ $category ] ) < 6 ) {
				$summary['categoryIssues'][ $category ][] = array(
					'id'	=> sanitize_key( $issue['rule_key'] ),
					'title'	=> wp_strip_all_tags( html_entity_decode( $issue['rule_name'], ENT_QUOTES, 'UTF-8' ) ),
					'pages'	=> (int) $issue['pages']
				);
			}
		}

		$summary['trend'] = self::get_site_trend( $strategy, $filter );

		return $summary;
	}

	/* ---------------------------------------------------------------------
	 * Snapshots
	 * ------------------------------------------------------------------- */

	public static function get_snapshots()
	{
		global $wpdb;

		$table = self::table( 'summary_snapshots' );
		$rows = $wpdb->get_results( "SELECT ID, strategy, type, snaptime, comment, summary_stats FROM $table ORDER BY snaptime DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.

		$snapshots = array();
		foreach ( (array) $rows as $row ) {
			$stats = json_decode( (string) $row['summary_stats'], true );
			$snapshots[] = array(
				'id'		=> (int) $row['ID'],
				'strategy'	=> self::strategy( $row['strategy'] ),
				'filter'	=> sanitize_text_field( $row['type'] ),
				'time'		=> (int) $row['snaptime'],
				'comment'	=> sanitize_text_field( $row['comment'] ),
				'score'		=> isset( $stats['score'] ) ? (int) $stats['score'] : null,
				'pages'		=> isset( $stats['pages'] ) ? (int) $stats['pages'] : null
			);
		}

		return $snapshots;
	}

	public static function create_snapshot( $strategy, $filter, $comment )
	{
		global $wpdb;

		$summary = self::get_summary( $strategy, $filter );
		$summary['format'] = 2;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; WordPress has no API for it.
		$saved = $wpdb->insert(
			self::table( 'summary_snapshots' ),
			array(
				'strategy'			=> self::strategy( $strategy ),
				'type'				=> sanitize_text_field( $filter ),
				'snaptime'			=> current_time( 'timestamp' ),
				'comment'			=> sanitize_text_field( $comment ),
				'summary_stats'		=> wp_json_encode( $summary ),
				'summary_reports'	=> wp_json_encode( array() )
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $saved ? (int) $wpdb->insert_id : 0;
	}

	public static function get_snapshot( $id )
	{
		global $wpdb;

		$table = self::table( 'summary_snapshots' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, strategy, type, snaptime, comment, summary_stats, summary_reports FROM $table WHERE ID = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.

		if ( ! $row ) {
			return null;
		}

		$stats = json_decode( (string) $row['summary_stats'], true );
		$stats = is_array( $stats ) ? $stats : array();

		if ( empty( $stats['format'] ) ) {
			$stats = self::convert_legacy_snapshot( $stats, json_decode( (string) $row['summary_reports'], true ) );
		}

		return array(
			'id'		=> (int) $row['ID'],
			'strategy'	=> self::strategy( $row['strategy'] ),
			'filter'	=> sanitize_text_field( $row['type'] ),
			'time'		=> (int) $row['snaptime'],
			'comment'	=> sanitize_text_field( $row['comment'] ),
			'summary'	=> self::clean_details( $stats )
		);
	}

	/**
	 * Snapshots saved by 4.x stored a different summary layout.
	 */
	private static function convert_legacy_snapshot( $stats, $reports )
	{
		$converted = array(
			'format'			=> 1,
			'pages'				=> null,
			'score'				=> isset( $stats['score'] ) ? (int) $stats['score'] : null,
			'categories'		=> array(),
			'distribution'		=> array(),
			'metrics'			=> array(),
			'issues'			=> array(),
			'categoryIssues'	=> array(),
			'best'				=> array(),
			'worst'				=> array(),
			'trend'				=> array()
		);

		if ( null !== $converted['score'] ) {
			$converted['categories']['performance'] = array( 'title' => __( 'Performance', 'google-pagespeed-insights' ), 'score' => $converted['score'] );
		}

		foreach ( array( 'highest' => 'best', 'lowest' => 'worst' ) as $old => $new ) {
			if ( ! empty( $stats['page_scores'][ $old ] ) ) {
				foreach ( $stats['page_scores'][ $old ] as $page ) {
					$converted[ $new ][] = array( 'url' => isset( $page['page_url'] ) ? esc_url_raw( $page['page_url'] ) : '', 'score' => isset( $page['score'] ) ? (int) $page['score'] : null );
				}
			}
		}

		if ( is_array( $reports ) ) {
			foreach ( $reports as $key => $report ) {
				$converted['issues'][] = array(
					'id'	=> sanitize_key( $key ),
					'title'	=> isset( $report['rule_name'] ) ? wp_strip_all_tags( $report['rule_name'] ) : $key,
					'pages'	=> isset( $report['occurances'] ) ? (int) $report['occurances'] : 0,
					'score'	=> isset( $report['avg_score'] ) ? (int) $report['avg_score'] : null
				);
			}
		}

		return $converted;
	}

	public static function delete_snapshots( $ids )
	{
		global $wpdb;

		$count = 0;
		foreach ( array_filter( array_map( 'intval', (array) $ids ) ) as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
			$count += (int) $wpdb->delete( self::table( 'summary_snapshots' ), array( 'ID' => $id ), array( '%d' ) );
		}

		return $count;
	}

	/* ---------------------------------------------------------------------
	 * Custom URLs
	 * ------------------------------------------------------------------- */

	public static function get_custom_urls()
	{
		global $wpdb;

		$urls_table = self::table( 'custom_urls' );
		$stats_table = self::table( 'page_stats' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin tables; table names are $wpdb->prefix plus a fixed string.
		$rows = $wpdb->get_results( "SELECT c.ID, c.URL, c.type, s.ID AS report_id FROM $urls_table c LEFT JOIN $stats_table s ON s.custom_id = c.ID ORDER BY c.ID DESC", ARRAY_A );

		$urls = array();
		foreach ( (array) $rows as $row ) {
			$urls[] = array(
				'id'		=> (int) $row['ID'],
				'url'		=> esc_url_raw( $row['URL'] ),
				'label'		=> sanitize_text_field( $row['type'] ),
				'reportId'	=> $row['report_id'] ? (int) $row['report_id'] : null
			);
		}

		return $urls;
	}

	public static function sanitize_label( $label )
	{
		$label = substr( sanitize_title( $label ), 0, 40 );

		return '' === $label ? 'custom' : $label;
	}

	/**
	 * Adds URLs (http/https only) that are not tracked yet. Returns array( added, skipped, invalid ).
	 */
	public static function add_custom_urls( $urls, $label )
	{
		global $wpdb;

		$label = self::sanitize_label( $label );
		$urls_table = self::table( 'custom_urls' );
		$stats_table = self::table( 'page_stats' );
		$added = 0;
		$skipped = 0;
		$invalid = 0;

		foreach ( array_unique( (array) $urls ) as $url ) {
			$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
			$host = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';

			// Google can only check public URLs, so the host needs a domain (or IP address) with a dot.
			if ( empty( $host ) || false === strpos( $host, '.' ) ) {
				$invalid++;
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin tables; table names are $wpdb->prefix plus a fixed string.
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT ( SELECT COUNT(*) FROM $urls_table WHERE URL = %s ) + ( SELECT COUNT(*) FROM $stats_table WHERE URL = %s )", $url, $url ) );

			if ( $exists ) {
				$skipped++;
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin table; WordPress has no API for it.
			$wpdb->insert( $urls_table, array( 'URL' => $url, 'type' => $label ), array( '%s', '%s' ) );
			$added++;
		}

		if ( $added ) {
			do_action( 'run_gpi', false );
		}

		return array( 'added' => $added, 'skipped' => $skipped, 'invalid' => $invalid );
	}

	/**
	 * Reads the <loc> URLs of an uploaded sitemap file. The file is read from PHP's temporary
	 * upload and is not stored; XML is not an upload type WordPress allows by default.
	 */
	public static function import_sitemap( $file, $label )
	{
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'gpi_upload', __( 'There was a problem uploading the sitemap', 'google-pagespeed-insights' ) );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		if ( 'xml' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'gpi_upload', __( 'Please upload an XML sitemap file (.xml).', 'google-pagespeed-insights' ) );
		}

		if ( filesize( $file['tmp_name'] ) > 10 * MB_IN_BYTES ) {
			return new WP_Error( 'gpi_upload', __( 'The sitemap file is too large. The limit is 10 MB.', 'google-pagespeed-insights' ) );
		}

		$dom = new DOMDocument();
		$loaded = @$dom->load( $file['tmp_name'], LIBXML_NONET ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid XML is reported to the user below.

		if ( ! $loaded ) {
			return new WP_Error( 'gpi_upload', __( 'The sitemap could not be read. Please upload a valid XML sitemap.', 'google-pagespeed-insights' ) );
		}

		if ( $dom->documentElement && 'sitemapindex' === $dom->documentElement->localName ) {
			return new WP_Error( 'gpi_upload', __( 'This file is a sitemap index. Upload one of the sitemaps it lists instead.', 'google-pagespeed-insights' ) );
		}

		$urls = array();
		foreach ( $dom->getElementsByTagName( 'url' ) as $node ) {
			$loc = $node->getElementsByTagName( 'loc' )->item( 0 );
			if ( $loc ) {
				$urls[] = trim( $loc->nodeValue );
			}
		}

		if ( empty( $urls ) ) {
			return new WP_Error( 'gpi_upload', __( 'No URLs were found in the sitemap.', 'google-pagespeed-insights' ) );
		}

		if ( count( $urls ) > 5000 ) {
			/* translators: %d: maximum number of URLs */
			return new WP_Error( 'gpi_upload', sprintf( __( 'The sitemap has too many URLs. Import up to %d at a time.', 'google-pagespeed-insights' ), 5000 ) );
		}

		return self::add_custom_urls( $urls, $label );
	}

	public static function delete_custom_urls( $ids )
	{
		global $wpdb;

		$count = 0;
		$stats_table = self::table( 'page_stats' );

		foreach ( array_filter( array_map( 'intval', (array) $ids ) ) as $id ) {
			$report_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $stats_table WHERE custom_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.

			if ( $report_id ) {
				self::delete_reports( array( $report_id ) );
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
			$wpdb->delete( self::table( 'page_blacklist' ), array( 'custom_id' => $id ), array( '%d' ) );
			$count += (int) $wpdb->delete( self::table( 'custom_urls' ), array( 'ID' => $id ), array( '%d' ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $count;
	}

	/* ---------------------------------------------------------------------
	 * Ignored URLs
	 * ------------------------------------------------------------------- */

	public static function get_ignored()
	{
		global $wpdb;

		$table = self::table( 'page_blacklist' );
		$rows = $wpdb->get_results( "SELECT ID, URL, type FROM $table ORDER BY ID DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.

		$ignored = array();
		foreach ( (array) $rows as $row ) {
			$ignored[] = array(
				'id'	=> (int) $row['ID'],
				'url'	=> esc_url_raw( $row['URL'] ),
				'type'	=> sanitize_text_field( $row['type'] )
			);
		}

		return $ignored;
	}

	public static function reactivate( $ids )
	{
		$count = self::delete_ignored( $ids );

		if ( $count ) {
			do_action( 'run_gpi', false );
		}

		return $count;
	}

	public static function delete_ignored( $ids )
	{
		global $wpdb;

		$count = 0;
		foreach ( array_filter( array_map( 'intval', (array) $ids ) ) as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
			$count += (int) $wpdb->delete( self::table( 'page_blacklist' ), array( 'ID' => $id ), array( '%d' ) );
		}

		return $count;
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	public static function settings_choices()
	{
		return array(
			'strategy'				=> array( 'desktop', 'mobile', 'both' ),
			'heartbeat'				=> array( 'fast', 'standard', 'slow', 'disabled' ),
			'recheck_interval'		=> array( DAY_IN_SECONDS, WEEK_IN_SECONDS, MONTH_IN_SECONDS / 2, MONTH_IN_SECONDS ),
			'max_execution_time'	=> array( 60, 300, 600, 900, 1800 ),
			'max_run_time'			=> array( 0, 15, 30, 45, 60, 90, 120, 150, 180 ),
			'sleep_time'			=> array( 0, 1, 2, 3, 4, 5, 10 )
		);
	}

	public static function get_settings()
	{
		$options = self::options();
		$timestamp = wp_next_scheduled( 'googlepagespeedinsightsworker' );

		$post_types = array();
		foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $post_type ) {
			$post_types[] = array(
				'value'	=> $post_type->name,
				'label'	=> $post_type->labels->name,
				'count'	=> (int) wp_count_posts( $post_type->name )->publish
			);
		}

		return array(
			'google_developer_key'	=> isset( $options['google_developer_key'] ) ? (string) $options['google_developer_key'] : '',
			'response_language'		=> isset( $options['response_language'] ) ? (string) $options['response_language'] : 'en_US',
			'strategy'				=> isset( $options['strategy'] ) ? (string) $options['strategy'] : 'desktop',
			'store_screenshots'		=> ! empty( $options['store_screenshots'] ),
			'use_schedule'			=> ! empty( $options['use_schedule'] ),
			'recheck_interval'		=> isset( $options['recheck_interval'] ) ? (int) $options['recheck_interval'] : DAY_IN_SECONDS,
			'check_pages'			=> ! empty( $options['check_pages'] ),
			'check_posts'			=> ! empty( $options['check_posts'] ),
			'check_categories'		=> ! empty( $options['check_categories'] ),
			'check_custom_urls'		=> ! empty( $options['check_custom_urls'] ),
			'cpt_whitelist'			=> self::custom_post_type_whitelist(),
			'max_execution_time'	=> isset( $options['max_execution_time'] ) ? (int) $options['max_execution_time'] : 300,
			'max_run_time'			=> isset( $options['max_run_time'] ) ? (int) $options['max_run_time'] : 0,
			'sleep_time'			=> isset( $options['sleep_time'] ) ? (int) $options['sleep_time'] : 0,
			'heartbeat'				=> isset( $options['heartbeat'] ) ? (string) $options['heartbeat'] : 'standard',
			'log_api_errors'		=> ! empty( $options['log_api_errors'] ),
			'counts'				=> array(
				'pages'			=> (int) wp_count_posts( 'page' )->publish,
				'posts'			=> (int) wp_count_posts( 'post' )->publish,
				'categories'	=> count( get_categories() ),
				'custom_urls'	=> count( self::get_custom_urls() )
			),
			'post_types'			=> $post_types,
			'next_scheduled'		=> $timestamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'Y-m-d H:i' ) : '',
			'cron_disabled'			=> defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'set_time_limit_disabled' => in_array( 'set_time_limit', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ),
			'server_max_execution_time' => (int) ini_get( 'max_execution_time' )
		);
	}

	/**
	 * Saves settings coming from the settings screen. Unknown values fall back to safe defaults.
	 */
	public static function save_settings( $input )
	{
		$old = self::options();
		$choices = self::settings_choices();

		$pick = function ( $key, $default ) use ( $input, $choices ) {
			if ( ! isset( $input[ $key ] ) ) {
				return $default;
			}
			$value = is_numeric( $default ) ? (int) $input[ $key ] : sanitize_text_field( $input[ $key ] );
			return in_array( $value, $choices[ $key ], true ) ? $value : $default;
		};

		$key = isset( $input['google_developer_key'] ) ? sanitize_text_field( $input['google_developer_key'] ) : ( isset( $old['google_developer_key'] ) ? $old['google_developer_key'] : '' );

		$new = array_merge( $old, array(
			'google_developer_key'	=> $key,
			'response_language'		=> isset( $input['response_language'] ) ? sanitize_text_field( $input['response_language'] ) : ( isset( $old['response_language'] ) ? $old['response_language'] : 'en_US' ),
			'strategy'				=> $pick( 'strategy', 'desktop' ),
			'store_screenshots'		=> ! empty( $input['store_screenshots'] ),
			'max_execution_time'	=> $pick( 'max_execution_time', 300 ),
			'max_run_time'			=> $pick( 'max_run_time', 0 ),
			'sleep_time'			=> $pick( 'sleep_time', 0 ),
			'recheck_interval'		=> $pick( 'recheck_interval', DAY_IN_SECONDS ),
			'use_schedule'			=> ! empty( $input['use_schedule'] ),
			'check_pages'			=> ! empty( $input['check_pages'] ),
			'check_posts'			=> ! empty( $input['check_posts'] ),
			'cpt_whitelist'			=> ! empty( $input['cpt_whitelist'] ) ? serialize( array_values( array_map( 'sanitize_key', (array) $input['cpt_whitelist'] ) ) ) : false,
			'check_categories'		=> ! empty( $input['check_categories'] ),
			'check_custom_urls'		=> ! empty( $input['check_custom_urls'] ),
			'bad_api_key'			=> false,
			'pagespeed_disabled'	=> false,
			'api_restriction'		=> false,
			'quota_exceeded'		=> false,
			'new_ignored_items'		=> false,
			'backend_error'			=> false,
			'check_logs'			=> false,
			'log_api_errors'		=> ! empty( $input['log_api_errors'] ),
			'new_activation_message' => false,
			'heartbeat'				=> $pick( 'heartbeat', 'standard' ),
			'version'				=> GPI_VERSION
		) );

		update_option( 'gpagespeedi_options', $new );

		$ui = get_option( 'gpagespeedi_ui_options' );
		$ui = is_array( $ui ) ? $ui : array();
		if ( 'both' !== $new['strategy'] ) {
			$ui['view_preference'] = $new['strategy'];
			update_option( 'gpagespeedi_ui_options', $ui );
		}

		$interval_changed = ! isset( $old['recheck_interval'] ) || (int) $old['recheck_interval'] !== (int) $new['recheck_interval'];

		if ( $new['use_schedule'] && ! wp_next_scheduled( 'googlepagespeedinsightsworker' ) ) {
			wp_schedule_event( time() + $new['recheck_interval'], 'gpi_scheduled_interval', 'googlepagespeedinsightsworker' );
		} else if ( $new['use_schedule'] && $interval_changed ) {
			wp_clear_scheduled_hook( 'googlepagespeedinsightsworker' );
			wp_schedule_event( time() + $new['recheck_interval'], 'gpi_scheduled_interval', 'googlepagespeedinsightsworker' );
		} else if ( ! $new['use_schedule'] ) {
			wp_clear_scheduled_hook( 'googlepagespeedinsightsworker' );
		}

		return self::get_settings();
	}

	/**
	 * "Delete Data" from the settings screen: 'reports' or 'everything'.
	 */
	public static function purge( $type )
	{
		global $wpdb;

		$tables = array( 'page_stats', 'page_reports', 'score_history' );
		if ( 'everything' === $type ) {
			$tables = array_merge( $tables, array( 'page_blacklist', 'api_error_logs', 'custom_urls', 'summary_snapshots' ) );
		} else if ( 'reports' !== $type ) {
			return false;
		}

		foreach ( $tables as $name ) {
			$table = self::table( $name );
			$wpdb->query( "TRUNCATE TABLE $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string.
		}

		if ( 'everything' === $type ) {
			do_action( 'gpi_truncate_custom_tables' );
		}

		// Scan state that points at the deleted pages; a running scan still needs it.
		if ( ! apply_filters( 'gpi_check_status', false ) ) {
			foreach ( array( 'gpi_progress', 'gpi_recheck_urls', 'gpi_missed_url_groups', 'gpi_error_message' ) as $option ) {
				delete_option( $option );
			}
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Logs and status
	 * ------------------------------------------------------------------- */

	public static function get_logs()
	{
		global $wpdb;

		$table = self::table( 'api_error_logs' );
		$rows = $wpdb->get_results( "SELECT URL, strategy, is_update, type, timestamp, error FROM $table ORDER BY timestamp DESC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.

		$logs = array();
		foreach ( (array) $rows as $row ) {
			$errors = maybe_unserialize( $row['error'] );
			$messages = array();

			if ( is_array( $errors ) ) {
				foreach ( $errors as $error ) {
					$error = (array) $error;
					$messages[] = array(
						'reason'	=> isset( $error['reason'] ) ? sanitize_text_field( $error['reason'] ) : '',
						'message'	=> isset( $error['message'] ) ? sanitize_text_field( $error['message'] ) : ''
					);
				}
			} else if ( is_string( $errors ) && '' !== $errors ) {
				$messages[] = array( 'reason' => '', 'message' => sanitize_text_field( $errors ) );
			}

			$logs[] = array(
				'url'		=> esc_url_raw( $row['URL'] ),
				'strategy'	=> sanitize_key( $row['strategy'] ),
				'update'	=> (bool) $row['is_update'],
				'type'		=> sanitize_text_field( $row['type'] ),
				'time'		=> (int) $row['timestamp'],
				'errors'	=> $messages
			);
		}

		return $logs;
	}

	/**
	 * Scan state for the progress indicator.
	 */
	/**
	 * Scans run in a loopback request to the site itself. The request removes its one-time
	 * token when it arrives, so a token that is still there after LOOPBACK_TIMEOUT seconds
	 * means the request was blocked (maintenance mode, a login wall, the host, ...).
	 * Returns 'pending', 'failed' or ''.
	 */
	public static function loopback_state( $running = null )
	{
		$requested = (int) get_option( 'gpi_check_now_time' );

		if ( ! $requested || ! get_option( 'gpi_check_now' ) ) {
			return '';
		}

		if ( null === $running ) {
			$running = (bool) apply_filters( 'gpi_check_status', false );
		}

		if ( $running ) {
			return '';
		}

		return time() - $requested < self::LOOPBACK_TIMEOUT ? 'pending' : 'failed';
	}

	public static function get_status()
	{
		$progress = get_option( 'gpi_progress' );
		$running = (bool) apply_filters( 'gpi_check_status', false );
		$starting = 'pending' === self::loopback_state( $running );
		$done = 0;
		$total = 0;

		if ( is_string( $progress ) && false !== strpos( $progress, ' / ' ) ) {
			list( $done, $total ) = array_map( 'intval', explode( ' / ', $progress ) );
		}

		return array(
			'running'	=> $running || $starting,
			'starting'	=> $starting,
			'aborting'	=> (bool) get_option( 'gpi_abort_scan' ),
			'aborted'	=> 'abort' === $progress,
			'done'		=> $done,
			'total'		=> $total
		);
	}

	/**
	 * Notices for the app header. One-time notices are cleared once read.
	 */
	public static function get_notices()
	{
		$options = self::options();
		$notices = array();

		$logging = ! empty( $options['log_api_errors'] );

		// Flag => array( status, message, screen the notice links to ).
		$map = array(
			'bad_api_key'			=> array( 'error', __( 'The Google PageSpeed API key you entered appears to be invalid. Please update it in the Settings.', 'google-pagespeed-insights' ), 'settings' ),
			'pagespeed_disabled'	=> array( 'error', __( 'The "PageSpeed Insights API" service is not enabled for your API key. Enable it in the Google Cloud Console.', 'google-pagespeed-insights' ), 'settings' ),
			'api_restriction'		=> array( 'error', __( 'This referrer or IP address is not allowed to use your API key. Check the key restrictions in the Google Cloud Console.', 'google-pagespeed-insights' ), 'settings' ),
			'quota_exceeded'		=> array( 'warning', __( 'The daily Google PageSpeed API quota of your key is used up, so the scan stopped. The quota resets at midnight Pacific Time; start the scan again after that, and the remaining pages are checked.', 'google-pagespeed-insights' ), '' ),
			'backend_error'			=> array( 'warning', __( 'Some URLs could not be checked (daily API limit reached, or the URL is not publicly accessible). They were added to the Ignored URLs.', 'google-pagespeed-insights' ), 'ignored' ),
			'new_ignored_items'		=> array( 'warning', __( 'One or more URLs could not be reached by Google PageSpeed and were added to the Ignored URLs.', 'google-pagespeed-insights' ), 'ignored' ),
			'check_logs'			=> $logging
				? array( 'error', __( 'The Google PageSpeed API returned an error. The Logs show the details.', 'google-pagespeed-insights' ), 'logs' )
				: array( 'error', __( 'The Google PageSpeed API returned an error. Enable "Log API errors" in the Settings and check the Logs for details.', 'google-pagespeed-insights' ), 'settings' )
		);

		foreach ( $map as $flag => $notice ) {
			if ( ! empty( $options[ $flag ] ) ) {
				$notices[] = array( 'id' => $flag, 'status' => $notice[0], 'message' => $notice[1], 'action' => $notice[2] );
			}
		}

		if ( isset( $options['last_run_finished'] ) && ! $options['last_run_finished'] && ! apply_filters( 'gpi_check_status', false ) ) {
			$notices[] = array( 'id' => 'last_run_finished', 'status' => 'warning', 'message' => __( 'The last scan did not finish. If this keeps happening, raise the Maximum Execution Time or set a Maximum Script Run Time in the Settings.', 'google-pagespeed-insights' ), 'action' => 'settings' );
		}

		if ( 'failed' === self::loopback_state() ) {
			$notices[] = array(
				'id'		=> 'loopback_failed',
				'status'	=> 'error',
				'message'	=> __( 'The scan could not start. Scans run in the background through a request from your site to itself (a loopback request), and that request was blocked. Common causes are a maintenance or "coming soon" mode, a plugin that requires a login for the whole site, or a host that blocks such requests. Site Health shows whether loopback requests work.', 'google-pagespeed-insights' ),
				'action'	=> 'site_health'
			);
		}

		$error_message = get_option( 'gpi_error_message' );
		if ( $error_message ) {
			$notices[] = array( 'id' => 'gpi_error_message', 'status' => 'info', 'message' => sanitize_text_field( html_entity_decode( $error_message, ENT_QUOTES, 'UTF-8' ) ) );
		}

		return $notices;
	}

	/**
	 * Clears a notice the user dismissed.
	 */
	public static function dismiss_notice( $id )
	{
		$flags = array( 'bad_api_key', 'pagespeed_disabled', 'api_restriction', 'quota_exceeded', 'backend_error', 'new_ignored_items', 'check_logs', 'last_run_finished' );

		if ( 'gpi_error_message' === $id ) {
			delete_option( 'gpi_error_message' );
			return true;
		}

		if ( 'loopback_failed' === $id ) {
			delete_option( 'gpi_check_now_time' );
			return true;
		}

		if ( in_array( $id, $flags, true ) ) {
			do_action( 'gpi_update_option', $id, 'last_run_finished' === $id, 'gpagespeedi_options' );
			return true;
		}

		return false;
	}
}
