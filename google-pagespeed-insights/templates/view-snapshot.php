<?php
/**
 * Template - View Snapshot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="toolbar">
	<div class="left">
		<form method="get" action="" name="filter">
			<input type="hidden" name="page" value="google-pagespeed-insights" />
			<input type="hidden" name="render" value="view-snapshot" />
			<div class="tablenav top snapshots">
				<select name="snapshot_id">
					<?php
						$similar_snapshots = apply_filters( 'gpi_similar_snapshots', array(), intval( $_GET['snapshot_id'] ) );

						foreach( $similar_snapshots as $snapshot ) :
							?>
							<option value="<?php echo intval( $snapshot['ID'] ); ?>" <?php selected( $snapshot['ID'], intval( $_GET['snapshot_id'] ) ); ?>><?php echo esc_html( date_i18n( 'M d Y g:ia', $snapshot['snaptime'] ) ); ?></option>
							<?php
						endforeach;
					?>
				</select>
				<?php
					submit_button( esc_html__( 'Apply', 'gpagespeedi' ), 'button', false, false, array( 'id' => 'post-query-submit' ) );
				?>
				<div class="comment" data-selector="snapshot"></div>
			</div>
		</form>
	</div>
	<?php if ( count( $similar_snapshots ) >= 2 ) : ?>
		<div class="right">
			<form method="get" action="" name="filter">
				<input type="hidden" name="page" value="google-pagespeed-insights" />
				<input type="hidden" name="render" value="view-snapshot" />
				<input type="hidden" name="snapshot_id" value="<?php echo intval( $_GET['snapshot_id'] ); ?>" />
				<div class="tablenav top snapshots">
					<select name="compare_id">
						<?php
							foreach( $similar_snapshots as $snapshot ) :
								if ( $_GET['snapshot_id'] == $snapshot['ID'] ) :
									continue;
								endif;
								$current_compare_id = isset( $_GET['compare_id'] ) ? intval( $_GET['compare_id'] ) : false
								?>
								<option value="<?php echo intval( $snapshot['ID'] ); ?>" <?php selected( $snapshot['ID'], $current_compare_id ); ?>><?php echo esc_html( date_i18n( 'M d Y g:ia', $snapshot['snaptime'] ) ); ?></option>
								<?php
							endforeach;
						?>
					</select>
					<?php
						submit_button( esc_html__( 'Compare', 'gpagespeedi' ), 'button', false, false, array( 'id' => 'post-query-submit' ) );
					?>
					<div class="comment" data-selector="compare"></div>
				</div>
			</form>
		</div>
	<?php endif; ?>
</div>

<div class="<?php if ( isset( $_GET['compare_id'] ) ) { echo esc_attr( 'left half' ); } ?>">
	<div class="row">
		<div class="top-row boxsizing pagespeed_gauge_wrapper">
			<div class="score_chart_div">
				<img class="pagespeed_needle" data-selector="snapshot" src="<?php echo esc_url( GPI_PUBLIC_PATH . 'assets/images/pagespeed_gauge_needle.png' ); ?>" width="204" height="204" alt="" />
				<div class="score_text"><span class="score" data-selector="snapshot"></span><span class="label"><?php esc_html_e( 'score', 'gpagespeedi' ); ?></span></div>
			</div>
		</div>
		<div class="top-row boxsizing framed pagespeed_avg_lab_data_wrapper">
			<div class="boxheader">
				<span class="left">
					<?php esc_html_e('Average Lab Data Scores', 'gpagespeedi'); ?>
					<span class="light">(<?php esc_html_e('Click for detailed report', 'gpagespeedi'); ?>)</span>
				</span>
			</div>
			<div class="avg_lab_data" data-selector="snapshot"></div>
		</div>
	</div>
	<div class="row boxsizing framed largest_improvement">
		<div class="boxheader">
			<span class="left"><?php esc_html_e( 'Largest Areas for Improvement', 'gpagespeedi' ); ?></span>
			<span class="right"><?php esc_html_e( 'Pages Impacted', 'gpagespeedi' ); ?></span>
			<span class="right"><?php esc_html_e( 'Average Score', 'gpagespeedi' ); ?></span>
		</div>
		<table class="stats" data-selector="snapshot"></table>
	</div>
	<div class="row scores_div">
		<div class="halfwidth boxsizing framed left highest_scores">
			<div class="boxheader">
				<span class="left"><?php esc_html_e( 'Highest Scoring Pages', 'gpagespeedi' ); ?></span>
				<span class="right"><?php esc_html_e( 'Score', 'gpagespeedi' ); ?></span>
			</div>
			<table class="stats" data-selector="snapshot"></table>
		</div>
		<div class="halfwidth boxsizing framed right lowest_scores">
			<div class="boxheader">
				<span class="left"><?php esc_html_e( 'Lowest Scoring Pages', 'gpagespeedi' ); ?></span>
				<span class="right"><?php esc_html_e( 'Score', 'gpagespeedi' ); ?></span>
			</div>
			<table class="stats" data-selector="snapshot"></table>
		</div>
	</div>
</div>

<?php if ( isset( $_GET['compare_id'] ) ) : ?>

	<div class="right half">
		<div class="row">
			<div class="top-row boxsizing pagespeed_gauge_wrapper">
				<div class="score_chart_div">
					<img class="pagespeed_needle" data-selector="compare" src="<?php echo esc_url( GPI_PUBLIC_PATH . 'assets/images/pagespeed_gauge_needle.png' ); ?>" width="204" height="204" alt="" />
					<div class="score_text"><span class="score" data-selector="compare"></span><span class="label"><?php esc_html_e( 'score', 'gpagespeedi' ); ?></span></div>
				</div>
			</div>
			<div class="top-row boxsizing framed pagespeed_avg_lab_data_wrapper">
				<div class="boxheader">
					<span class="left">
						<?php esc_html_e('Average Lab Data Scores', 'gpagespeedi'); ?>
						<span class="light">(<?php esc_html_e('Click for detailed report', 'gpagespeedi'); ?>)</span>
					</span>
				</div>
				<div class="avg_lab_data" data-selector="compare"></div>
			</div>
		</div>
		<div class="row boxsizing framed largest_improvement">
			<div class="boxheader">
				<span class="left"><?php esc_html_e( 'Largest Areas for Improvement', 'gpagespeedi' ); ?></span>
				<span class="right"><?php esc_html_e( 'Pages Impacted', 'gpagespeedi' ); ?></span>
				<span class="right"><?php esc_html_e( 'Average Score', 'gpagespeedi' ); ?></span>
			</div>
			<table class="stats" data-selector="compare"></table>
		</div>
		<div class="row scores_div">
			<div class="halfwidth boxsizing framed left highest_scores">
				<div class="boxheader">
					<span class="left"><?php esc_html_e( 'Highest Scoring Pages', 'gpagespeedi' ); ?></span>
					<span class="right"><?php esc_html_e( 'Score', 'gpagespeedi' ); ?></span>
				</div>
				<table class="stats" data-selector="compare"></table>
			</div>
			<div class="halfwidth boxsizing framed right lowest_scores">
				<div class="boxheader">
					<span class="left"><?php esc_html_e( 'Lowest Scoring Pages', 'gpagespeedi' ); ?></span>
					<span class="right"><?php esc_html_e( 'Score', 'gpagespeedi' ); ?></span>
				</div>
				<table class="stats" data-selector="compare"></table>
			</div>
		</div>
	</div>

<?php endif; ?>

<?php include GPI_DIRECTORY . '/templates/parts/nitropack.php'; ?>
