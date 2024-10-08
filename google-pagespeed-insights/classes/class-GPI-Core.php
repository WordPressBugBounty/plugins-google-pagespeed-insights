<?php
/**
 * =======================================
 * Google Pagespeed Insights Core
 * =======================================
 * 
 * 
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	die();
}

class GPI_Core
{
	var $gpi_options;
	var $gpi_ui_options;
	static $last_scan_finished = false;
	static $force_recheck_urls = false;
	static $exceeded_runtime = false;
	static $skipped_all = true;

	public function __construct()
	{
		$this->gpi_options = get_option('gpagespeedi_options');
		$this->gpi_ui_options = get_option('gpagespeedi_ui_options');
	}

	public function init()
	{
		add_action( 'init', array( $this, 'trigger_gpi' ), 9999 );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'gpi_prune_logs', array( $this, 'prune_logs') );
		add_action( 'googlepagespeedinsightsworker', array( $this, 'googlepagespeedinsightsworker'), 10, 2 );
		add_action( 'gpi_update_option', array( $this, 'update_option' ), 10, 3 );
		add_filter( 'gpi_check_status', array( $this, 'check_status' ), 10, 1 );
		add_action( 'run_gpi', array( $this, 'run_gpi' ), 10, 3 );

		if ( ! wp_next_scheduled( 'gpi_prune_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'gpi_prune_logs' );
		}
	}

	public function trigger_gpi()
	{
		if ( ! isset( $_GET['gpi_check_now'] ) ) {
			return;
		}

		if ( ! get_option( 'gpi_check_now' ) ) {
			return;
		}

		delete_option( 'gpi_check_now' );

		$force_recheck_all_urls = isset( $_GET['recheck'] ) ? true : false;
		$timeout_respawn = isset( $_GET['timeout'] ) ? true : false;
		$urls_provided = isset( $_GET['urls_provided'] ) ? true : false;

		if ( $urls_provided ) {
			$urls_to_check = get_option( 'gpi_recheck_urls', array() );
		} else {
			$urls_to_check = array();
		}

		$this->googlepagespeedinsightsworker( $urls_to_check, $force_recheck_all_urls, $timeout_respawn );
	}

	public function cron_schedules( $schedules )
	{
		if ( ! isset( $schedules['gpi_scheduled_interval'] ) ) {
			$schedules['gpi_scheduled_interval'] = array(
				'interval'	=> intval( $this->gpi_options['recheck_interval'] ),
				'display'	=> esc_html__( 'Interval set in GPI options', 'gpagespeedi' )
			);
		}

		return $schedules;
	}

	public function prune_logs()
	{
		global $wpdb;

		$gpi_api_error_logs = $wpdb->prefix . 'gpi_api_error_logs';
		$compare_time = current_time( 'timestamp' ) - WEEK_IN_SECONDS;

		$wpdb->query(
			"
			DELETE
			FROM $gpi_api_error_logs
			WHERE timestamp < $compare_time
			"
		);
	}

	public function get_lock()
	{
		global $wpdb;

		$lock = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, %d)',
				'gpi_lock_' . intval( $this->gpi_options['mutex_id'] ), 0
			)
		);
	
		return $lock == 1;
	}

	public function release_lock()
	{
		global $wpdb;

		$wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				'gpi_lock_' . intval( $this->gpi_options['mutex_id'] )
			)
		);
	}

	public function check_status( $busy )
	{
		$mutex_lock = $this->get_lock();
		
		$this->release_lock();

		if ( ! $mutex_lock ){
			$busy = true;
		}

		return $busy;
	}

	public function run_gpi( $recheck = false, $timeout = false, $urls_provided = false )
	{
		add_option( 'gpi_check_now', true );

		$query_args = array( 'gpi_check_now' => true, 'cb' => time() );

		if ( $recheck ) {
			$query_args['recheck'] = true;
		}
		if ( $timeout ) {
			$query_args['timeout'] = true;
		}
		if ( $urls_provided ) {
			$query_args['urls_provided'] = true;
		}

		$cron_url = add_query_arg( $query_args, home_url() );

		wp_remote_post( $cron_url, array( 'timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters( 'https_local_ssl_verify', true ) ) );
	}

	public function googlepagespeedinsightsworker( $urls_to_check = array(), $force_recheck_all_urls = false, $timeout_respawn = false, $busy = false )
	{
		$mutex_lock = $this->get_lock();
		if ( ! $mutex_lock ) {
			$busy = true;
		} else {
			self::$force_recheck_urls = $force_recheck_all_urls;
			$this->worker( $urls_to_check, sanitize_text_field( $this->gpi_options['strategy'] ), $force_recheck_all_urls, $timeout_respawn );
		}

		return $busy;
	}

	public function worker( $urls_to_recheck, $strategy, $force_recheck_all_urls, $timeout_respawn )
	{
		if ( empty( $this->gpi_options['google_developer_key'] ) ) {
			return;
		}

		// Add a shutdown function to check if the last scan finished successfully, and relaunch the scan if it did not.
		register_shutdown_function( array( 'GPI_Core', 'shutdown_checker' ) );

		if ( $max_runtime = intval( $this->gpi_options['max_run_time'] ) ) {
			$start_runtime = time();
		}

		// Include our Google API
		if ( ! class_exists('GPI_Pagespeed_API') ) {
			require_once GPI_DIRECTORY . '/classes/class-GPI-Pagespeed-API.php';
		}

		$developer_key = sanitize_text_field( $this->gpi_options['google_developer_key'] );
		$Pagespeed_API = new GPI_Pagespeed_API( $developer_key );

		$recheck_interval = intval( $this->gpi_options['recheck_interval'] );

		// Don't stop the script when the connection is closed
		ignore_user_abort( true );

		// Get our URLs and go to work!
		$url_groups = array();

		if ( $timeout_respawn && $missed_url_groups = get_option( 'gpi_missed_url_groups' ) ) {
			$url_groups = $missed_url_groups;
		} else if ( empty( $urls_to_recheck ) ) {
			$url_groups = $this->get_urls_to_check();
		} else if ( ! empty( $urls_to_recheck ) ) {
			$url_groups = $urls_to_recheck;
		}

		if ( empty( $url_groups ) ) {
			return;
		}

		// Set last run finished to false, we will change this to true if this process finishes before max execution time.
		$this->update_option( 'last_run_finished', false, 'gpagespeedi_options' );

		// Clear Bad API Key, Pagespeed Disabled, and API Restriction warnings
		$this->update_option( 'check_logs', false, 'gpagespeedi_options' );
		$this->update_option( 'bad_api_key', false, 'gpagespeedi_options' );
		$this->update_option( 'pagespeed_disabled', false, 'gpagespeedi_options' );
		$this->update_option( 'api_restriction', false, 'gpagespeedi_options' );

		$user_abort = false;
		$current_page = isset( $url_groups['completed_pages'] ) ? $url_groups['completed_pages'] : 0;
		$url_groups_clone = $url_groups;

		foreach ( $url_groups as $group_type => $group ) {
			if ( 'total_url_count' == $group_type || 'completed_pages' == $group_type ) {
				continue;
			}

			foreach ( $group as $item_key => $item ) {

				update_option( 'gpi_progress', $current_page . ' / ' . $url_groups['total_url_count'] );
				$current_page++;

				if ( 'both' == $strategy ) {
					foreach ( array( 'desktop', 'mobile' ) as $new_strategy ) {
						$result = $this->get_result( $Pagespeed_API, $new_strategy, $group_type, $item, $recheck_interval, $force_recheck_all_urls );

						if ( ! $result ) {
							break 3;
						}

						if ( $max_runtime && time() - $start_runtime > $max_runtime ) {
							self::$exceeded_runtime = true;
							break 3;
						}

						if ( $this->check_user_abort() ) {
							$user_abort = true;
							break 3;
						}
					}
				} else {
					$result = $this->get_result( $Pagespeed_API, $strategy, $group_type, $item, $recheck_interval, $force_recheck_all_urls );

					if ( ! $result ) {
						break 2;
					}

					if ( $max_runtime && time() - $start_runtime > $max_runtime ) {
						self::$exceeded_runtime = true;
						break 2;
					}

					if ( $this->check_user_abort() ) {
						$user_abort = true;
						break 2;
					}
				}

				$url_groups_clone['completed_pages'] = $current_page;
				unset( $url_groups_clone[ $group_type ][ $item_key ] );
			}
			unset( $url_groups_clone[ $group_type ] );
		}

		if ( ! empty( $url_groups_clone ) && ! $user_abort ) {
			update_option( 'gpi_missed_url_groups', $url_groups_clone );
		} else {
			delete_option( 'gpi_missed_url_groups' );
		}

		// All menu items have been processed, update the 'last_run_finished' value in the options so we know for next time
		$this->update_option( 'last_run_finished', true, 'gpagespeedi_options' );
		self::$last_scan_finished = ( self::$exceeded_runtime ) ? false : true;

		// Clear out our status option or show abort message
		if ( ! $user_abort ) {
			delete_option( 'gpi_progress' );
		} else {
			update_option( 'gpi_progress', 'abort' );
		}

		// If we skipped all URLs because there are no expired reports, alert the user
		if ( self::$skipped_all ) {
			update_option( 'gpi_error_message', esc_html__( 'There are no new pages, or pages with expired pagespeed reports to recheck. To force a recheck of all pages, check the "Recheck All" box before starting reporting.', 'gpagespeedi' ) );
		}

		// Release our lock on the DB
		$this->release_lock();

		// If this is the first time we have run through the whole way, update the DB
		$this->update_option( 'first_run_complete', true, 'gpagespeedi_options' );
	}

	static function shutdown_checker()
	{
		// If scan took longer than Maximum Script Run Time or Maximum Execution Time, start new scan.
		if ( ! self::$last_scan_finished && self::$exceeded_runtime ) {
			do_action( 'run_gpi', self::$force_recheck_urls, true, false );
		} else if ( ! self::$last_scan_finished ) {
			do_action( 'run_gpi', false, true, false ); // If scan failed due to Maximum Execution Time, avoid trying again with force_recheck as it could cause infinite loop.
		}
	}

	private function get_result( $Pagespeed_API, $strategy, $group_type, $item, $recheck_interval, $force_recheck_all_urls, $continue = true )
	{
		global $wpdb;

		// Use max_execution_time set in settings.
		@set_time_limit( intval( $this->gpi_options['max_execution_time'] ) );

		$object_url = $item['url'];

		$object_id	= $item['objectid'];
		$custom_url	= ( empty( $item['custom'] ) ) ? false : true;

		$gpi_page_stats = $wpdb->prefix . 'gpi_page_stats';
		$where_column = $this->get_where_column( $custom_url, $group_type );

		$existing_url_info = $wpdb->get_row(
			$wpdb->prepare(
				"
					SELECT {$strategy}_last_modified, force_recheck
					FROM $gpi_page_stats
					WHERE $where_column = %d
				",
				$object_id
			)
		);

		$property = $strategy . '_last_modified';
		$update = ( isset( $existing_url_info->$property ) ) ? true : false;
		$time = current_time( 'timestamp' );

		if ( $update && ! $force_recheck_all_urls && ! $existing_url_info->force_recheck ) {
			$last_modified = $existing_url_info->$property;

			if ( ! empty( $last_modified ) && $time - $last_modified < $recheck_interval ) {
				return $continue;
			}
		}

		self::$skipped_all = false;

		$result = $Pagespeed_API->run_pagespeed( $object_url, array( 'locale' => sanitize_text_field( $this->gpi_options['response_language'] ), 'strategy' => $strategy ) );
		if ( ! empty( $result ) ) {
			if ( isset( $result['responseCode'] ) && $result['responseCode'] >= 200 && $result['responseCode'] < 300 ) {
				$result['type'] = $group_type;
				$result[ $where_column ] = $object_id;
				$result['last_modified'] = $time;
				$this->save_values( $Pagespeed_API, $result, $where_column, $object_id, $object_url, $update, $strategy );
			} else {
				$exception_type = $this->exception_handler( $result, $strategy, $update, $group_type, $where_column, $object_id, $object_url );
				if ( 'fatal' == $exception_type ) {
					$continue = false;
				}
			}
		}

		// Some web servers seem to have a difficult time responding to the constant requests from the Google API, sleeping inbetween each URL helps
		sleep( intval( $this->gpi_options['sleep_time'] ) );

		return $continue;
	}

	private function check_user_abort()
	{
		global $wpdb;

		$abort_scan = $wpdb->get_var(
			"
				SELECT COUNT(*)
				FROM $wpdb->options
				WHERE option_name = 'gpi_abort_scan'
			"
		);

		if ( $abort_scan ) {
			delete_option( 'gpi_abort_scan' );
			return true;
		}

		return false;
	}

	private function get_where_column( $custom_url, $url_group_type )
	{
		if ( ! $custom_url ) {
			// Use Term ID or Object ID depending on Url Group Type
			if ( $url_group_type != 'category' ) {
				$where_column = 'object_id';
			} else {
				$where_column = 'term_id';
			}
		} else {
			$where_column = 'custom_id';
		}

		return $where_column;
	}

	private function get_existing_reports( $types )
	{
		if ( empty( $types ) ) {
			return false;
		}

		global $wpdb;

		$gpi_page_stats = $wpdb->prefix . 'gpi_page_stats';
		$types	= implode( '|', $types );

		$reports = $wpdb->get_results(
			$wpdb->prepare(
				"
					SELECT URL as url, type, object_id as objectid, term_id, custom_id
					FROM $gpi_page_stats
					WHERE type REGEXP %s
				",
				$types
			), ARRAY_A
		);

		return $reports;
	}

	public function get_urls_to_check()
	{
		$total_count = 0;
		$urls_to_check = array();
		$blacklist_urls = $this->get_blacklist_urls();
		$flat_urls = array();
		$types = array();

		// Get Custom Post Type URLs
		if ( $cpt_whitelist = maybe_unserialize( $this->gpi_options['cpt_whitelist'] ) ) {
			if ( ! empty( $cpt_whitelist ) ) {
				$args = array(
					'public'   => true,
					'_builtin' => false
				);
				$custom_post_types = get_post_types( $args, 'names', 'and' );
				foreach ( $custom_post_types as $custom_post_type ) {
					if ( in_array( $custom_post_type, $cpt_whitelist ) ) {
						$types[ $custom_post_type ] = $custom_post_type;
						$x = 0;
						$custom_posts = get_posts( array( 'post_status' => 'publish', 'post_type' => $custom_post_type, 'posts_per_page' => -1, 'fields' => 'ids' ) );
						foreach ( $custom_posts as $custom_post ) {
							$url = get_permalink( $custom_post );
							if ( ! in_array( $url, $blacklist_urls ) ) {
								$flat_urls[] = $url;
								$urls_to_check[ $custom_post_type ][ $x ]['url'] = $url;
								$urls_to_check[ $custom_post_type ][ $x ]['objectid'] = $custom_post;
								$total_count++;
								$x++;
							}
						}
					}
				}
			}
		}

		// Get Posts URLs from built in 'post' type
		if ( (bool) $this->gpi_options['check_posts'] ) {
			$types['post'] = 'post';
			$x = 0;
			$builtin_posts_array = get_posts( array('post_status' => 'publish', 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids') );
			foreach ( $builtin_posts_array as $standard_post ) {
				$url = get_permalink( $standard_post );
				if ( ! in_array( $url, $blacklist_urls ) ) {
					$flat_urls[] = $url;
					$urls_to_check['post'][ $x ]['url'] = $url;
					$urls_to_check['post'][ $x ]['objectid'] = $standard_post;
					$total_count++;
					$x++;
				}
			}
		}

		// Get Page URLs
		if ( (bool) $this->gpi_options['check_pages'] ) {
			$types['page'] = 'page';
			$x = 0;
			$pages_array = get_pages();
			foreach( $pages_array as $page ) {
				$url = get_permalink( $page->ID );
				if ( ! in_array( $url, $blacklist_urls ) ) {
					$flat_urls[] = $url;
					$urls_to_check['page'][ $x ]['url'] = $url;
					$urls_to_check['page'][ $x ]['objectid'] = $page->ID;
					$total_count++;
					$x++;
				}
			}

		}

		// Get Category URLs
		if ( (bool) $this->gpi_options['check_categories'] ) {
			$types['category'] = 'category';
			$x = 0;
			$categories_array = get_categories();
			foreach ( $categories_array as $category ) {
				$url = get_category_link( $category->term_id );
				if ( ! in_array( $url, $blacklist_urls ) ) {
					$flat_urls[] = $url;
					$urls_to_check['category'][ $x ]['url'] = $url;
					$urls_to_check['category'][ $x ]['objectid'] = $category->term_id;
					$total_count++;
					$x++;
				}
			}
		}

		// Get Custom URLs
		if ( (bool) $this->gpi_options['check_custom_urls'] ) {

			global $wpdb;

			$gpi_custom_urls = $wpdb->prefix . 'gpi_custom_urls';
			$custom_urls_array = $wpdb->get_results(
				"
					SELECT ID, URL, type
					FROM $gpi_custom_urls
				",
				ARRAY_A
			);
			$x = 0;
			foreach ( $custom_urls_array as $custom_url ) {
				if ( ! isset( $types[ $custom_url['type'] ] ) ) {
					$types[ $custom_url['type'] ] = $custom_url['type'];
				}
				$url = $custom_url['URL'];
				if ( ! in_array( $url, $blacklist_urls ) ) {
					$flat_urls[] = esc_url_raw( $url );
					$urls_to_check[ $custom_url['type'] ][ $x ]['url'] = esc_url_raw( $url );
					$urls_to_check[ $custom_url['type'] ][ $x ]['objectid'] = intval( $custom_url['ID'] );
					$urls_to_check[ $custom_url['type'] ][ $x ]['custom'] = 1;
					$total_count++;
					$x++;
				}
			}
		}

		// Get any existing reports not found in the above search
		$existing_reports = $this->get_existing_reports( $types );

		if ( ! empty( $existing_reports ) ) {
			foreach ( $existing_reports as $key => $report_info ) {
				if ( in_array( $report_info['url'], $flat_urls ) ) {
					unset( $existing_reports[ $key ] );
					continue;
				}

				if ( ! empty( $report_info['term_id'] ) ) {
					$existing_reports[ $key ]['objectid'] = intval( $report_info['term_id'] );
				}

				if ( ! empty( $report_info['custom_id'] ) ) {
					$existing_reports[ $key ]['custom'] = true;
					$existing_reports[ $key ]['objectid'] = intval( $report_info['custom_id'] );
				}

				unset( $existing_reports[ $key ]['term_id'] );
				unset( $existing_reports[ $key ]['custom_id'] );
			}
		}

		if ( ! empty( $existing_reports ) ) {
			foreach ( $existing_reports as $report_info ) {
				$urls_to_check[ $report_info['type'] ][] = $report_info;
				$total_count++;
			}
		}

		$urls_to_check['total_url_count'] = $total_count;

		return $urls_to_check;
	}

	public function save_values( $Pagespeed_API, $result, $where_column, $object_id, $object_url, $update, $strategy )
	{
		global $wpdb;
		$gpi_page_stats = $wpdb->prefix . 'gpi_page_stats';
		$gpi_page_stats_values = array();

		$score = $result['data']->lighthouseResult->categories->performance->score;

		// Store identifying information
		$gpi_page_stats_values['URL'] = esc_url_raw( $object_url );
		$gpi_page_stats_values['type'] = sanitize_title( $result['type'] );
		$gpi_page_stats_values[ sanitize_key( $where_column ) ] = intval( $result[ $where_column ] );
		$gpi_page_stats_values[ sanitize_key( $strategy . '_last_modified' ) ] = intval( $result['last_modified'] );
		$gpi_page_stats_values['force_recheck'] = 0;
		$gpi_page_stats_values['response_code'] = intval( $result['responseCode'] );
		$gpi_page_stats_values[ sanitize_key( $strategy . '_lab_data' ) ] = $Pagespeed_API->get_lab_data( $result['data'] );
		$gpi_page_stats_values[ sanitize_key( $strategy . '_field_data' ) ] = $Pagespeed_API->get_field_data( $result['data'] );
		$gpi_page_stats_values[ sanitize_key( $strategy . '_score' ) ] = intval( $score * 100 );

		if ( $update ) {
			$wpdb->update( $gpi_page_stats, $gpi_page_stats_values, array( sanitize_key( $where_column ) => intval( $object_id ) ) );
			$last_updated_id = $wpdb->get_var(
				$wpdb->prepare(
					"
						SELECT ID
						FROM $gpi_page_stats
						WHERE $where_column = %d
					",
					$object_id
				)
			);
		} else {
			$wpdb->insert( $gpi_page_stats, $gpi_page_stats_values );
			$last_updated_id = $wpdb->insert_id;
		}

		$gpi_page_reports = $wpdb->prefix . 'gpi_page_reports';
		if ( $update ) {
			$wpdb->query(
				$wpdb->prepare(
					"
						DELETE FROM $gpi_page_reports
						WHERE page_id = %d
						AND strategy = %s
					",
					$last_updated_id,
					$strategy
				)
			);
		}

		$page_reports = $Pagespeed_API->get_page_reports( $result['data'], $last_updated_id, $strategy, $this->gpi_options );
		if ( ! empty( $page_reports ) ) {
			foreach ( $page_reports as $page_report ) {
				$wpdb->insert( $gpi_page_reports, $page_report );
			}
		}

	}

	public function save_bad_request( $type, $where_column, $object_id, $object_url, $message = true )
	{
		global $wpdb;
		$gpi_page_blacklist = $wpdb->prefix . 'gpi_page_blacklist';

		$row_exist = $wpdb->get_row(
			$wpdb->prepare(
				"
					SELECT ID
					FROM $gpi_page_blacklist
					WHERE URL = %s
				",
				$object_url
			),
			ARRAY_A
		);

		if ( ! $row_exist ) {
			$gpi_page_blacklist_values = array();

			if ( $message ) {
				$this->update_option( 'new_ignored_items', true, 'gpagespeedi_options' );
			}

			$wpdb->insert( $gpi_page_blacklist,
				array(
					'URL'			=> $object_url,
					'type'			=> $type,
					$where_column	=> $object_id
				)
			);
		}
	}

	public function exception_handler( $result, $strategy, $update, $url_group_type, $where_column, $object_id, $object_url, $error_type = 'non_fatal' )
	{
		$errors = isset( $result['data']->error->errors ) ? $result['data']->error->errors : false;

		if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'keyInvalid' ) {

			$this->update_option( 'bad_api_key', true, 'gpagespeedi_options' );
			$error_type = 'fatal';

		} else if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'badRequest' ) {

			$this->update_option( 'check_logs', true, 'gpagespeedi_options' );
			$error_type = 'fatal';

		} else if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'accessNotConfigured' ) {

			$this->update_option( 'pagespeed_disabled', true, 'gpagespeedi_options' );
			$error_type = 'fatal';

		} else if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'ipRefererBlocked' ) {

			$this->update_option( 'api_restriction', true, 'gpagespeedi_options' );
			$error_type = 'fatal';

		} else if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'forbidden' ) {

			$this->update_option( 'api_restriction', true, 'gpagespeedi_options' );
			$error_type = 'fatal';

		} else if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'backendError' ) {

			$this->save_bad_request( $url_group_type, $where_column, $object_id, $object_url, false );
			$this->update_option( 'backend_error', true, 'gpagespeedi_options' );

		} else if ( isset( $errors[0]->reason ) && $errors[0]->reason == 'mainResourceRequestFailed' ) {

			$this->save_bad_request( $url_group_type, $where_column, $object_id, $object_url );

		} else if ( $result['responseCode'] == '500' ) {

			$this->save_bad_request( $url_group_type, $where_column, $object_id, $object_url );
		}

		if ( (bool) $this->gpi_options['log_api_errors'] ) {
			global $wpdb;

			$gpi_api_error_logs = $wpdb->prefix . 'gpi_api_error_logs';

			$wpdb->insert( $gpi_api_error_logs,
				array(
					'URL'		=> $object_url,
					'strategy'	=> $strategy,
					'is_update'	=> $update,
					'type'		=> $url_group_type,
					'timestamp'	=> current_time( 'timestamp' ),
					'error'		=> maybe_serialize( $errors )
				)
			);
		}

		return $error_type;
	}

	public function get_blacklist_urls()
	{
		global $wpdb;

		$gpi_page_blacklist = $wpdb->prefix . 'gpi_page_blacklist';
		$blacklist_urls = $wpdb->get_col(
			"
				SELECT URL
				FROM $gpi_page_blacklist
			"
		);

		return $blacklist_urls;
	}

	public function update_option( $opt_key, $opt_val, $opt_group )
	{
		if ( 'gpagespeedi_ui_options' == $opt_group ) {
			$options = get_option( 'gpagespeedi_ui_options' );
		} else {
			$options = $this->gpi_options;
		}

		$options[ $opt_key ] = $opt_val;

		update_option( $opt_group, $options );

		if ( 'gpagespeedi_ui_options' == $opt_group ) {
			$this->gpi_ui_options = $options;
		} else {
			$this->gpi_options = $options;
		}
	}

}

add_action( 'plugins_loaded', array( new GPI_Core, 'init' ) );
