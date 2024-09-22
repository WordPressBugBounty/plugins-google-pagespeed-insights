<?php
/**
 * Template - Ignored URLs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once GPI_DIRECTORY . '/classes/class-GPI-List-Table.php';

$GPI_List_Table = new GPI_List_Table( 'ignored-urls' );
$GPI_List_Table->prepare_items();
?>

<form id="reports-filter" action="" method="get">
	<input type="hidden" name="page" value="google-pagespeed-insights" />
	<input type="hidden" name="render" value="ignored-urls" />

	<?php $GPI_List_Table->display(); ?>
</form>