<?php
/**
 * Template - Options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<form method="post" action="">
	<div class="row framed boxsizing">
		<div class="boxheader large toggle">
			<span class="left google"><?php esc_html_e( 'Google Pagespeed Options', 'gpagespeedi' ); ?></span>
			<span class="right open"></span>
		</div>
		<div class="padded">
			<p><?php esc_html_e( 'Google API Key:', 'gpagespeedi' ); ?></p>
			<input type="text" name="google_developer_key" id="google_developer_key" value="<?php echo esc_attr( $this->gpi_options['google_developer_key'] ); ?>" class="googleapi code" />
			<p class="description"><span style="color:red;"><?php esc_html_e( 'This is required', 'gpagespeedi' ); ?></span>: <?php esc_html_e( 'if you do not have an API key you can create a new one for free from', 'gpagespeedi' ); ?>: <a href="https://console.developers.google.com" target="_blank">https://console.developers.google.com</a>. <?php echo wp_kses_data( __( 'Read the documentation included with this plugin or <a href="http://mattkeys.me/documentation/google-pagespeed-insights/#required_configuration" target="_blank">online</a> for additional information about creating an API key.', 'gpagespeedi' ) ); ?></p>

			<p><?php esc_html_e( 'Google Response Language:', 'gpagespeedi' ); ?></p>
			<select name="response_language" id="response_language">
				<option value="ar" <?php selected( $this->gpi_options['response_language'], 'ar' ); ?>>Arabic</option>
				<option value="bg" <?php selected( $this->gpi_options['response_language'], 'bg' ); ?>>Bulgarian</option>
				<option value="ca" <?php selected( $this->gpi_options['response_language'], 'ca' ); ?>>Catalan</option>
				<option value="zh_TW" <?php selected( $this->gpi_options['response_language'], 'zh_TW' ); ?>>Traditional Chinese (Taiwan)</option>
				<option value="zh_CN" <?php selected( $this->gpi_options['response_language'], 'zh_CN' ); ?>>Simplified Chinese</option>
				<option value="hr" <?php selected( $this->gpi_options['response_language'], 'hr' ); ?>>Croatian</option>
				<option value="cs" <?php selected( $this->gpi_options['response_language'], 'cs' ); ?>>Czech</option>
				<option value="da" <?php selected( $this->gpi_options['response_language'], 'da' ); ?>>Danish</option>
				<option value="nl" <?php selected( $this->gpi_options['response_language'], 'nl' ); ?>>Dutch</option>
				<option value="en_US" <?php selected( $this->gpi_options['response_language'], 'en_US' ); ?>>English</option>
				<option value="en_GB" <?php selected( $this->gpi_options['response_language'], 'en_GB' ); ?>>English UK</option>
				<option value="fil" <?php selected( $this->gpi_options['response_language'], 'fil' ); ?>>Filipino</option>
				<option value="fi" <?php selected( $this->gpi_options['response_language'], 'fi' ); ?>>Finnish</option>
				<option value="fr" <?php selected( $this->gpi_options['response_language'], 'fr' ); ?>>French</option>
				<option value="de" <?php selected( $this->gpi_options['response_language'], 'de' ); ?>>German</option>
				<option value="el" <?php selected( $this->gpi_options['response_language'], 'el' ); ?>>Greek</option>
				<option value="iw" <?php selected( $this->gpi_options['response_language'], 'iw' ); ?>>Hebrew</option>
				<option value="hi" <?php selected( $this->gpi_options['response_language'], 'hi' ); ?>>Hindi</option>
				<option value="hu" <?php selected( $this->gpi_options['response_language'], 'hu' ); ?>>Hungarian</option>
				<option value="id" <?php selected( $this->gpi_options['response_language'], 'id' ); ?>>Indonesian</option>
				<option value="it" <?php selected( $this->gpi_options['response_language'], 'it' ); ?>>Italian</option>
				<option value="ja" <?php selected( $this->gpi_options['response_language'], 'ja' ); ?>>Japanese</option>
				<option value="ko" <?php selected( $this->gpi_options['response_language'], 'ko' ); ?>>Korean</option>
				<option value="lv" <?php selected( $this->gpi_options['response_language'], 'lv' ); ?>>Latvian</option>
				<option value="lt" <?php selected( $this->gpi_options['response_language'], 'lt' ); ?>>Lithuanian</option>
				<option value="no" <?php selected( $this->gpi_options['response_language'], 'no' ); ?>>Norwegian</option>
				<option value="pl" <?php selected( $this->gpi_options['response_language'], 'pl' ); ?>>Polish</option>
				<option value="pt_BR" <?php selected( $this->gpi_options['response_language'], 'pt_BR' ); ?>>Portuguese (Brazilian)</option>
				<option value="pt_PT" <?php selected( $this->gpi_options['response_language'], 'pt_PT' ); ?>>Portuguese (Portugal)</option>
				<option value="ro" <?php selected( $this->gpi_options['response_language'], 'ro' ); ?>>Romanian</option>
				<option value="ru" <?php selected( $this->gpi_options['response_language'], 'ru' ); ?>>Russian</option>
				<option value="sr" <?php selected( $this->gpi_options['response_language'], 'sr' ); ?>>Serbian</option>
				<option value="sk" <?php selected( $this->gpi_options['response_language'], 'sk' ); ?>>Slovakian</option>
				<option value="sl" <?php selected( $this->gpi_options['response_language'], 'sl' ); ?>>Slovenian</option>
				<option value="es" <?php selected( $this->gpi_options['response_language'], 'es' ); ?>>Spanish</option>
				<option value="sv" <?php selected( $this->gpi_options['response_language'], 'sv' ); ?>>Swedish</option>
				<option value="th" <?php selected( $this->gpi_options['response_language'], 'th' ); ?>>Thai</option>
				<option value="tr" <?php selected( $this->gpi_options['response_language'], 'tr' ); ?>>Turkish</option>
				<option value="uk" <?php selected( $this->gpi_options['response_language'], 'uk' ); ?>>Ukrainian</option>
				<option value="vi" <?php selected( $this->gpi_options['response_language'], 'vi' ); ?>>Vietnamese</option>
			</select>
			<?php if ( (bool) $this->gpi_options['first_run_complete'] ) : ?>
				<p class="description"><span style="color:red;"><?php esc_html_e( 'Note', 'gpagespeedi' ); ?></span>: <?php esc_html_e( 'URLs must be rechecked before language changes take effect. Use the "Delete Data" option under "Advanced Configuration" if you would like to remove old reports.', 'gpagespeedi' ); ?></p>
			<?php endif; ?>

			<p><?php esc_html_e( 'Report Type(s):', 'gpagespeedi' ); ?></p>
			<select name="strategy" id="strategy">
				<option value="desktop" <?php selected( $this->gpi_options['strategy'], 'desktop' ); ?>><?php esc_html_e( 'Desktop', 'gpagespeedi' ); ?></option>
				<option value="mobile" <?php selected( $this->gpi_options['strategy'], 'mobile' ); ?>><?php esc_html_e( 'Mobile', 'gpagespeedi' ); ?></option>
				<option value="both" <?php selected( $this->gpi_options['strategy'], 'both' ); ?>><?php esc_html_e( 'Both', 'gpagespeedi' ); ?></option>
			</select>

			<p><?php esc_html_e( 'Store Page Load Screenshots:', 'gpagespeedi' ); ?></p>
			<select name="store_screenshots" id="store_screenshots">
				<option value="0" <?php selected( $this->gpi_options['store_screenshots'], 0 ); ?>><?php esc_html_e( 'No', 'gpagespeedi' ); ?></option>
				<option value="1" <?php selected( $this->gpi_options['store_screenshots'], 1 ); ?>><?php esc_html_e( 'Yes', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description"><span style="color:red;"><?php esc_html_e( 'Note', 'gpagespeedi' ); ?></span>: <?php esc_html_e( 'Screenshots stored in the database will usually take up around 50kb ~ 150kb per page report. If you have many pages to report on this may consume an unreasonable amount of space. Changes to this value will take effect on future report scans.', 'gpagespeedi' ); ?></p>
		</div>
	</div>

	<div class="row framed boxsizing">
		<div class="boxheader large toggle">
			<span class="left cal"><?php esc_html_e( 'Scheduling and URL Configuration', 'gpagespeedi' ); ?></span>
			<span class="right"></span>
		</div>
		<div class="padded hidden">

			<p><h4><?php esc_html_e( 'Choose wether or not Google Pagespeed Insights should automatically re-check page scores, and if so, how often.', 'gpagespeedi' ); ?></h4></p>
			<p class="checkbx">
				<input type="checkbox" name="use_schedule" id="use_schedule" <?php checked( $this->gpi_options['use_schedule'] ); ?>/>
				<label for="use_schedule"><?php esc_html_e( 'Automatically re-check Pagespeed Insights scores using a schedule', 'gpagespeedi' ); ?></label>
				<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
					<p class="description"><strong><?php esc_html_e( 'Notice', 'gpagespeedi' ); ?>:</strong> <?php esc_html_e( 'The WP Cron service this plugin uses to check pages is disabled. Hosting providers often disable the WP Cron service and run their own manual Cron service on an interval of their choosing. Contact your hosting provider if scheduled checks fail to run.', 'gpagespeedi' ); ?></p>
				<?php endif; ?>
			</p>

			<p><label for="recheck_interval"><?php esc_html_e( 'Report Expiration / Recheck Interval', 'gpagespeedi' ); ?>:</label></p>
			<select name="recheck_interval" id="recheck_interval">
				<option value="<?php echo esc_attr( DAY_IN_SECONDS );?>" <?php selected( $this->gpi_options['recheck_interval'], DAY_IN_SECONDS ); ?>><?php esc_html_e( '1 Day', 'gpagespeedi' ); ?></option>
				<option value="<?php echo esc_attr( WEEK_IN_SECONDS );?>" <?php selected( $this->gpi_options['recheck_interval'], WEEK_IN_SECONDS ); ?>><?php esc_html_e( '7 Days', 'gpagespeedi' ); ?></option>
				<option value="<?php echo esc_attr( MONTH_IN_SECONDS / 2 );?>" <?php selected( $this->gpi_options['recheck_interval'], MONTH_IN_SECONDS / 2 ); ?>><?php esc_html_e( '15 Days', 'gpagespeedi' ); ?></option>
				<option value="<?php echo esc_attr( MONTH_IN_SECONDS );?>" <?php selected( $this->gpi_options['recheck_interval'], MONTH_IN_SECONDS ); ?>><?php esc_html_e( '30 Days', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description"><?php echo esc_html__( 'When page reporting is running, pages which are newer than the specified Report Expiration will be skipped.', 'gpagespeedi' ) . '<br />' . esc_html__( 'If "Automatically re-check Pagespeed Insights scores" is checked above, this option will control its frequency.', 'gpagespeedi' ); ?></p>

			<?php
				$timestamp = wp_next_scheduled( 'googlepagespeedinsightsworker' );
				if ( $timestamp ) {
					?>
					<p><strong><?php esc_html_e( 'Next Scheduled Check', 'gpagespeedi' ); ?></strong>: <?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'F, d @ g:i a' ) ); ?></p>
					<?php
				}
			?>

			<hr>
			<p><h4><?php esc_html_e( 'Configure which types of URLs should be checked when running reports.', 'gpagespeedi' ); ?></h4></p>
			<p><span style="color:red;"><?php esc_html_e( 'Note', 'gpagespeedi' ); ?></span>: <?php esc_html_e( 'Google Pagespeed will load each page to generate a report. The more pages you select, the longer it will take for the scan to complete.', 'gpagespeedi' ); ?></p>
			<p class="checkbx">
				<input type="checkbox" name="check_pages" id="check_pages" <?php checked( $this->gpi_options['check_pages'] ); ?>/>
				<label for="check_pages"><?php esc_html_e( 'Check Wordpress Pages', 'gpagespeedi' ); ?> (<?php echo esc_html( wp_count_posts( 'page' )->publish ); ?>)</label>
			</p>
			<p class="checkbx">
				<input type="checkbox" name="check_posts" id="check_posts" <?php checked( $this->gpi_options['check_posts'] ); ?>/>
				<label for="check_posts"><?php esc_html_e( 'Check Wordpress Posts', 'gpagespeedi' ); ?> (<?php echo esc_html( wp_count_posts( 'post' )->publish ); ?>)</label>
			</p>
			<p class="checkbx">
				<input type="checkbox" name="check_categories" id="check_categories" <?php checked( $this->gpi_options['check_categories'] ); ?>/>
				<label for="check_categories"><?php esc_html_e( 'Check Category Indexes', 'gpagespeedi' ); ?> (<?php echo esc_html( count( get_categories() ) ) ?>)</label>
			</p>

			<p class="checkbx">
				<input type="checkbox" name="check_custom_urls" id="check_custom_urls" <?php checked( $this->gpi_options['check_custom_urls'] ); ?>/>
				<label for="check_custom_urls"><?php esc_html_e( 'Check Custom URLs', 'gpagespeedi' ); ?> (<?php echo esc_html( apply_filters( 'gpi_custom_urls_count', 0 ) ); ?>)</label>
			</p>

			<?php
				$custom_post_types = apply_filters( 'gpi_custom_post_types', array() );
				if ( ! empty( $custom_post_types ) ) :
					?>
					<p class="checkbx">
						<?php esc_html_e( 'Custom Post Types', 'gpagespeedi' ); ?>:
					</p>

					<div class="padded" style="padding-top: 0px;">
					<?php
						foreach ( $custom_post_types  as $custom_post_type ) :
							?>
							<p class="checkbx posttypes">
								<input type="checkbox" name="cpt_whitelist[]" id="<?php echo esc_attr( 'cpt_' . $custom_post_type['value'] ); ?>" value="<?php echo esc_attr( $custom_post_type['value'] ); ?>" <?php if ( $custom_post_type['checked'] ) { echo esc_html( 'checked="checked"' ); } ?> />
								<label for="<?php echo esc_attr( 'cpt_' . $custom_post_type['value'] ); ?>"><?php echo esc_html( $custom_post_type['value'] ); ?> (<?php echo esc_html( $custom_post_type['count'] ); ?>)</label>
							</p>
							<?php
						endforeach;
					?>
					</div>
					<?php
				endif;
			?>
		</div>
	</div>

	<div class="row framed boxsizing">
		<div class="boxheader large toggle">
			<span class="left gear"><?php esc_html_e( 'Advanced Configuration', 'gpagespeedi' ); ?></span>
			<span class="right"></span>
		</div>
		<div class="padded hidden">
			<p><?php esc_html_e( 'For most users, the following settings can be left at their defaults unless otherwise instructed by support.', 'gpagespeedi' ); ?></p>

			<p><label for="max_execution_time"><?php esc_html_e( 'Maximum Execution Time', 'gpagespeedi' ); ?>:</label></p>
			<select name="max_execution_time" id="max_execution_time">
				<option value="60" <?php selected( $this->gpi_options['max_execution_time'], 60 ); ?>><?php esc_html_e( '1 Minute', 'gpagespeedi' ); ?></option>
				<option value="300" <?php selected( $this->gpi_options['max_execution_time'], 300 ); ?>><?php esc_html_e( '5 Minutes', 'gpagespeedi' ); ?></option>
				<option value="600" <?php selected( $this->gpi_options['max_execution_time'], 600 ); ?>><?php esc_html_e( '10 Minutes', 'gpagespeedi' ); ?></option>
				<option value="900" <?php selected( $this->gpi_options['max_execution_time'], 900 ); ?>><?php esc_html_e( '15 Minutes', 'gpagespeedi' ); ?></option>
				<option value="1800" <?php selected( $this->gpi_options['max_execution_time'], 1800 ); ?>><?php esc_html_e( '30 Minutes', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'The default value of 5 minutes is fine for most sites.', 'gpagespeedi' ); ?> <?php esc_html_e( 'Increasing this value may help if your page reports do not finish completely.', 'gpagespeedi' ); ?></p>
			<?php if ( apply_filters( 'gpi_set_time_limit_disabled', false ) ) : ?> 
				<p class="description"><span style="color:red;"><?php esc_html_e( 'Notice', 'gpagespeedi' ); ?>:</span> <?php esc_html_e( 'We have detected that your server may not allow the maximum execution time to be overridden by this plugin. If you experience problems with pagespeed report scans failing to complete, contact your hosting provider. Default Maximum Execution Time: ', 'gpagespeedi' ); ?> <strong><?php echo esc_html( ini_get( 'max_execution_time' ) ) . ' ' . __( 'seconds', 'gpagespeedi' ); ?></strong></p>
			<?php endif; ?>

			<p><label for="max_run_time"><?php esc_html_e( 'Maximum Script Run Time', 'gpagespeedi' ); ?>:</label></p>
			<select name="max_run_time" id="max_run_time">
				<option value="0" <?php selected( $this->gpi_options['max_run_time'], 0 ); ?>><?php esc_html_e( 'No Limit', 'gpagespeedi' ); ?></option>
				<option value="15" <?php selected( $this->gpi_options['max_run_time'], 15 ); ?>><?php esc_html_e( '15 Seconds', 'gpagespeedi' ); ?></option>
				<option value="30" <?php selected( $this->gpi_options['max_run_time'], 30 ); ?>><?php esc_html_e( '30 Seconds', 'gpagespeedi' ); ?></option>
				<option value="45" <?php selected( $this->gpi_options['max_run_time'], 45 ); ?>><?php esc_html_e( '45 Seconds', 'gpagespeedi' ); ?></option>
				<option value="60" <?php selected( $this->gpi_options['max_run_time'], 60 ); ?>><?php esc_html_e( '60 Seconds', 'gpagespeedi' ); ?></option>
				<option value="90" <?php selected( $this->gpi_options['max_run_time'], 90 ); ?>><?php esc_html_e( '90 Seconds', 'gpagespeedi' ); ?></option>
				<option value="120" <?php selected( $this->gpi_options['max_run_time'], 120 ); ?>><?php esc_html_e( '120 Seconds', 'gpagespeedi' ); ?></option>
				<option value="150" <?php selected( $this->gpi_options['max_run_time'], 150 ); ?>><?php esc_html_e( '150 Seconds', 'gpagespeedi' ); ?></option>
				<option value="180" <?php selected( $this->gpi_options['max_run_time'], 180 ); ?>><?php esc_html_e( '180 Seconds', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'Some web hosting providers have limits on script runtime that cannot be overridden. If your scans do not finish completely and changing the Maximum Execution Time does not resolve the problem, this setting may help. Once the specified run time is reached a new scan process will automatically start.', 'gpagespeedi' ); ?>
				<?php esc_html_e( 'It is best to find the largest value that still allows the script to complete successfully. Test first at 30 seconds, then raise the value to 45 if your test is successful. Continue until you find the maximum runtime your host allows.', 'gpagespeedi' ); ?>
			</p>

			<p><label for="sleep_time"><?php esc_html_e( 'Report Throttling Delay Time', 'gpagespeedi' ); ?>:</label></p>
			<select name="sleep_time" id="sleep_time">
				<option value="0" <?php selected( $this->gpi_options['sleep_time'], 0 ); ?>><?php esc_html_e( '0 Seconds', 'gpagespeedi' ); ?></option>
				<option value="1" <?php selected( $this->gpi_options['sleep_time'], 1 ); ?>><?php esc_html_e( '1 Seconds', 'gpagespeedi' ); ?></option>
				<option value="2" <?php selected( $this->gpi_options['sleep_time'], 2 ); ?>><?php esc_html_e( '2 Seconds', 'gpagespeedi' ); ?></option>
				<option value="3" <?php selected( $this->gpi_options['sleep_time'], 3 ); ?>><?php esc_html_e( '3 Seconds', 'gpagespeedi' ); ?></option>
				<option value="4" <?php selected( $this->gpi_options['sleep_time'], 4 ); ?>><?php esc_html_e( '4 Seconds', 'gpagespeedi' ); ?></option>
				<option value="5" <?php selected( $this->gpi_options['sleep_time'], 5 ); ?>><?php esc_html_e( '5 Seconds', 'gpagespeedi' ); ?></option>
				<option value="10" <?php selected( $this->gpi_options['sleep_time'], 10 ); ?>><?php esc_html_e( '10 Seconds', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'The default value of 0 seconds is fine for most sites.', 'gpagespeedi' ); ?>
				<br />
				<?php esc_html_e( 'Raising this value will slow down page reporting, but may help provide more accurate reports on poorly performing web servers', 'gpagespeedi' ); ?>
			</p>

			<p><label for="heartbeat"><?php esc_html_e( 'Report Status Indicator Refresh Rate', 'gpagespeedi' ); ?>:</label></p>
			<select name="heartbeat" id="heartbeat">
				<option value="fast" <?php selected( $this->gpi_options['heartbeat'], 'fast' ); ?>><?php esc_html_e( 'Fast', 'gpagespeedi' ); ?></option>
				<option value="standard" <?php selected( $this->gpi_options['heartbeat'], 'standard' ); ?>><?php esc_html_e( 'Standard' ); ?></option>
				<option value="slow" <?php selected( $this->gpi_options['heartbeat'], 'slow' ); ?>><?php esc_html_e( 'Slow', 'gpagespeedi' ); ?></option>
				<option value="disabled" <?php selected( $this->gpi_options['heartbeat'], 'disabled' ); ?>><?php esc_html_e( 'Disabled - manually refresh pages to update status', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'The default value of "Fast" is fine for most sites.', 'gpagespeedi' ); ?>
				<br />
				<?php esc_html_e( 'More frequent updates may impact Pagespeed reports on poorly performing servers, reduce the rate if you are experiencing issues.', 'gpagespeedi' ); ?>
			</p>

			<p class="checkbx">
				<input type="checkbox" name="log_api_errors" id="log_api_errors" <?php checked( $this->gpi_options['log_api_errors'] ); ?>/>
				<label for="log_api_errors"><?php esc_html_e( 'Log API Exceptions', 'gpagespeedi' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'API error logs will be stored for up to 7 days.', 'gpagespeedi' ); ?> <a href="?page=google-pagespeed-insights&amp;render=logs"><?php esc_html_e( 'View Logs', 'gpagespeedi' ); ?></a></p>

			<p><label for="sleep_time"><?php esc_html_e( 'Delete Data', 'gpagespeedi' ); ?>:</label></p>
			<select name="purge_all_data" id="purge_all_data">
				<option><?php esc_html_e( 'Do Nothing', 'gpagespeedi' ); ?></option>
				<option value="purge_reports"><?php esc_html_e( 'Delete Reports Only', 'gpagespeedi' ); ?></option>
				<option value="purge_everything"><?php esc_html_e( 'Delete EVERYTHING', 'gpagespeedi' ); ?></option>
			</select>
			<p class="description"><span style="color:red;"><?php esc_html_e( 'Warning', 'gpagespeedi' ); ?>:</span> <?php esc_html_e( 'This option can not be reversed.', 'gpagespeedi' ); ?></p>
		</div>
	</div>

	<input type="hidden" name="action" value="save-options" />
	<?php
		wp_nonce_field( 'gpi-save-options' );

		if ( $worker_status = apply_filters( 'gpi_check_status', false ) ) :
			submit_button( esc_html__( 'Save Options', 'gpagespeedi' ), 'secondary', 'submit', false, array( 'disabled' => true ) );
		else :
			submit_button( esc_html__( 'Save Options', 'gpagespeedi' ), 'primary', 'submit', false );
		endif;
	?>

	<?php include GPI_DIRECTORY . '/templates/parts/nitropack.php'; ?>
</form>