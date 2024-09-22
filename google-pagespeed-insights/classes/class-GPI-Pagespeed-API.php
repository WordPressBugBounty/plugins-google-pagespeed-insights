<?php
/**
 * =======================================
 * Google Pagespeed Insights API
 * =======================================
 * 
 * 
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	die();
}

class GPI_Pagespeed_API
{

	private $api_baseurl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	private $developer_key;
	private $lab_data_indexes;
	private $audits_to_skip;

	public function __construct( $developer_key )
	{
		$this->developer_key = $developer_key;
		$this->lab_data_indexes = apply_filters( 'GPI_lab_data_indexes', array(
			'first-contentful-paint',
			'first-meaningful-paint',
			'speed-index',
			'interactive',
			'first-cpu-idle',
			'estimated-input-latency'
		) );
		$this->audits_to_skip = apply_filters( 'GPI_audits_to_skip', array(
			'final-screenshot',
			'metrics',
			'network-requests',
			'main-thread-tasks'
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

	public function get_lab_data( $result, $lab_data = array() )
	{
		foreach ( $this->lab_data_indexes as $index ) {
			if ( ! isset( $result->lighthouseResult->audits->$index ) ) {
				continue;
			}

			$lab_data[] = array(
				'title'			=> sanitize_text_field( $result->lighthouseResult->audits->$index->title ),
				'description'	=> wp_kses_post( $this->parse_markdown( $result->lighthouseResult->audits->$index->description ) ),
				'score'			=> floatval( $result->lighthouseResult->audits->$index->score ),
				'displayValue'	=> sanitize_text_field( $result->lighthouseResult->audits->$index->displayValue )
			);
		}

		return serialize( $lab_data );
	}

	public function get_field_data( $result, $field_data = array() )
	{
		if ( ! isset( $result->loadingExperience->metrics ) ) {
			return $field_data;
		}

		$result = $this->object_to_array( $result->loadingExperience->metrics );

		array_walk_recursive( $result, [ $this, 'sanitize_recursive' ], 'fielddata' );

		return serialize( $result );
	}

	public function get_page_reports( $result, $page_id, $strategy, $options, $page_reports = array() )
	{
		$rule_results = $result->lighthouseResult->audits;

		if ( ! empty( $rule_results ) ) {
			foreach ( $rule_results as $rulename => $results_obj ) {

				if ( in_array( $rulename, $this->lab_data_indexes ) ) {
					continue;
				}

				if ( in_array( $rulename, $this->audits_to_skip ) ) {
					continue;
				}

				if ( 'screenshot-thumbnails' == $rulename && ! (bool) $options['store_screenshots'] ) {
					continue;
				}

				$page_reports[] = array(
					'page_id'		=> intval( $page_id ),
					'strategy'		=> sanitize_text_field( $strategy ),
					'rule_key'		=> sanitize_key( $rulename ),
					'rule_name'		=> wp_kses_data( $this->parse_markdown( $results_obj->title ) ),
					'rule_score'	=> floatval( $results_obj->score ),
					'rule_type'		=> isset( $results_obj->details->type ) ? sanitize_key( $results_obj->details->type ) : 'n/a',
					'rule_blocks'	=> $this->get_rule_blocks( $results_obj )
				);
			}
		}

		return $page_reports;
	}

	private function get_rule_blocks( $results_obj, $rule_blocks = array() )
	{
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

		if ( isset( $results_obj->details ) ) {
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