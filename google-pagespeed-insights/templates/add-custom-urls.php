<?php
/**
 * Template - Add Custom URLs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<form method="post" action="">
	<input type="hidden" name="page" value="google-pagespeed-insights" />
	<input type="hidden" name="render" value="add-custom-urls" />
	<input type="hidden" name="action" value="add-custom-urls" />

	<?php wp_nonce_field('gpi-add-custom-urls'); ?>

	<div class="framed boxsizing">
		<div class="boxheader large">
			<span class="left add"><?php esc_html_e( 'Add Custom URLs', 'gpagespeedi' ); ?></span>
		</div>
		<div class="padded">

			<p><?php esc_html_e( 'Add any valid URL, even from sites outside of WordPress. Enter up to 10 URLs below. If you need to enter a lot of URLs check out the', 'gpagespeedi' ); ?> <a href="?page=google-pagespeed-insights&amp;render=add-custom-urls-bulk"><?php esc_html_e( 'Bulk URL uploader', 'gpagespeedi' ); ?></a>.</p>

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
							<label ><?php esc_html_e( 'Custom URLs', 'gpagespeedi' ); ?>:</label>
						</th>
						<td>
							<?php
								for ( $x = 0; $x < 10; $x++ ) :
									?>
									<input type="text" name="custom_urls[]" id="custom_urls" placeholder="Custom URL" class="regular-text code" />
									<?php
								endfor;
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<br />
		</div>
	</div>
	<?php submit_button( esc_html__( 'Submit URLs', 'gpagespeedi' ) ); ?>
</form>