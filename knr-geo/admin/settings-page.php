<?php
/**
 * Settings Page UI
 *
 * @package Big_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the main admin settings page.
 */
function big_geo_settings_page() {
	// Initialize class objects for status display.
	$audit    = new BIG_GEO_Robots_Audit();
	$llms     = new BIG_GEO_LLMS_Txt( get_option( 'big_geo_settings', array() ) );
	$llms_full = new BIG_GEO_LLMS_Full( get_option( 'big_geo_settings', array() ) );

	// Determine current tab.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

	?>
	<div class="wrap big-geo-wrap">
		<div class="big-geo-header">
			<span class="plugin-logo">&#127758;</span>
			<h1><?php esc_html_e( 'Big GEO', 'knr-geo' ); ?></h1>
			<span class="version-badge">v<?php echo esc_html( BIG_GEO_VERSION ); ?></span>
		</div>

		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=knr-geo&tab=dashboard' ) ); ?>"
			   class="nav-tab <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Dashboard', 'knr-geo' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=knr-geo&tab=robots' ) ); ?>"
			   class="nav-tab <?php echo 'robots' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Robots.txt', 'knr-geo' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=knr-geo&tab=llms' ) ); ?>"
			   class="nav-tab <?php echo 'llms' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'LLMS.txt', 'knr-geo' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=knr-geo&tab=llms-full' ) ); ?>"
			   class="nav-tab <?php echo 'llms-full' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'LLMS-Full.txt', 'knr-geo' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=knr-geo&tab=settings' ) ); ?>"
			   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings', 'knr-geo' ); ?>
			</a>
		</nav>


		<div class="big-geo-tab-content">


			<?php if ( 'dashboard' === $active_tab ) : ?>
			<div class="big-geo-dashboard-grid">

				<div class="big-geo-section status-card">
					<h3><?php esc_html_e( 'AI Crawlers Status', 'knr-geo' ); ?></h3>
					<p><?php esc_html_e( 'Check if major AI crawlers can access your site via robots.txt.', 'knr-geo' ); ?></p>
					<div class="big-geo-actions">
						<button id="big-geo-run-audit" class="button button-primary">
							<?php esc_html_e( 'Run AI Crawlers Audit', 'knr-geo' ); ?>
						</button>
					</div>
					<div id="big-geo-audit-results" class="big-geo-audit-table-wrap">
						<?php echo wp_kses_post( $audit->get_audit_html() ); ?>
					</div>
				</div>

				<div class="big-geo-section status-card">
					<h3><?php esc_html_e( 'LLMS Files', 'knr-geo' ); ?></h3>
					<p><?php esc_html_e( 'Generate llms.txt and llms-full.txt for AI language models.', 'knr-geo' ); ?></p>
					<?php
					$llms_exists = $llms->file_exists();
					$llms_full_exists = $llms_full->file_exists();
					?>
					<ul style="margin:0;padding:0 0 0 1.2em;">
						<li>
							<strong>llms.txt</strong>:
							<?php if ( $llms_exists ) : ?>
								<span class="status-badge allowed"><?php esc_html_e( 'Exists', 'knr-geo' ); ?></span>
								<small><?php echo esc_html( $llms->get_file_time() ); ?></small>
							<?php else : ?>
								<span class="status-badge blocked"><?php esc_html_e( 'Not generated', 'knr-geo' ); ?></span>
							<?php endif; ?>
						</li>
						<li>
							<strong>llms-full.txt</strong>:
							<?php if ( $llms_full_exists ) : ?>
								<span class="status-badge allowed"><?php esc_html_e( 'Exists', 'knr-geo' ); ?></span>
								<small><?php echo esc_html( $llms_full->get_file_time() ); ?></small>
							<?php else : ?>
								<span class="status-badge blocked"><?php esc_html_e( 'Not generated', 'knr-geo' ); ?></span>
							<?php endif; ?>
						</li>
					</ul>
				</div>

			</div><!-- .big-geo-dashboard-grid -->
			<?php endif; ?>

			<?php if ( 'robots' === $active_tab ) : ?>
			<div class="big-geo-section">
				<h2><?php esc_html_e( 'AI Crawler Discovery (robots.txt)', 'knr-geo' ); ?></h2>
				<p><?php esc_html_e( 'Ensure AI bots like GPTBot, ClaudeBot, PerplexityBot, and others can crawl your site. Big GEO audits your robots.txt and helps you fix any issues.', 'knr-geo' ); ?></p>

				<?php
				$robots_type = $audit->detect_tier();
				if ( 'physical' === $robots_type ) :
				?>
				<div class="big-geo-notice notice-warning">
					<?php esc_html_e( 'A physical robots.txt file exists in your site root. You can view/edit it below and write updated contents.', 'knr-geo' ); ?>
				</div>
				<?php endif; ?>

				<div class="big-geo-actions">
					<button id="big-geo-run-audit" class="button button-primary">
						<?php esc_html_e( 'Run AI Crawlers Audit', 'knr-geo' ); ?>
					</button>
					<button id="big-geo-apply-fix" class="button button-secondary">
						<?php esc_html_e( 'Apply Virtual Fix (WP Filter)', 'knr-geo' ); ?>
					</button>
				</div>
				<div id="big-geo-audit-results" class="big-geo-audit-table-wrap" style="margin-top:16px;">
					<p class="description"><?php esc_html_e( 'Click "Run AI Crawlers Audit" to check your robots.txt.', 'knr-geo' ); ?></p>
				</div>

				<hr style="margin:24px 0;">
				<h3><?php esc_html_e( 'Write Physical robots.txt', 'knr-geo' ); ?></h3>
				<p><?php esc_html_e( 'Optionally write a physical robots.txt file. The content below is pre-filled with AI-friendly rules.', 'knr-geo' ); ?></p>
				<textarea id="big-geo-robots-content" class="big-geo-robots-editor" rows="12"><?php echo esc_textarea( $audit->generate_corrected_robots() ); ?></textarea>
				<div class="big-geo-actions">
					<button id="big-geo-write-robots" class="button button-primary">
						<?php esc_html_e( 'Write Physical robots.txt', 'knr-geo' ); ?>
					</button>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( 'llms' === $active_tab ) : ?>
			<div class="big-geo-section">
				<h2><?php esc_html_e( 'LLMS.txt Generator', 'knr-geo' ); ?></h2>
				<p><?php esc_html_e( 'Generate a llms.txt file that helps AI language models understand your site structure, key pages, and important links.', 'knr-geo' ); ?></p>
				<?php if ( $llms->file_exists() ) : ?>
				<div class="big-geo-file-meta">
					<span><strong><?php esc_html_e( 'File:', 'knr-geo' ); ?></strong> llms.txt</span>
					<span><strong><?php esc_html_e( 'Last updated:', 'knr-geo' ); ?></strong> <?php echo esc_html( $llms->get_file_time() ); ?></span>
					<span><a href="<?php echo esc_url( esc_url( home_url( '/llms.txt' ) ) ); ?>" target="_blank"><?php esc_html_e( 'View file', 'knr-geo' ); ?></a></span>
				</div>
				<?php else : ?>
				<div class="big-geo-notice notice-warning"><?php esc_html_e( 'llms.txt has not been generated yet.', 'knr-geo' ); ?></div>
				<?php endif; ?>
				<div class="big-geo-actions" style="margin-top:16px;">
					<button id="big-geo-generate-llms" class="button button-primary">
						<?php esc_html_e( 'Generate & Save llms.txt', 'knr-geo' ); ?>
					</button>
					<?php if ( $llms->file_exists() ) : ?>
					<a id="big-geo-llms-link" href="<?php echo esc_url( esc_url( home_url( '/llms.txt' ) ) ); ?>" class="button" target="_blank">
						<?php esc_html_e( 'View llms.txt', 'knr-geo' ); ?>
					</a>
					<?php endif; ?>
				</div>
				<div id="big-geo-llms-preview" class="big-geo-preview-box" style="display:none;"></div>
			</div>
			<?php endif; ?>

			<?php if ( 'llms-full' === $active_tab ) : ?>
			<div class="big-geo-section">
				<h2><?php esc_html_e( 'LLMS-Full.txt Generator', 'knr-geo' ); ?></h2>
				<p><?php esc_html_e( 'Generate a llms-full.txt file containing full content from your published posts and pages. This allows AI models to deeply understand your site content.', 'knr-geo' ); ?></p>
				<?php if ( $llms_full->file_exists() ) : ?>
				<div class="big-geo-file-meta">
					<span><strong><?php esc_html_e( 'File:', 'knr-geo' ); ?></strong> llms-full.txt</span>
					<span><strong><?php esc_html_e( 'Last updated:', 'knr-geo' ); ?></strong> <?php echo esc_html( $llms_full->get_file_time() ); ?></span>
					<span><a href="<?php echo esc_url( esc_url( home_url( '/llms-full.txt' ) ) ); ?>" target="_blank"><?php esc_html_e( 'View file', 'knr-geo' ); ?></a></span>
				</div>
				<?php else : ?>
				<div class="big-geo-notice notice-warning"><?php esc_html_e( 'llms-full.txt has not been generated yet.', 'knr-geo' ); ?></div>
				<?php endif; ?>
				<div class="big-geo-actions" style="margin-top:16px;">
					<button id="big-geo-preview-llms-full" class="button">
						<?php esc_html_e( 'Preview Content', 'knr-geo' ); ?>
					</button>
					<button id="big-geo-generate-llms-full" class="button button-primary">
						<?php esc_html_e( 'Generate & Save llms-full.txt', 'knr-geo' ); ?>
					</button>
					<?php if ( $llms_full->file_exists() ) : ?>
					<a id="big-geo-llms-full-link" href="<?php echo esc_url( esc_url( home_url( '/llms-full.txt' ) ) ); ?>" class="button" target="_blank">
						<?php esc_html_e( 'View llms-full.txt', 'knr-geo' ); ?>
					</a>
					<?php endif; ?>
				</div>
				<div id="big-geo-llms-full-preview" class="big-geo-preview-box" style="display:none;"></div>
			</div>
			<?php endif; ?>

			<?php if ( 'settings' === $active_tab ) : ?>
			<div class="big-geo-section">
				<h2><?php esc_html_e( 'Plugin Settings', 'knr-geo' ); ?></h2>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'big_geo_settings_group' );
					do_settings_sections( 'big-geo' );
					submit_button();
					?>
				</form>
			</div>
			<?php endif; ?>

		</div><!-- .big-geo-tab-content -->
	</div><!-- .big-geo-wrap -->
	<?php
}

/**
 * Save plugin settings.
 */
function big_geo_save_settings() {
	check_ajax_referer( 'big_geo_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$settings = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
	$clean = array();
	foreach ( $settings as $key => $val ) {
		$clean[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $val ) );
	}
	update_option( 'big_geo_settings', $clean );
	wp_send_json_success( array( 'message' => 'Settings saved.' ) );
}
