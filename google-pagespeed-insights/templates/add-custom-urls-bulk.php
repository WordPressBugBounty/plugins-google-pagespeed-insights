<?php
/**
 * Template - Bulk Upload New URLs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<form method="post" action="" enctype="multipart/form-data">
	<input type="hidden" name="page" value="google-pagespeed-insights" />
	<input type="hidden" name="render" value="add-custom-urls-bulk" />
	<input type="hidden" name="action" value="add-custom-urls-bulk" />

	<?php wp_nonce_field('gpi-add-custom-urls'); ?>

	<div class="framed boxsizing">
		<div class="boxheader large">
			<span class="left add"><?php esc_html_e( 'Bulk Upload Custom URLs', 'gpagespeedi' ); ?></span>
		</div>
		<div class="padded">

			<p><?php esc_html_e( 'Add any valid URL, even from sites outside of WordPress. Upload a properly formatted XML sitemap below to add pages.', 'gpagespeedi' ); ?></p>
			<p><?php echo wp_kses_data( __( 'XML must conform to the <a href="http://www.sitemaps.org/protocol.html" target="_blank">sitemaps.org standards</a>. There are free services that can help you generate a sitemap such as', 'gpagespeedi' ) ); ?> <a href="http://www.xml-sitemaps.com/" target="_blank">http://www.xml-sitemaps.com/</a>.</p>

			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label for="custom_url_label"><?php esc_html_e( 'Custom URL Label', 'gpagespeedi' ); ?>:</label>
						</th>
						<td>
							<input type="text" maxlength="40" name="custom_url_label" id="custom_url_label" placeholder="Custom Label" class="regular-text code" />
							<p class="description"><?php esc_html_e( 'Choose a custom label for your new URLs, this will be used later when sorting your reports.', 'gpagespeedi' ); ?><br /><span style="color:red"><?php esc_html_e( 'Max 40 Charactors, Lowercase alpha-numeric Only. Spaces will be replaced with hyphens', 'gpagespeedi' ); ?></span></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="xml_sitemap"><?php esc_html_e( 'XML Sitemap File', 'gpagespeedi' ); ?>:</label>
						</th>
						<td>
							<input type="file" name="xml_sitemap" id="xml_sitemap" />
						</td>
					</tr>
				</tbody>
			</table>
			<br />
		</div>
	</div>
	<?php submit_button( esc_html__( 'Submit Sitemap', 'gpagespeedi' ) ); ?>
</form>