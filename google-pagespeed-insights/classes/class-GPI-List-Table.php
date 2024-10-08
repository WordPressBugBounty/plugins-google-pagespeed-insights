<?php
/**
 * =======================================
 * Google Pagespeed Insights List Table
 * =======================================
 * 
 * 
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	die();
}

if ( ! class_exists('WP_List_Table') ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GPI_List_Table extends WP_List_Table
{	
	private $gpi_options;
	private $type;
	private $strategy;
	private $columns;
	private $sortable;
	private $per_page;
	private $table;
	private $db_columns;
	private $orderby;
	private $order;

	public function __construct( $type = 'default' )
	{
		global $wpdb;

		parent::__construct( array(
			'singular'  => 'gpi_page_report',
			'plural'    => 'gpi_page_reports',
			'ajax'      => false
		));

		$this->gpi_options		= get_option('gpagespeedi_options');
		$this->type				= $type;
		$this->strategy			= sanitize_text_field( $this->gpi_options['strategy'] );
		$this->columns			= $this->get_columns();
		$this->sortable			= $this->get_sortable_columns();
		$this->per_page			= isset( $_GET['post-per-page']) ? intval( $_GET['post-per-page'] ) : 25;
		$this->_column_headers	= array( $this->columns, array(), $this->sortable );
		$this->orderby			= isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'ID';
		$this->order			= isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'asc';

		switch ( $type ) {
			case 'ignored-urls':
				$this->table		= $wpdb->prefix . 'gpi_page_blacklist';
				$this->db_columns	= array( 'ID', 'URL', 'type' );
				break;

			case 'snapshots':
				$this->table		= $wpdb->prefix . 'gpi_summary_snapshots';
				$this->db_columns	= array( 'ID', 'strategy', 'type', 'snaptime', 'comment' );
				break;

			case 'custom-urls':
				$this->table		= $wpdb->prefix . 'gpi_custom_urls';
				$this->db_columns	= array( 'ID', 'URL', 'type' );
				break;

			default:
				$this->table		= $wpdb->prefix . 'gpi_page_stats';
				$this->db_columns	= array( 'ID', 'URL', 'desktop_score', 'mobile_score', 'type', 'desktop_last_modified', 'mobile_last_modified' );
				break;
		}
	}

	protected function display_tablenav( $which )
	{
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-' . $this->_args['plural'], '_wpnonce', false );
		}
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( $this->has_items() ): ?>
				<div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
				</div>
			<?php endif;
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>
			<br class="clear" />
		</div>
		<?php
	}

	private function human_timing( $time )
	{
		if ( empty( $time ) ) {
			return esc_html__( 'N/A', 'gpagespeedi' );
		}
		$time = current_time( 'timestamp' ) - $time;

		$tokens = array (
			31536000 => __( 'year', 'gpagespeedi' ),
			2592000 => __( 'month', 'gpagespeedi' ),
			604800 => __( 'week', 'gpagespeedi' ),
			86400 => __( 'day', 'gpagespeedi' ),
			3600 => __( 'hour', 'gpagespeedi' ),
			60 => __( 'minute', 'gpagespeedi' ),
			1 => __( 'second', 'gpagespeedi' )
		);

		foreach ( $tokens as $unit => $text ) {
			if ( $time < $unit ) {
				continue;
			}
			$number_of_units = floor( $time / $unit );

			return $number_of_units . ' ' . $text . ( ( $number_of_units > 1 ) ? __( 's ago', 'gpagespeedi' ) : ' ' . __( 'ago', 'gpagespeedi' ) );
		}
	}

	public function no_items()
	{
		$pagetype = sanitize_text_field( $_GET['render'] );

		switch( $pagetype )
		{
			case 'ignored-urls':
				echo wp_kses_data( __( 'No Ignored URLs found. A URL can be ignored from the <a href="?page=google-pagespeed-insights&render=report-list">Report List</a> page if you would like to remove it from report pages', 'gpagespeedi' ) );
				break;

			case 'snapshots':
				echo wp_kses_data( __( 'No Snapshots found. Snapshots can be created from the', 'gpagespeedi' ) . ' ' . '<a href="?page=google-pagespeed-insights&render=summary">' . __( 'Report Summary', 'gpagespeedi' ) . '</a>' . ' ' . __( 'page', 'gpagespeedi' ) . '.' );
				break;

			case 'custom-urls':
				esc_html_e( 'No Custom URLs found. Click "Add New URLs" or "Bulk Upload New URLs" above to add custom URLs.', 'gpagespeedi' );
				break;

			default:
				esc_html_e( 'No Pagespeed Reports Found. Google Pagespeed may still be checking your pages. If problems persist, see the following possible solutions:', 'gpagespeedi' );
				?>
				<ol class="no-items">
					<li><?php esc_html_e( 'Make sure that you have entered your Google API key on the ', 'gpagespeedi' );?><a href="?page=google-pagespeed-insights&amp;render=options"><?php esc_html_e( 'Options', 'gpagespeedi' ); ?></a> <?php esc_html_e( 'page', 'gpagespeedi' ); ?>.</li>
					<li><?php esc_html_e( 'Make sure that you have enabled "PageSpeed Insights API" from the Services page of the ', 'gpagespeedi' );?><a href="https://console.developers.google.com/"> <?php esc_html_e( 'Google Console', 'gpagespeedi' ); ?></a>.</li>
					<li><?php esc_html_e( 'Make sure that your URLs are publicly accessible', 'gpagespeedi' ); ?>.</li>
				</ol>
				<?php
				break;
		}
	}
	
	public function strip_domain( $url )
	{
		$siteurl = get_site_url();
		$siteurl_ssl = get_site_url( '', '', 'https' );

		$search_urls = array( $siteurl, $siteurl_ssl );

		$cleaned_url = str_replace( $search_urls, '', $url );

		if ( '/' == $cleaned_url ) {
			$cleaned_url .= '(' . __( 'homepage', 'gpagespeedi' ) . ')';
		}

		return $cleaned_url;
	}

	public function column_default( $item, $column_name )
	{
		switch( $column_name )
		{
			case 'desktop_last_modified':
				$formatted_time = $this->human_timing( $item['desktop_last_modified'] );
				return esc_html( $formatted_time );

			case 'mobile_last_modified':
				$formatted_time = $this->human_timing( $item['mobile_last_modified'] );
				return esc_html( $formatted_time );

			case 'type':
				return esc_attr( $item[ $column_name ] );

			case 'custom_url':
				$actions = array(
					'delete'    => sprintf( '?page=google-pagespeed-insights&render=%s&action=%s&page_id=%s', 'custom-urls', 'delete', intval( esc_attr( $item['ID'] ) ) ),
					'visit'     => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $item['URL'] ), esc_html__( 'View URL', 'gpagespeedi' ) )
				);
				
				$nonced_url = wp_nonce_url( $actions['delete'], 'bulk-gpi_page_reports' );
				$actions['delete'] = '<a href="' . $nonced_url . '">' . esc_html__( 'Delete', 'gpagespeedi') . '</a>';

				return sprintf( '%1$s %2$s',
					esc_url( $item['URL'] ),
					$this->row_actions( $actions )
				);

			case 'snaptime':
				$date = $item['snaptime'];
				$date = date( 'M d, Y - h:i a', $date );

				$actions = array(
					'delete'    => sprintf( '?page=google-pagespeed-insights&render=%s&action=%s&snapshot_id=%s', 'snapshots', 'delete-snapshot', intval( esc_attr( $item['ID'] ) ) ),
					'view'      => sprintf( '<a href="?page=google-pagespeed-insights&render=%s&snapshot_id=%s">%s</a>', 'view-snapshot', intval( esc_attr( $item['ID'] ) ), esc_html__( 'View Snapshot', 'gpagespeedi' ) )
				);
				
				$nonced_url = wp_nonce_url( $actions['delete'], 'bulk-gpi_page_reports' );
				$actions['delete'] = '<a href="' . $nonced_url . '">' . esc_html__('Delete', 'gpagespeedi') . '</a>';


				return sprintf( '<a href="?page=%1$s&render=%2$s&snapshot_id=%3$s">%4$s</a> %5$s',
					'google-pagespeed-insights',
					'view-snapshot',
					intval( esc_attr( $item['ID'] ) ),
					esc_html( $date ),
					$this->row_actions( $actions )
				);

			case 'snapfilter':
				$filter = $item['type'];
				$filter_search = array( 'gpi_custom_posts-', 'gpi_custom_urls-', 'gpi_custom_posts', 'gpi_custom_urls', 'all', 'page', 'post', 'category' );
				$filter_replace = array( '', '', __( 'All Custom Post Types', 'gpagespeedi' ), __( 'All Custom URLs', 'gpagespeedi' ), __( 'All Reports', 'gpagespeedi' ), __( 'Pages', 'gpagespeedi' ), __( 'Posts', 'gpagespeedi' ), __( 'Categories', 'gpagespeedi' ) );
				$cleaned_filter = str_replace( $filter_search, $filter_replace, $filter );

				return esc_html( $cleaned_filter );

			case apply_filters( 'gpi_custom_column', false, $column_name ):
				return esc_html( apply_filters( 'gpi_custom_column_config', $column_name, $item ) );

			default:
				return esc_html( $item[ $column_name ] );
		}
	}
	
	public function column_url( $item )
	{
		$cleaned_url = $this->strip_domain( $item['URL'] );

		$actions = array(
			'view_details'  => sprintf( '<a href="?page=google-pagespeed-insights&render=%s&page_id=%s">%s</a>', 'details', intval( esc_attr( $item['ID'] ) ), esc_html__( 'Details', 'gpagespeedi' ) ),
			'ignore'        => sprintf( '?page=google-pagespeed-insights&render=%s&action=%s&page_id=%s', 'report-list', 'ignore', intval( esc_attr( $item['ID'] ) ) ),
			'delete_report' => sprintf( '?page=google-pagespeed-insights&render=%s&action=%s&page_id=%s', 'report-list', 'delete_report', intval( esc_attr( $item['ID'] ) ) ),
			'visit'         => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $item['URL'] ), esc_html__( 'View URL', 'gpagespeedi' ) )
		);

		$actions['ignore'] = '<a href="' . wp_nonce_url( $actions['ignore'], 'bulk-gpi_page_reports' ) . '">' . esc_html__( 'Ignore', 'gpagespeedi' ) . '</a>';
		$actions['delete_report'] = '<a href="' . wp_nonce_url( $actions['delete_report'], 'bulk-gpi_page_reports' ) . '">' . esc_html__( 'Delete', 'gpagespeedi' ) . '</a>';

		return sprintf( '<a href="?page=%3$s&render=%4$s&page_id=%5$s">%1$s</a> %2$s',
			esc_url( $cleaned_url ),
			$this->row_actions( $actions ),
			'google-pagespeed-insights',
			'details',
			intval( esc_attr( $item['ID'] ) )
		);
	}

	public function column_ignored_url( $item )
	{
		$cleaned_url = $this->strip_domain( $item['URL'] );

		$actions = array(
			'reactivate'		=> sprintf( '?page=google-pagespeed-insights&render=%s&action=%s&page_id=%s', 'ignored-urls', 'reactivate', intval( esc_attr( $item['ID'] ) ) ),
			'delete_blacklist'	=> sprintf( '?page=google-pagespeed-insights&render=%s&action=%s&page_id=%s', 'ignored-urls', 'delete_blacklist', intval( esc_attr( $item['ID'] ) ) ),
			'visit'				=> sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $item['URL'] ), esc_html__( 'View URL', 'gpagespeedi' ) )
		);

		$actions['reactivate'] = '<a href="' . wp_nonce_url( $actions['reactivate'], 'bulk-gpi_page_reports' ) . '">' . esc_html__( 'Reactivate', 'gpagespeedi' ) . '</a>';
		$actions['delete_blacklist'] = '<a href="' . wp_nonce_url( $actions['delete_blacklist'], 'bulk-gpi_page_reports' ) . '">' . esc_html__( 'Delete', 'gpagespeedi' ) . '</a>';

		return sprintf( '%1$s %2$s',
			esc_url( $cleaned_url ),
			$this->row_actions( $actions )
		);
	}

	public function column_mobile_score( $item )
	{
		if ( empty( $item['mobile_score'] ) && '0' != $item['mobile_score'] ) {
			return esc_html__( 'N/A', 'gpagespeedi' );
		}

		if ( $item['mobile_score'] < 50 ) {
			$barcolor = '#c7221f';
		} else if ( $item['mobile_score'] < 90 ) {
			$barcolor = '#e67700';
		} else {
			$barcolor = '#178239';
		}
		$innerdiv_css = 'background-color:' . $barcolor . ';width:' . $item['mobile_score'] . '%';

		return sprintf( '<span class="scorenum">%1$s</span><div class="reportscore_outter_bar"><div class="reportscore_inner_bar" style="%2$s"></div></div>', esc_html( $item['mobile_score'] ), esc_attr( $innerdiv_css ) );
	}

	public function column_desktop_score( $item )
	{
		if ( empty( $item['desktop_score'] ) && '0' != $item['desktop_score'] ) {
			return esc_html( 'N/A', 'gpagespeedi' );
		}

		if ( $item['desktop_score'] < 50 ) {
			$barcolor = '#c7221f';
		} else if ( $item['desktop_score'] < 90 ) {
			$barcolor = '#e67700';
		} else {
			$barcolor = '#178239';
		}
		$innerdiv_css = 'background-color:' . $barcolor . ';width:' . $item['desktop_score'] . '%';

		return sprintf( '<span class="scorenum">%1$s</span><div class="reportscore_outter_bar"><div class="reportscore_inner_bar" style="%2$s"></div></div>', esc_html( $item['desktop_score'] ), esc_attr( $innerdiv_css ) );
	}

	public function column_cb( $item )
	{
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			intval( esc_attr( $item['ID'] ) )
		);
	}

	public function get_columns()
	{
		switch ( $this->type ) {
			case 'ignored-urls':
				$columns = array(
					'cb'			=> '<input type="checkbox" />',
					'ignored_url'	=> __( 'Ignored URL', 'gpagespeedi' ),
					'type'			=> __( 'Page Type', 'gpagespeedi' )
				);
				break;

			case 'snapshots':
				$columns = array(
					'cb'			=> '<input type="checkbox" />',
					'snaptime'		=> __( 'Snapshot Date', 'gpagespeedi' ),
					'snapfilter'	=> __( 'Report Description', 'gpagespeedi' ),
					'strategy'		=> __( 'Report Type', 'gpagespeedi' ),
					'comment'		=> __( 'Comment', 'gpagespeedi' )
				);
				break;

			case 'custom-urls':
				$columns = array(
					'cb'			=> '<input type="checkbox" />',
					'custom_url'	=> __( 'Custom URL', 'gpagespeedi' ),
					'type'			=> __( 'Page Type', 'gpagespeedi' )
				);
				break;

			default:
				if ( $this->strategy == 'desktop' ) {
					$columns = array(
						'cb'					=> '<input type="checkbox" />',
						'url'					=> __( 'URL', 'gpagespeedi' ),
						'desktop_score'			=> __( 'Score', 'gpagespeedi' ),
						'type'					=> __( 'Page Type', 'gpagespeedi' ),
						'desktop_last_modified'	=> __( 'Last Checked', 'gpagespeedi' )
					);
				} else if ( $this->strategy == 'mobile' ) {
					$columns = array(
						'cb'					=> '<input type="checkbox" />',
						'url'					=> __( 'URL', 'gpagespeedi' ),
						'mobile_score'			=> __( 'Score', 'gpagespeedi' ),
						'type'					=> __( 'Page Type', 'gpagespeedi' ),
						'mobile_last_modified'	=> __( 'Last Checked', 'gpagespeedi' )
					);
				} else {
					$columns = array(
						'cb'					=> '<input type="checkbox" />',
						'url'					=> __( 'URL', 'gpagespeedi' ),
						'desktop_score'			=> __( 'Score (Desktop)', 'gpagespeedi' ),
						'mobile_score'			=> __( 'Score (Mobile)', 'gpagespeedi' ),
						'type'					=> __( 'Page Type', 'gpagespeedi' ),
						'desktop_last_modified'	=> __( 'Last Checked (Desktop)', 'gpagespeedi' ),
						'mobile_last_modified'	=> __( 'Last Checked (Mobile)', 'gpagespeedi' )
					);
				}
				break;
		}

		return array_map( 'esc_html', $columns );
	}
	
	public function get_sortable_columns()
	{
		$filter = ( isset( $_GET['filter'] ) ) ? sanitize_text_field( $_GET['filter'] ) : 'all';

		switch ( $this->type ) {
			case 'ignored-urls':
				$sortable_columns = array(
					'type' => array( 'type', false )
				);
				break;

			case 'snapshots':
				$sortable_columns = array(
					'snaptime'		=> array( 'snaptime', false ),
					'snapfilter'	=> array( 'type', false ),
					'strategy'		=> array( 'strategy', false )
				);
				break;

			case 'custom-urls':
				$sortable_columns = array(
					'type' => array( 'type', false )
				);
				break;

			default:
				if ( 'all' == $filter || 'custom_posts' == $filter || 'custom_urls' == $filter  ) {
					$sortable_columns = array(
						'desktop_score'	=> array( 'desktop_score', false ),
						'mobile_score'	=> array( 'mobile_score', false ),
						'type'			=> array( 'type', false )
					);
				} else {
					$sortable_columns = array(
						'desktop_score'	=> array( 'desktop_score', false ),
						'mobile_score'	=> array( 'mobile_score', false )
					);
				}
				break;
		}

		return $sortable_columns;
	}

	public function get_bulk_actions()
	{
		$render = ( isset( $_GET['render'] ) ) ? sanitize_text_field( $_GET['render'] ) : '';

		switch ( $render ) {
			case 'ignored-urls':
				$actions = array(
					'reactivate' => __( 'Reactivate', 'gpagespeedi' ),
					'delete_blacklist' => __( 'Delete URL', 'gpagespeedi' )
				);
				break;

			case 'snapshots':
				$actions = array(
					'delete-snapshot' => __( 'Delete', 'gpagespeedi' )
				);
				break;

			case 'custom-urls':
				$actions = array(
					'delete' => __( 'Delete', 'gpagespeedi' )
				);
				break;

			default:
				$actions = array(
					'ignore' => __( 'Ignore Reports', 'gpagespeedi' ),
					'delete_report' => __( 'Delete Reports', 'gpagespeedi' )
				);
				break;
		}

		return array_map( 'esc_html', $actions );
	}

	public function extra_tablenav( $which )
	{
		global $wpdb;

		$post_per_page = ( isset( $_GET['post-per-page'] ) ) ? intval( $_GET['post-per-page'] ) : 25;

		if ( 'top' == $which ) {
			?>
			<div class="alignleft actions">
				<?php if ( isset( $_GET['render'] ) && ( 'report-list' == $_GET['render'] || 'summary' == $_GET['render'] ) ) : ?>
				<select name="filter" id="filter">
				<?php
					$filter_options = apply_filters( 'gpi_filter_options', array(), false );

					if ( $filter_options ) :
						foreach ( $filter_options as $value => $label ) :
							$current_filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';

							if ( is_array( $label ) ) :
								?>
								<optgroup label="<?php echo esc_attr( $label['optgroup_label'] ); ?>">
								<?php
									foreach ( $label['options'] as $sub_value => $sub_label ) :
										?>
										<option value="<?php echo esc_attr( $sub_value ); ?>" <?php selected( $sub_value, $current_filter ); ?>><?php echo esc_html( $sub_label ); ?></option>
										<?php
									endforeach;
								?>
								</optgroup>
								<?php
							else :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $current_filter ); ?>><?php echo esc_html( $label ); ?></option>
								<?php
							endif; 
						endforeach;
					endif;
				?>
				</select>
				<?php endif; ?>
				<?php if ( isset( $_GET['render'] ) && 'summary' != $_GET['render'] ) : ?>
				<select name="post-per-page" id="post-per-page">
					<option value="25" <?php selected( $post_per_page, 25 ); ?>><?php esc_html_e( '25 Results/Page', 'gpagespeedi' ); ?></option>
					<option value="50" <?php selected( $post_per_page, 50 ); ?>><?php esc_html_e( '50 Results/Page', 'gpagespeedi' ); ?></option>
					<option value="100" <?php selected( $post_per_page, 100 ); ?>><?php esc_html_e( '100 Results/Page', 'gpagespeedi' ); ?></option>
					<option value="500" <?php selected( $post_per_page, 500 ); ?>><?php esc_html_e( '500 Results/Page', 'gpagespeedi' ); ?></option>
					<option value="1000" <?php selected( $post_per_page, 1000 ); ?>><?php esc_html_e( '1000 Results/Page', 'gpagespeedi' ); ?></option>
				</select>
				<?php endif; ?>
				<?php
					submit_button( esc_html__( 'Filter', 'gpagespeedi' ), 'button', false, false, array( 'id' => 'post-query-submit' ) );
				?>

				<?php if ( 'custom-urls' == $_GET['render'] ) : ?>
					<a href="?page=google-pagespeed-insights&amp;render=add-custom-urls" class="button-secondary"><?php esc_html_e( 'Add New URLs', 'gpagespeedi' ); ?></a>
					<a href="?page=google-pagespeed-insights&amp;render=add-custom-urls-bulk" class="button-secondary"><?php esc_html_e( 'Bulk Upload New URLs', 'gpagespeedi' ); ?></a>
				<?php endif; ?>

				<?php do_action( 'gpi_after_tablenav', sanitize_text_field( $_GET['render'] ) ); ?>

			</div>
		<?php
		}
	}

	public function prepare_items()
	{
		global $wpdb;

		$db_columns = implode( ',', $this->db_columns );
		$orderby = in_array( $this->orderby, $this->db_columns ) ? $this->orderby : 'ID';
		$order = ( 'asc' == strtolower( $this->order ) || 'desc' == strtolower( $this->order ) ) ? $this->order : 'asc';

		$all_types = apply_filters( 'gpi_filter_options', array(), true );

		$data = array();

		if ( 'default' == $this->type ) {
			$filter	= isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';
			$filter	= 'all' != $filter ? $filter : implode( '|', $all_types );
			$filter	= 'gpi_custom_urls' != $filter ? $filter : apply_filters( 'gpi_custom_url_labels', $filter );

			if ( $filter ) {
				$data = $wpdb->get_results(
					$wpdb->prepare(
						"
							SELECT $db_columns
							FROM $this->table
							WHERE type REGEXP %s
							ORDER BY $orderby $order
						",
						$filter
					), ARRAY_A
				);
			}
		} else {
			$data = $wpdb->get_results(
				"
					SELECT $db_columns
					FROM $this->table
					ORDER BY $orderby $order
				", ARRAY_A
			);
		}

		$current_page = $this->get_pagenum();
		$total_items = count( $data );
		
		// Slice up our data for Pagination
		$data = array_slice( $data, ( ( $current_page - 1 ) * $this->per_page ), $this->per_page );
		
		// Return sorted data to be used
		$this->items = $data;
		
		// Register pagination
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $total_items / $this->per_page )
		));
	}

}
