<?php
/**
 * =======================================
 * Google Pagespeed Insights API
 * =======================================
 * 
 * 
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	die();
}

class GPI_Pagespeed_API
{

	private $api_baseurl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	private $developer_key;
	private $lab_data_indexes;
	private $audits_to_skip;
	private $categories;

	public function __construct( $developer_key )
	{
		$this->developer_key = $developer_key;
		// Lighthouse 10+ performance metrics (the old FMP / First CPU Idle / EIL metrics were removed by Google).
		$this->lab_data_indexes = apply_filters( 'GPI_lab_data_indexes', array(
			'first-contentful-paint',
			'largest-contentful-paint',
			'total-blocking-time',
			'cumulative-layout-shift',
			'speed-index'
		) );
		$this->audits_to_skip = apply_filters( 'GPI_audits_to_skip', array(
			'metrics',
			'network-requests',
			'main-thread-tasks'
		) );
		// Lighthouse categories requested from the API. "agentic-browsing" checks how well AI agents can use the page.
		$this->categories = apply_filters( 'GPI_categories', array(
			'performance',
			'accessibility',
			'best-practices',
			'seo',
			'agentic-browsing'
		) );
	}

	public function run_pagespeed( $object_url, $options )
	{
		$query_args = array(
			'url'		=> esc_url_raw ( $object_url ),
			'key'		=> sanitize_text_field( $this->developer_key ),
			'locale'	=> isset( $options['locale'] ) ? sanitize_text_field( $options['locale'] ) : 'en_US',
			'strategy'	=> isset( $options['strategy'] ) ? sanitize_text_field( $options['strategy'] ) : 'desktop'
		);

		$api_url = add_query_arg( $query_args, $this->api_baseurl );

		// The API accepts the category parameter more than once, which add_query_arg() cannot express.
		foreach ( $this->categories as $category ) {
			$api_url .= '&category=' . rawurlencode( strtoupper( str_replace( '-', '_', $category ) ) );
		}

		$api_request = wp_remote_get( $api_url, array(
			'timeout'	=> apply_filters( 'GPI_remote_get_timeout', 300 )
		) );

		$api_response_code = wp_remote_retrieve_response_code( $api_request );
		$api_response_body = json_decode( wp_remote_retrieve_body( $api_request ) );

		return array(
			'responseCode'	=> $api_response_code,
			'data'			=> $api_response_body
		);
	}

	/**
	 * Checks the API key without running Lighthouse. The request uses a deliberately invalid URL:
	 * Google checks the key first, so a working key gets "invalid argument" back and an unusable
	 * key gets the reason (invalid, API not enabled, restricted). Returns array( status, valid, message ).
	 */
	public function check_key()
	{
		$api_request = wp_remote_get( add_query_arg( array(
			'url'	=> 'gpi-key-check',
			'key'	=> rawurlencode( $this->developer_key )
		), $this->api_baseurl ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $api_request ) ) {
			return array(
				'status'	=> 'error',
				'valid'		=> false,
				/* translators: %s: error message */
				'message'	=> sprintf( __( 'Google could not be reached from this server: %s', 'google-pagespeed-insights' ), $api_request->get_error_message() )
			);
		}

		$code = wp_remote_retrieve_response_code( $api_request );
		$body = json_decode( wp_remote_retrieve_body( $api_request ) );
		$reason = '';

		if ( isset( $body->error->details ) && is_array( $body->error->details ) ) {
			foreach ( $body->error->details as $detail ) {
				if ( isset( $detail->reason ) ) {
					$reason = $detail->reason;
					break;
				}
			}
		}

		if ( 'API_KEY_INVALID' === $reason ) {
			return array( 'status' => 'invalid', 'valid' => false, 'message' => __( 'Google does not recognise this API key. Copy it again from the Google Cloud Console.', 'google-pagespeed-insights' ) );
		}

		if ( 'SERVICE_DISABLED' === $reason ) {
			return array( 'status' => 'disabled', 'valid' => false, 'message' => __( 'The key exists, but the "PageSpeed Insights API" is not enabled for its project. Enable it in the Google Cloud Console, wait a minute and check again.', 'google-pagespeed-insights' ) );
		}

		if ( 0 === strpos( $reason, 'API_KEY_' ) && false !== strpos( $reason, '_BLOCKED' ) ) {
			return array( 'status' => 'restricted', 'valid' => false, 'message' => __( 'The key’s restrictions do not allow requests from this server. Remove the website (HTTP referrer) restriction, or allow this server’s IP address.', 'google-pagespeed-insights' ) );
		}

		if ( 429 === $code || 'RATE_LIMIT_EXCEEDED' === $reason ) {
			return array( 'status' => 'quota', 'valid' => true, 'message' => __( 'The key works, but its daily quota is used up. Checks continue when the quota resets.', 'google-pagespeed-insights' ) );
		}

		if ( 400 === $code && isset( $body->error->status ) && 'INVALID_ARGUMENT' === $body->error->status ) {
			return array( 'status' => 'valid', 'valid' => true, 'message' => __( 'The API key works.', 'google-pagespeed-insights' ) );
		}

		$message = isset( $body->error->message ) ? sanitize_text_field( $body->error->message ) : sprintf( 'HTTP %d', $code );

		return array(
			'status'	=> 'error',
			'valid'		=> false,
			/* translators: %s: error message from Google */
			'message'	=> sprintf( __( 'Google returned an error: %s', 'google-pagespeed-insights' ), $message )
		);
	}

	public function get_lab_data( $result, $lab_data = array() )
	{
		$refs = $this->get_audit_refs( $result );

		foreach ( $this->lab_data_indexes as $index ) {
			if ( ! isset( $result->lighthouseResult->audits->$index ) ) {
				continue;
			}

			$audit = $result->lighthouseResult->audits->$index;

			$lab_data[] = array(
				'id'			=> sanitize_key( $index ),
				'title'			=> sanitize_text_field( $audit->title ),
				'description'	=> wp_kses_post( $this->parse_markdown( $audit->description ) ),
				'score'			=> floatval( $audit->score ),
				'displayValue'	=> isset( $audit->displayValue ) ? sanitize_text_field( $audit->displayValue ) : '',
				'numericValue'	=> isset( $audit->numericValue ) ? floatval( $audit->numericValue ) : null,
				'acronym'		=> isset( $refs[ $index ] ) ? sanitize_text_field( $refs[ $index ]['acronym'] ) : '',
				'weight'		=> isset( $refs[ $index ] ) ? floatval( $refs[ $index ]['weight'] ) : 0
			);
		}

		return serialize( $lab_data );
	}

	/**
	 * Chrome UX Report (real user) data. Metric keys stay at the top level for backwards
	 * compatibility; the overall assessment and the origin-wide data use "_" prefixed keys.
	 */
	public function get_field_data( $result, $field_data = array() )
	{
		if ( isset( $result->loadingExperience->metrics ) ) {
			$field_data = $this->object_to_array( $result->loadingExperience->metrics );
			array_walk_recursive( $field_data, [ $this, 'sanitize_recursive' ], 'fielddata' );

			if ( isset( $result->loadingExperience->overall_category ) ) {
				$field_data['_overall'] = sanitize_key( $result->loadingExperience->overall_category );
			}
		}

		if ( isset( $result->originLoadingExperience->metrics ) ) {
			$origin = $this->object_to_array( $result->originLoadingExperience->metrics );
			array_walk_recursive( $origin, [ $this, 'sanitize_recursive' ], 'fielddata' );

			$field_data['_origin'] = $origin;

			if ( isset( $result->originLoadingExperience->overall_category ) ) {
				$field_data['_origin_overall'] = sanitize_key( $result->originLoadingExperience->overall_category );
			}
		}

		// The value is stored in a text column, so pages without Chrome UX Report data get an empty string, not an array.
		return empty( $field_data ) ? '' : serialize( $field_data );
	}

	/**
	 * Score (0-100, or null when not applicable) for every Lighthouse category in the response.
	 */
	public function get_categories( $result, $categories = array() )
	{
		if ( ! isset( $result->lighthouseResult->categories ) ) {
			return serialize( $categories );
		}

		foreach ( $result->lighthouseResult->categories as $id => $category ) {
			$categories[ sanitize_key( $id ) ] = array(
				'title'			=> isset( $category->title ) ? sanitize_text_field( $category->title ) : '',
				'score'			=> isset( $category->score ) && null !== $category->score ? (int) round( $category->score * 100 ) : null,
				'description'	=> isset( $category->description ) ? wp_kses_post( $this->parse_markdown( $category->description ) ) : ''
			);
		}

		return serialize( $categories );
	}

	/**
	 * Final screenshot of the page as a data URI (only kept when "Store Page Load Screenshots" is on).
	 */
	public function get_final_screenshot( $result )
	{
		if ( ! isset( $result->lighthouseResult->audits->{'final-screenshot'}->details->data ) ) {
			return '';
		}

		$data = $result->lighthouseResult->audits->{'final-screenshot'}->details->data;

		return preg_match( '#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $data ) ? $data : '';
	}

	/**
	 * Map of audit id => array( category, group, weight ) built from the categories' auditRefs.
	 * When an audit belongs to several categories, the first category listed in $this->categories wins.
	 */
	private function get_audit_refs( $result )
	{
		$refs = array();

		if ( ! isset( $result->lighthouseResult->categories ) ) {
			return $refs;
		}

		foreach ( $this->categories as $category_id ) {
			if ( ! isset( $result->lighthouseResult->categories->$category_id->auditRefs ) ) {
				continue;
			}

			foreach ( $result->lighthouseResult->categories->$category_id->auditRefs as $ref ) {
				if ( isset( $refs[ $ref->id ] ) ) {
					continue;
				}

				$refs[ $ref->id ] = array(
					'category'	=> $category_id,
					'group'		=> isset( $ref->group ) ? $ref->group : '',
					'weight'	=> isset( $ref->weight ) ? floatval( $ref->weight ) : 0,
					'acronym'	=> isset( $ref->acronym ) ? $ref->acronym : ''
				);
			}
		}

		return $refs;
	}


	public function get_page_reports( $result, $page_id, $strategy, $options, $page_reports = array() )
	{
		$rule_results = $result->lighthouseResult->audits;
		$audit_refs = $this->get_audit_refs( $result );
		$stack_tips = $this->get_stack_tips( $result );

		if ( ! empty( $rule_results ) ) {
			foreach ( $rule_results as $rulename => $results_obj ) {

				if ( in_array( $rulename, $this->lab_data_indexes ) ) {
					continue;
				}

				if ( in_array( $rulename, $this->audits_to_skip ) ) {
					continue;
				}

				if ( in_array( $rulename, array( 'screenshot-thumbnails', 'final-screenshot' ) ) ) {
					if ( ! (bool) $options['store_screenshots'] ) {
						continue;
					}
					$ref = array( 'category' => 'performance', 'group' => 'screenshots', 'weight' => 0 );
				} else if ( isset( $audit_refs[ $rulename ] ) ) {
					$ref = $audit_refs[ $rulename ];
				} else {
					// Not part of any requested category (internal Lighthouse data).
					continue;
				}

				if ( 'hidden' == $ref['group'] ) {
					continue;
				}

				// Outside of performance, passed and not applicable audits only need their title and score.
				$keep_details = 'performance' == $ref['category'] || ( isset( $results_obj->score ) && null !== $results_obj->score && $results_obj->score < 1 );

				$page_reports[] = array(
					'page_id'		=> intval( $page_id ),
					'strategy'		=> sanitize_text_field( $strategy ),
					'rule_key'		=> sanitize_key( $rulename ),
					'rule_name'		=> wp_kses_data( $this->parse_markdown( $results_obj->title ) ),
					'rule_score'	=> null === $results_obj->score ? null : floatval( $results_obj->score ),
					'rule_type'		=> isset( $results_obj->details->type ) ? sanitize_key( $results_obj->details->type ) : 'n/a',
					'rule_category'	=> sanitize_key( $ref['category'] ),
					'rule_group'	=> sanitize_key( $ref['group'] ),
					'rule_blocks'	=> $this->get_rule_blocks( $results_obj, array(), $ref, isset( $stack_tips[ $rulename ] ) ? $stack_tips[ $rulename ] : '', $keep_details )
				);
			}
		}

		return $page_reports;
	}

	/**
	 * Platform specific advice (Lighthouse "stack packs"), e.g. WordPress tips for an audit.
	 */
	private function get_stack_tips( $result, $tips = array() )
	{
		if ( empty( $result->lighthouseResult->stackPacks ) ) {
			return $tips;
		}

		foreach ( $result->lighthouseResult->stackPacks as $pack ) {
			if ( empty( $pack->descriptions ) ) {
				continue;
			}

			foreach ( $pack->descriptions as $audit_id => $description ) {
				if ( ! isset( $tips[ $audit_id ] ) ) {
					$tips[ $audit_id ] = wp_kses_post( $this->parse_markdown( $description ) );
				}
			}
		}

		return $tips;
	}

	private function get_rule_blocks( $results_obj, $rule_blocks = array(), $ref = array(), $stack_tip = '', $keep_details = true )
	{
		if ( ! empty( $ref ) ) {
			$rule_blocks['weight'] = floatval( $ref['weight'] );
		}

		if ( $stack_tip ) {
			$rule_blocks['stack_tip'] = $stack_tip;
		}

		if ( isset( $results_obj->numericValue ) ) {
			$rule_blocks['numeric_value'] = floatval( $results_obj->numericValue );
		}

		if ( isset( $results_obj->metricSavings ) ) {
			$rule_blocks['metric_savings'] = array_map( 'floatval', (array) $results_obj->metricSavings );
		}

		if ( isset( $results_obj->description ) ) {
			$rule_blocks['description'] = wp_kses_post( $this->parse_markdown( $results_obj->description ) );
		}

		if ( isset( $results_obj->scoreDisplayMode ) ) {
			$rule_blocks['score_display_mode'] = sanitize_text_field( $results_obj->scoreDisplayMode );
		}

		if ( isset( $results_obj->displayValue ) ) {
			$rule_blocks['display_value'] = sanitize_text_field( $results_obj->displayValue );
		} else {
			$rule_blocks['display_value'] = '';
		}

		if ( isset( $results_obj->details ) && $keep_details ) {
			$details = $this->object_to_array( $results_obj->details );

			array_walk_recursive( $details, [ $this, 'sanitize_recursive' ], 'ruleblocks' );

			$rule_blocks['details'] = $details;
		}

		return serialize( $rule_blocks );
	}

	private function object_to_array( $obj )
	{
		if ( is_object( $obj ) || is_array( $obj ) ) {
			$ret = (array) $obj;
			foreach ( $ret as &$item ) {
				$item = $this->object_to_array( $item );
			}
			return $ret;
		} else {
			return $obj;
		}
	}

	private function sanitize_recursive( &$value, $key, $type )
	{
		switch ( $key ) {
			case 'url':
				$value = wp_kses_post( $value );
				break;

			case 'report_url':
			case 'URL':
				$value = esc_url_raw( $value );
				break;

			case 'groupLabel':
				$value = sanitize_text_field( $value );
				break;

			case 'min':
			case 'max':
			case 'percentile':
				$value = intval( $value );
				break;

			case 'proportion':
			case 'average':
				$value = floatval( $value );
				break;

			case 'blockingTime':
			case 'wastedMs':
			case 'score':
			case 'startTime':
			case 'duration':
			case 'total':
			case 'scripting':
			case 'scriptParseCompile':
				$value = floatval( $value );
				break;

			case 'wastedBytes':
			case 'totalBytes':
				$value = intval( $value );
				break;

			case 'transferSize':
				$value = intval( $value );
				break;
		}
	}

	private function link_urls( $value )
	{
		$url = esc_url( $value );

		if ( ! $url ) {
			return $value;
		}

		return '<a href="' . $url . '" target="_blank">' . $value . '</a>';
	}

	private function parse_markdown( $string )
	{
		// Inline Code Blocks
		$string = preg_replace_callback( '/`(.*?)`/', [ $this, 'markdown_entities' ], $string );

		// URLs
		$string = preg_replace( '/\[(.*?)\]\((.*?)\)/', '<a href="${2}" target="_blank">${1}</a>', $string );

		return $string;
	}

	private function markdown_entities( $matches ) {
		return '<code>' . htmlentities( str_replace( '`', '', $matches[0] ) ) . '</code>';
	}

}