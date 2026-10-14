<?php
/**
 * =======================================
 * Google Pagespeed Insights REST API
 * =======================================
 *
 * Endpoints used by the admin app (namespace gpi/v1). Every route requires manage_options,
 * the same capability as the admin page, and the standard wp_rest nonce sent by apiFetch.
 *
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	die();
}

class GPI_REST
{
	const NAMESPACE_V1 = 'gpi/v1';

	public function init()
	{
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function can_manage()
	{
		return current_user_can( 'manage_options' );
	}

	private function route( $path, $methods, $callback, $args = array() )
	{
		register_rest_route( self::NAMESPACE_V1, $path, array(
			'methods'				=> $methods,
			'callback'				=> array( $this, $callback ),
			'permission_callback'	=> array( $this, 'can_manage' ),
			'args'					=> $args
		) );
	}

	private function arg_strategy()
	{
		return array(
			'type'		=> 'string',
			'enum'		=> array( 'desktop', 'mobile' ),
			'default'	=> 'desktop'
		);
	}

	private function arg_filter()
	{
		return array(
			'type'				=> 'string',
			'default'			=> 'all',
			'sanitize_callback'	=> 'sanitize_text_field'
		);
	}

	private function arg_ids()
	{
		return array(
			'type'		=> 'array',
			'items'		=> array( 'type' => 'integer', 'minimum' => 1 ),
			'required'	=> true,
			'minItems'	=> 1,
			'maxItems'	=> 1000
		);
	}

	public function register_routes()
	{
		$this->route( '/app', 'GET', 'get_app' );
		$this->route( '/status', 'GET', 'get_status' );
		$this->route( '/scan', 'POST', 'start_scan', array(
			'recheck'	=> array( 'type' => 'boolean', 'default' => false )
		) );
		$this->route( '/scan/abort', 'POST', 'abort_scan' );

		$this->route( '/reports', 'GET', 'get_reports', array(
			'strategy'	=> $this->arg_strategy(),
			'filter'	=> $this->arg_filter(),
			'search'	=> array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'orderby'	=> array( 'type' => 'string', 'enum' => array( 'score', 'url', 'type', 'last_checked' ), 'default' => 'score' ),
			'order'		=> array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'asc' ),
			'page'		=> array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
			'per_page'	=> array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 25 )
		) );
		$this->route( '/reports/(?P<id>\d+)', 'GET', 'get_report', array(
			'id'		=> array( 'type' => 'integer', 'minimum' => 1, 'required' => true ),
			'strategy'	=> $this->arg_strategy()
		) );
		$this->route( '/reports/batch', 'POST', 'batch_reports', array(
			'action'	=> array( 'type' => 'string', 'enum' => array( 'recheck', 'ignore', 'delete' ), 'required' => true ),
			'ids'		=> $this->arg_ids()
		) );

		$this->route( '/summary', 'GET', 'get_summary', array(
			'strategy'	=> $this->arg_strategy(),
			'filter'	=> $this->arg_filter()
		) );

		$this->route( '/snapshots', 'GET', 'get_snapshots' );
		$this->route( '/snapshots', 'POST', 'create_snapshot', array(
			'strategy'	=> $this->arg_strategy(),
			'filter'	=> $this->arg_filter(),
			'comment'	=> array( 'type' => 'string', 'default' => '', 'maxLength' => 200, 'sanitize_callback' => 'sanitize_text_field' )
		) );
		$this->route( '/snapshots/(?P<id>\d+)', 'GET', 'get_snapshot', array(
			'id'	=> array( 'type' => 'integer', 'minimum' => 1, 'required' => true )
		) );
		$this->route( '/snapshots/delete', 'POST', 'delete_snapshots', array( 'ids' => $this->arg_ids() ) );

		$this->route( '/custom-urls', 'GET', 'get_custom_urls' );
		$this->route( '/custom-urls', 'POST', 'add_custom_urls', array(
			'urls'	=> array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'required' => true, 'minItems' => 1, 'maxItems' => 500 ),
			'label'	=> array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' )
		) );
		$this->route( '/custom-urls/sitemap', 'POST', 'import_sitemap', array(
			'label'	=> array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' )
		) );
		$this->route( '/custom-urls/delete', 'POST', 'delete_custom_urls', array( 'ids' => $this->arg_ids() ) );

		$this->route( '/ignored', 'GET', 'get_ignored' );
		$this->route( '/ignored/reactivate', 'POST', 'reactivate_ignored', array( 'ids' => $this->arg_ids() ) );
		$this->route( '/ignored/delete', 'POST', 'delete_ignored', array( 'ids' => $this->arg_ids() ) );

		$this->route( '/settings', 'GET', 'get_settings' );
		$this->route( '/settings', 'POST', 'save_settings' );
		$this->route( '/settings/check-key', 'POST', 'check_key', array(
			'key'	=> array( 'type' => 'string', 'required' => true, 'minLength' => 1, 'maxLength' => 200, 'validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'sanitize_text_field' )
		) );
		$this->route( '/settings/purge', 'POST', 'purge', array(
			'type'	=> array( 'type' => 'string', 'enum' => array( 'reports', 'everything' ), 'required' => true )
		) );

		$this->route( '/logs', 'GET', 'get_logs' );
		$this->route( '/notices/dismiss', 'POST', 'dismiss_notice', array(
			'id'	=> array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' )
		) );
		$this->route( '/preferences', 'POST', 'save_preferences', array(
			'view_preference'	=> $this->arg_strategy()
		) );
	}

	/* ------------------------------------------------------------------- */

	public function get_app()
	{
		global $wpdb;

		$options = GPI_Data::options();
		$ui = get_option( 'gpagespeedi_ui_options' );
		$view = isset( $ui['view_preference'] ) ? GPI_Data::strategy( $ui['view_preference'] ) : 'desktop';
		$enabled = GPI_Data::enabled_strategies();

		if ( ! in_array( $view, $enabled, true ) ) {
			$view = $enabled[0];
		}

		$count = function ( $name ) use ( $wpdb ) {
			$table = GPI_Data::table( $name );
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string; custom plugin table.
		};

		return rest_ensure_response( array(
			'hasKey'		=> ! empty( $options['google_developer_key'] ),
			'strategies'	=> $enabled,
			'view'			=> $view,
			'filters'		=> GPI_Data::filter_options(),
			'status'		=> GPI_Data::get_status(),
			'notices'		=> GPI_Data::get_notices(),
			'counts'		=> array(
				'reports'		=> $count( 'page_stats' ),
				'customUrls'	=> $count( 'custom_urls' ),
				'ignored'		=> $count( 'page_blacklist' ),
				'snapshots'		=> $count( 'summary_snapshots' ),
				'logs'			=> $count( 'api_error_logs' )
			),
			'logging'		=> ! empty( $options['log_api_errors'] ),
			'heartbeat'		=> isset( $options['heartbeat'] ) ? sanitize_key( $options['heartbeat'] ) : 'standard',
			'upgradeRecheck' => (bool) get_option( 'gpagespeedi_upgrade_recheck_required' )
		) );
	}

	public function get_status()
	{
		return rest_ensure_response( GPI_Data::get_status() );
	}

	public function start_scan( $request )
	{
		$options = GPI_Data::options();

		if ( empty( $options['google_developer_key'] ) ) {
			return new WP_Error( 'gpi_no_key', __( 'Enter your Google API key in the Settings before starting a scan.', 'google-pagespeed-insights' ), array( 'status' => 400 ) );
		}

		if ( apply_filters( 'gpi_check_status', false ) ) {
			return new WP_Error( 'gpi_busy', __( 'A scan is already running.', 'google-pagespeed-insights' ), array( 'status' => 409 ) );
		}

		delete_option( 'gpi_abort_scan' );
		delete_option( 'gpi_error_message' );
		delete_option( 'gpagespeedi_upgrade_recheck_required' );
		do_action( 'run_gpi', (bool) $request['recheck'] );

		return rest_ensure_response( array( 'started' => true, 'status' => GPI_Data::get_status() ) );
	}

	public function abort_scan()
	{
		add_option( 'gpi_abort_scan', true, '', false );

		// Stopped before the background request arrived: stop waiting for it.
		if ( 'pending' === GPI_Data::loopback_state() ) {
			delete_option( 'gpi_check_now_time' );
		}

		return rest_ensure_response( GPI_Data::get_status() );
	}

	public function get_reports( $request )
	{
		return rest_ensure_response( GPI_Data::get_reports( array(
			'strategy'	=> $request['strategy'],
			'filter'	=> $request['filter'],
			'search'	=> $request['search'],
			'orderby'	=> $request['orderby'],
			'order'		=> $request['order'],
			'page'		=> $request['page'],
			'per_page'	=> $request['per_page']
		) ) );
	}

	public function get_report( $request )
	{
		$report = GPI_Data::get_report( (int) $request['id'], $request['strategy'] );

		if ( ! $report ) {
			return new WP_Error( 'gpi_not_found', __( 'Report not found.', 'google-pagespeed-insights' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $report );
	}

	public function batch_reports( $request )
	{
		switch ( $request['action'] ) {
			case 'recheck':
				$count = GPI_Data::recheck_reports( $request['ids'] );
				break;

			case 'ignore':
				$count = GPI_Data::ignore_reports( $request['ids'] );
				break;

			default:
				$count = GPI_Data::delete_reports( $request['ids'] );
				break;
		}

		return rest_ensure_response( array( 'count' => $count ) );
	}

	public function get_summary( $request )
	{
		return rest_ensure_response( GPI_Data::get_summary( $request['strategy'], $request['filter'] ) );
	}

	public function get_snapshots()
	{
		return rest_ensure_response( GPI_Data::get_snapshots() );
	}

	public function create_snapshot( $request )
	{
		$id = GPI_Data::create_snapshot( $request['strategy'], $request['filter'], $request['comment'] );

		if ( ! $id ) {
			return new WP_Error( 'gpi_snapshot', __( 'The snapshot could not be saved.', 'google-pagespeed-insights' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'id' => $id ) );
	}

	public function get_snapshot( $request )
	{
		$snapshot = GPI_Data::get_snapshot( (int) $request['id'] );

		if ( ! $snapshot ) {
			return new WP_Error( 'gpi_not_found', __( 'Snapshot not found.', 'google-pagespeed-insights' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $snapshot );
	}

	public function delete_snapshots( $request )
	{
		return rest_ensure_response( array( 'count' => GPI_Data::delete_snapshots( $request['ids'] ) ) );
	}

	public function get_custom_urls()
	{
		return rest_ensure_response( GPI_Data::get_custom_urls() );
	}

	public function add_custom_urls( $request )
	{
		return rest_ensure_response( GPI_Data::add_custom_urls( $request['urls'], $request['label'] ) );
	}

	public function import_sitemap( $request )
	{
		$files = $request->get_file_params();

		if ( empty( $files['sitemap'] ) || ! is_array( $files['sitemap'] ) ) {
			return new WP_Error( 'gpi_upload', __( 'Please choose a sitemap file to upload.', 'google-pagespeed-insights' ), array( 'status' => 400 ) );
		}

		$result = GPI_Data::import_sitemap( $files['sitemap'], $request['label'] );

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	public function delete_custom_urls( $request )
	{
		return rest_ensure_response( array( 'count' => GPI_Data::delete_custom_urls( $request['ids'] ) ) );
	}

	public function get_ignored()
	{
		return rest_ensure_response( GPI_Data::get_ignored() );
	}

	public function reactivate_ignored( $request )
	{
		return rest_ensure_response( array( 'count' => GPI_Data::reactivate( $request['ids'] ) ) );
	}

	public function delete_ignored( $request )
	{
		return rest_ensure_response( array( 'count' => GPI_Data::delete_ignored( $request['ids'] ) ) );
	}

	public function get_settings()
	{
		return rest_ensure_response( GPI_Data::get_settings() );
	}

	public function save_settings( $request )
	{
		if ( apply_filters( 'gpi_check_status', false ) ) {
			return new WP_Error( 'gpi_busy', __( 'Settings cannot be changed while a scan is running.', 'google-pagespeed-insights' ), array( 'status' => 409 ) );
		}

		$input = $request->get_json_params();

		return rest_ensure_response( GPI_Data::save_settings( is_array( $input ) ? $input : array() ) );
	}

	public function check_key( $request )
	{
		if ( ! class_exists( 'GPI_Pagespeed_API' ) ) {
			require_once GPI_DIRECTORY . '/classes/class-GPI-Pagespeed-API.php';
		}

		$key = trim( $request['key'] );
		if ( '' === $key ) {
			return new WP_Error( 'gpi_key', __( 'Please enter an API key.', 'google-pagespeed-insights' ), array( 'status' => 400 ) );
		}

		$api = new GPI_Pagespeed_API( $key );

		return rest_ensure_response( $api->check_key() );
	}

	public function purge( $request )
	{
		if ( apply_filters( 'gpi_check_status', false ) ) {
			return new WP_Error( 'gpi_busy', __( 'Data cannot be deleted while a scan is running.', 'google-pagespeed-insights' ), array( 'status' => 409 ) );
		}

		return rest_ensure_response( array( 'purged' => GPI_Data::purge( $request['type'] ) ) );
	}

	public function get_logs()
	{
		return rest_ensure_response( GPI_Data::get_logs() );
	}

	public function dismiss_notice( $request )
	{
		return rest_ensure_response( array( 'dismissed' => GPI_Data::dismiss_notice( $request['id'] ) ) );
	}

	public function save_preferences( $request )
	{
		do_action( 'gpi_update_option', 'view_preference', GPI_Data::strategy( $request['view_preference'] ), 'gpagespeedi_ui_options' );

		return rest_ensure_response( array( 'view' => GPI_Data::strategy( $request['view_preference'] ) ) );
	}
}

add_action( 'plugins_loaded', array( new GPI_REST, 'init' ) );
