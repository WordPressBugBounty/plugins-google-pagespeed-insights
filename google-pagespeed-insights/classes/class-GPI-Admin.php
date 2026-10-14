<?php
/**
 * =======================================
 * Google Pagespeed Insights Admin
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

class GPI_Admin
{
	var $gpi_options;
	var $gpi_ui_options;
	var $gpi_management_page;

	public function init()
	{
		$this->gpi_options		= get_option( 'gpagespeedi_options' );
		$this->gpi_ui_options	= get_option( 'gpagespeedi_ui_options' );

		add_filter( 'plugin_action_links', array( $this, 'add_settings_link' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'upgrade_check' ), 10 );
		add_action( 'pre_uninstall_plugin', array( $this, 'backup_addon_tables' ), 10, 1 );
		add_action( 'deleted_plugin', array( $this, 'restore_addon_tables' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'google_pageinsights_menu' ), 10 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_app' ) );

	}

	public function add_settings_link( $links, $file )
	{
		if ( $file != GPI_BASENAME ) {
			return $links;
		}

		array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=google-pagespeed-insights#/settings' ) ) . '">' . esc_html__( 'Settings', 'google-pagespeed-insights' ) . '</a>' );

		return $links;
	}

	public function upgrade_check()
	{
		if ( ! isset( $this->gpi_options['version'] ) || version_compare( $this->gpi_options['version'], GPI_VERSION, '<' ) ) {
			GPI_Activation::upgrade( $this->gpi_options, $this->gpi_ui_options );
		}

		if ( defined( 'GPIA_PLUGIN_FILE' ) && current_user_can( 'activate_plugins' ) ) {
			deactivate_plugins( GPIA_PLUGIN_FILE );

			add_action( 'admin_notices', array( $this, 'notify_addon_deactivate' ) );
		}

		do_action( 'gpi_addon_upgrade_check' );
	}

	public function backup_addon_tables( $plugin_file )
	{
		if ( 'google-pagespeed-insights-addon/google-pagespeed-insights-addon.php' != $plugin_file ) {
			return;
		}

		global $wpdb;

		$gpi_summary_snapshots			= $wpdb->prefix . 'gpi_summary_snapshots';
		$gpi_custom_urls				= $wpdb->prefix . 'gpi_custom_urls';
		$gpi_summary_snapshots_backup	= $wpdb->prefix . 'gpi_summary_snapshots_backup';
		$gpi_custom_urls_backup			= $wpdb->prefix . 'gpi_custom_urls_backup';

		// Rename current tables
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$wpdb->query( "RENAME TABLE $gpi_summary_snapshots TO $gpi_summary_snapshots_backup" );
		$wpdb->query( "RENAME TABLE $gpi_custom_urls TO $gpi_custom_urls_backup" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Create new blank tables so uninstall hook doesn't produce errors
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- The plugin's own tables are created, changed or removed here.
		$wpdb->query( "CREATE TABLE $gpi_summary_snapshots (id INT(1) )" );
		$wpdb->query( "CREATE TABLE $gpi_custom_urls (id INT(1) )" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public function restore_addon_tables( $plugin_file, $deleted )
	{
		if ( 'google-pagespeed-insights-addon/google-pagespeed-insights-addon.php' != $plugin_file ) {
			return;
		}

		global $wpdb;

		$gpi_summary_snapshots			= $wpdb->prefix . 'gpi_summary_snapshots';
		$gpi_custom_urls				= $wpdb->prefix . 'gpi_custom_urls';
		$gpi_summary_snapshots_backup	= $wpdb->prefix . 'gpi_summary_snapshots_backup';
		$gpi_custom_urls_backup			= $wpdb->prefix . 'gpi_custom_urls_backup';

		// Rename backed up tables to restore
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix plus a fixed string.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; WordPress has no API for it.
		$wpdb->query( "RENAME TABLE $gpi_summary_snapshots_backup TO $gpi_summary_snapshots" );
		$wpdb->query( "RENAME TABLE $gpi_custom_urls_backup TO $gpi_custom_urls" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public function notify_addon_deactivate()
	{
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'The plugin "Google Pagespeed Insights Addon" has automatically been deactivated. As of v3.0 Insights from Google PageSpeed now includes all "addon" functionality for free. The "Google Pagespeed Insights Addon" can be uninstalled from the plugins page.', 'google-pagespeed-insights' ); ?></p>
		</div>
		<?php
	}

	public function google_pageinsights_menu()
	{
		$this->gpi_management_page = add_management_page( 'Insights from Google PageSpeed', 'Pagespeed Insights', 'manage_options', 'google-pagespeed-insights', array( $this, 'render_admin_page' ) );
	}

	/**
	 * The admin screen is a React app (src/, built to build/). Screens are routed with the URL hash.
	 */
	public function render_admin_page()
	{
		?>
		<div class="wrap gpi-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Insights from Google PageSpeed', 'google-pagespeed-insights' ); ?></h1>
			<div id="gpi-app" class="gpi-app">
				<noscript><?php esc_html_e( 'This screen needs JavaScript.', 'google-pagespeed-insights' ); ?></noscript>
			</div>
		</div>
		<?php
	}

	public function enqueue_app( $hook )
	{
		if ( $hook != $this->gpi_management_page ) {
			return;
		}

		$asset_file = GPI_DIRECTORY . '/build/index.asset.php';
		$asset = file_exists( $asset_file ) ? include $asset_file : array( 'dependencies' => array(), 'version' => GPI_VERSION );

		wp_enqueue_script( 'gpi-app', plugins_url( '/build/index.js', GPI_PLUGIN_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'gpi-app', plugins_url( '/build/style-index.css', GPI_PLUGIN_FILE ), array( 'wp-components' ), $asset['version'] );

		wp_localize_script( 'gpi-app', 'gpiApp', array(
			'version'			=> GPI_VERSION,
			'logo'				=> plugins_url( '/assets/images/icon.svg', GPI_PLUGIN_FILE ),
			'shortpixelLogo'	=> plugins_url( '/assets/images/shortpixel.png', GPI_PLUGIN_FILE ),
			'homeHost'			=> (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'gmtOffset'			=> (float) get_option( 'gmt_offset' )
		) );

		wp_set_script_translations( 'gpi-app', 'google-pagespeed-insights' );
	}

	public function admin_notice()
	{
		if ( ! (bool) $this->gpi_options['new_activation_message'] || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div id="message" class="updated">
			<p>
				<?php esc_html_e( 'Insights from Google PageSpeed has been activated. It can be accessed via Tools', 'google-pagespeed-insights' ); ?> -> <a href="<?php echo esc_url( admin_url( 'tools.php?page=google-pagespeed-insights#/settings' ) ); ?>">Pagespeed Insights</a>
			</p>
		</div>
		<?php
		do_action( 'gpi_update_option', 'new_activation_message', false, 'gpagespeedi_options' );
	}

}

add_action( 'plugins_loaded', array( new GPI_Admin, 'init' ) );
