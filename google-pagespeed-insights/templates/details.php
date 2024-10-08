<?php
/**
 * Template - Details
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<h3 id="url" class="subTitle"></h3>
<div class="toolbar">
	<a id="view_url" class="button-gpi view" target="_blank"><?php esc_html_e( 'View Page', 'gpagespeedi' ); ?></a>
	<a id="recheck_url" class="button-gpi recheck"><?php esc_html_e( 'Recheck Results', 'gpagespeedi' ); ?></a>
</div>

<div class="row">
	<div class="top-row boxsizing pagespeed_gauge_wrapper" id="pagespeed_gauge_wrapper">
		<div class="score_chart_div" id="score_chart_div">
			<img class="pagespeed_needle" id="pagespeed_needle" src="<?php echo esc_url( GPI_PUBLIC_PATH . 'assets/images/pagespeed_gauge_needle.png' ); ?>" width="204" height="204" alt="" />
			<div class="score_text" id="score_text"><span class="score"></span><span class="label"><?php esc_html_e( 'score', 'gpagespeedi' ); ?></span></div>
		</div>
	</div>
	<div class="top-row boxsizing framed pagespeed_lab_data_wrapper" id="pagespeed_lab_data_wrapper">
		<div class="boxheader">
			<span class="left"><?php esc_html_e('Lab Data', 'gpagespeedi'); ?></span>
			<span class="right"><?php esc_html_e( 'Value', 'gpagespeedi' ); ?></span>
		</div>
		<table class="stats">
			<tr class="last_checked">
				<td class="leftcol"><?php esc_html_e( 'Last Checked', 'gpagespeedi' ); ?></td>
				<td class="rightcol"></td>
			</tr>
		</table>
	</div>
	<div class="top-row boxsizing framed pagespeed_field_data_wrapper" id="pagespeed_field_data_wrapper">
		<div class="boxheader">
			<span class="left"><?php esc_html_e( 'Field Data', 'gpagespeedi' ); ?></span>
		</div>
		<div class="chart_data">
			<div id="FCP"></div>
			<div id="FID"></div>
		</div>
	</div>
</div>

<div class="row boxsizing framed screenshots">
	<div class="boxheader">
		<span class="left"><?php esc_html_e( 'Loading Screenshots', 'gpagespeedi' ); ?></span><span class="light"><?php esc_html_e( '(Hover for timestamp)', 'gpagespeedi' ); ?></span>
	</div>
	<div class="inner screenshots" id="screenshots"></div>
</div>
<div class="row boxsizing framed lighthouse opportunities">
	<div class="boxheader">
		<span class="left"><?php esc_html_e( 'Opportunities for improvement', 'gpagespeedi' ); ?></span><span class="light"><?php esc_html_e( '(Click for detailed report)', 'gpagespeedi' ); ?></span>
		<span class="right"><?php esc_html_e( 'Estimated Savings', 'gpagespeedi' ); ?></span>
	</div>
	<div class="inner opportunities accordion" id="opportunities"></div>
</div>
<div class="row boxsizing framed lighthouse diagnostics">
	<div class="boxheader">
		<span class="left"><?php esc_html_e( 'Diagnostics', 'gpagespeedi' ); ?></span><span class="light"><?php esc_html_e( '(Click for detailed report)', 'gpagespeedi' ); ?></span>
	</div>
	<div class="inner diagnostics accordion" id="diagnostics"></div>
</div>
<div class="row boxsizing framed lighthouse passed-audits">
	<div class="boxheader">
		<span class="left"><?php esc_html_e( 'Passed Audits', 'gpagespeedi' ); ?></span><span class="light"><?php esc_html_e( '(Click for detailed report)', 'gpagespeedi' ); ?></span>
		<span class="right"><span id="passed_audits_count"></span> <?php esc_html_e( 'audits', 'gpagespeedi' ); ?></span>

	</div>
	<div class="inner passed-audits accordion" id="passed_audits"></div>
</div>

<?php include GPI_DIRECTORY . '/templates/parts/nitropack.php'; ?>
