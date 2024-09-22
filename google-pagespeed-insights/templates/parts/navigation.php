<?php
/**
 * Template Part - Navigation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<h3 class="nav-tab-wrapper">
	<a href="?page=google-pagespeed-insights&amp;render=report-list" class="nav-tab <?php if ( $admin_page == '' || $admin_page == 'report-list' || $admin_page == 'ignore' || $admin_page == 'recheck' ) { echo esc_attr( 'nav-tab-active' ); } ?>"><?php esc_html_e( 'Report List', 'gpagespeedi' ); ?></a>
	<?php if ( $admin_page == 'details' ) : ?>
		<a href="<?php echo esc_url( '?page=google-pagespeed-insights&amp;render=details&amp;page_id=' . intval( $_GET['page_id'] ) ); ?>" class="nav-tab nav-tab-active nav-tab-temp"><?php esc_html_e( 'Report Details', 'gpagespeedi' ); ?></a>
	<?php endif; ?>
	<a href="?page=google-pagespeed-insights&amp;render=summary" class="nav-tab <?php if ( $admin_page == 'summary' ) { echo esc_attr( 'nav-tab-active' ); } ?>"><?php esc_html_e( 'Report Summary', 'gpagespeedi' ); ?></a>

	<a href="?page=google-pagespeed-insights&amp;render=snapshots" class="nav-tab <?php if ( $admin_page == 'snapshots' ) { echo esc_attr( 'nav-tab-active' ); } ?>"><?php esc_html_e( 'Snapshots', 'gpagespeedi' ); ?></a>
	<?php if ( $admin_page == 'view-snapshot' && ! isset( $_GET['compare_id'] ) ) : ?>
		<a href="<?php echo esc_url( '?page=google-pagespeed-insights&amp;render=view-snapshot&amp;snapshot_id=' . intval( $_GET['snapshot_id'] ) ); ?>" class="nav-tab nav-tab-active nav-tab-temp"><?php esc_html_e( 'View Snapshot', 'gpagespeedi' ); ?></a>
	<?php endif; ?>
	<?php if ( $admin_page == 'view-snapshot' && isset( $_GET['compare_id'] ) ) : ?>
		<a href="<?php echo esc_url( '?page=google-pagespeed-insights&amp;render=view-snapshot&amp;snapshot_id=' . intval( $_GET['snapshot_id'] ) . '&amp;compare_id=' . intval( $_GET['compare_id'] ) ); ?>" class="nav-tab nav-tab-active nav-tab-temp"><?php esc_html_e('Compare Snapshots', 'gpagespeedi'); ?></a>
	<?php endif; ?>
	<a href="?page=google-pagespeed-insights&amp;render=custom-urls" class="nav-tab <?php if ( $admin_page == 'custom-urls' || $admin_page == 'delete' ) { echo esc_attr( 'nav-tab-active' ); } ?>"><?php esc_html_e( 'Custom URLs', 'gpagespeedi' ); ?></a>
	<?php if($admin_page == 'add-custom-urls') : ?>
		<a href="?page=google-pagespeed-insights&amp;render=add-custom-urls" class="nav-tab nav-tab-active nav-tab-temp"><?php esc_html_e( 'Add New URLs', 'gpagespeedi' ); ?></a>
	<?php endif ?>
	<?php if ( $admin_page == 'add-custom-urls-bulk' ) : ?>
		<a href="?page=google-pagespeed-insights&amp;render=add-custom-urls-bulk" class="nav-tab nav-tab-active nav-tab-temp"><?php esc_html_e( 'Bulk Upload New URLs', 'gpagespeedi' ); ?></a>
	<?php endif; ?>

	<a href="?page=google-pagespeed-insights&amp;render=ignored-urls" class="nav-tab <?php if ( $admin_page == 'ignored-urls' || $admin_page == 'activate' ) { echo esc_attr( 'nav-tab-active' ); } ?>"><?php esc_html_e( 'Ignored URLs', 'gpagespeedi' ); ?></a>
	<a href="?page=google-pagespeed-insights&amp;render=options" class="nav-tab <?php if ( $admin_page == 'options' ) { echo esc_attr( 'nav-tab-active' ); } ?>"><?php esc_html_e( 'Options', 'gpagespeedi' ); ?></a>
	<?php if ( $admin_page == 'logs' ) : ?>
		<a href="?page=google-pagespeed-insights&amp;render=logs" class="nav-tab nav-tab-active nav-tab-temp"><?php esc_html_e('Logs', 'gpagespeedi'); ?></a>
	<?php endif; ?>

	<?php do_action( 'gpi_navigation', $admin_page ); ?>

</h3>