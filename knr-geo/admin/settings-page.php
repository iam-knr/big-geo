<?php
/**
 * Big GEO - Admin Settings Page
 * Tabbed interface for managing llms.txt, llms-full.txt, and AI Crawler Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add admin menu
add_action( 'admin_menu', 'big_geo_admin_menu' );
function big_geo_admin_menu() {
	add_menu_page(
		'Big GEO Settings',
		'Big GEO',
		'manage_options',
		'knr-geo',
		'big_geo_settings_page',
		'dashicons-admin-site-alt3',
		80
	);
}

/**
 * Settings Page UI
 */
function big_geo_settings_page() {
	// Initialize classes
	$audit = new BIG_GEO_Robots_Audit();
	$llms = new BIG_GEO_LLMS_Txt();
	$llms_full = new BIG_GEO_LLMS_Full();
	
	// Handle settings save
	if ( isset( $_POST['big_geo_save_settings'] ) && check_admin_referer( 'big_geo_settings_nonce' ) ) {
		big_geo_save_settings();
	}
	
	// Get current tab
	$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
	?>
	<div class="wrap big-geo-settings">
		<h1><span class="dashicons dashicons-admin-settings"></span> Big GEO - AI Discovery Manager</h1>
		
		<?php settings_errors( 'big_geo_messages' ); ?>
		
		<nav class="nav-tab-wrapper">
			<a href="?page=knr-geo&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
			<a href="?page=knr-geo&tab=llms-txt" class="nav-tab <?php echo $active_tab == 'llms-txt' ? 'nav-tab-active' : ''; ?>">llms.txt</a>
			<a href="?page=knr-geo&tab=llms-full" class="nav-tab <?php echo $active_tab == 'llms-full' ? 'nav-tab-active' : ''; ?>">llms-full.txt</a>
			<a href="?page=knr-geo&tab=ai-crawlers" class="nav-tab <?php echo $active_tab == 'ai-crawlers' ? 'nav-tab-active' : ''; ?>">AI Crawler Audit</a>
		</nav>
		
		<div class="tab-content mt-20">
			<?php
			switch ( $active_tab ) {
				case 'dashboard':
					big_geo_render_dashboard( $llms, $llms_full, $audit );
					break;
				case 'llms-txt':
					big_geo_render_llms_txt_tab( $llms );
					break;
				case 'llms-full':
					big_geo_render_llms_full_tab( $llms_full );
					break;
				case 'ai-crawlers':
					big_geo_render_ai_crawlers_tab( $audit );
					break;
			}
			?>
		</div>
	</div>
	
	<script>
	jQuery(document).ready(function($) {
		// AJAX for Preview
		$('.big-geo-preview-btn').on('click', function(e) {
			e.preventDefault();
			const btn = $(this);
			const target = btn.data('target');
			const type = btn.data('type');
			const nonce = '<?php echo wp_create_nonce("big_geo_generate"); ?>';
			
			btn.addClass('updating-message').prop('disabled', true);
			
			$.post(ajaxurl, {
				action: 'big_geo_preview_' + type,
				nonce: nonce
			}, function(response) {
				btn.removeClass('updating-message').prop('disabled', false);
				if (response.success) {
					$('#' + target).val(response.data.content).show();
				}
			});
		});
		
		// AJAX for Generate
		$('.big-geo-generate-btn').on('click', function(e) {
			e.preventDefault();
			const btn = $(this);
			const type = btn.data('type');
			const nonce = '<?php echo wp_create_nonce("big_geo_generate"); ?>';
			
			if (!confirm('This will write a physical file to your site root. Proceed?')) return;
			
			btn.addClass('updating-message').prop('disabled', true);
			
			$.post(ajaxurl, {
				action: 'big_geo_generate_' + type,
				nonce: nonce
			}, function(response) {
				btn.removeClass('updating-message').prop('disabled', false);
				alert(response.data.message);
				if (response.success) location.reload();
			});
		});
	});
	</script>
	<?php
}

/**
 * Render Dashboard Tab
 */
function big_geo_render_dashboard( $llms, $llms_full, $audit ) {
	?>
	<div class="big-geo-dashboard-grid">
		<div class="card status-card">
			<h3>llms.txt Status</h3>
			<p>
				<?php if ( $llms->file_exists() ) : ?>
					<span class="status-badge success">Active</span>
					<br><small>Last Generated: <?php echo date( 'Y-m-d H:i', $llms->get_file_time() ); ?></small>
				<?php else : ?>
					<span class="status-badge error">Not Found</span>
				<?php endif; ?>
			</p>
			<a href="?page=knr-geo&tab=llms-txt" class="button">Manage llms.txt</a>
		</div>
		
		<div class="card status-card">
			<h3>llms-full.txt Status</h3>
			<p>
				<?php if ( $llms_full->file_exists() ) : ?>
					<span class="status-badge success">Active</span>
					<br><small>Last Generated: <?php echo date( 'Y-m-d H:i', $llms_full->get_file_time() ); ?></small>
				<?php else : ?>
					<span class="status-badge error">Not Found</span>
				<?php endif; ?>
			</p>
			<a href="?page=knr-geo&tab=llms-full" class="button">Manage llms-full.txt</a>
		</div>
		
		<div class="card status-card">
			<h3>AI Crawler Audit</h3>
			<?php $report = $audit->run_audit(); ?>
			<p>
				<span class="status-badge <?php echo ! $report['all_allowed'] ? 'warning' : 'success'; ?>">
					<?php echo count( array_filter( $report['bots'], function($b){ return $b['status'] === 'allowed'; } ) ); ?> Bots Allowed
				</span>
			</p>
			<a href="?page=knr-geo&tab=ai-crawlers" class="button">View Audit</a>
		</div>
	</div>
	<?php
}

/**
 * Render llms.txt Tab
 */
function big_geo_render_llms_txt_tab( $llms ) {
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	$selected_types = get_option( 'big_geo_post_types', array( 'post', 'page' ) );
	?>
	<div class="card">
		<h2>Module 1: llms.txt Configuration</h2>
		<form method="post">
			<?php wp_nonce_field( 'big_geo_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th>Include Post Types</th>
					<td>
						<?php foreach ( $post_types as $type ) : ?>
							<label>
								<input type="checkbox" name="big_geo_post_types[]" value="<?php echo $type->name; ?>" <?php checked( in_array( $type->name, $selected_types ) ); ?>>
								<?php echo $type->label; ?>
							</label><br>
											<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th>Site Description (Markdown)</th>
					<td>
						<textarea name="big_geo_site_description" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'big_geo_site_description' ) ); ?></textarea>
						<p class="description">Add an intro description about your site for AI models.</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="big_geo_save_settings" class="button button-primary" value="Save Settings">
			</p>
		</form>
		
		<hr>
		
		<h3>Generation Control</h3>
		<p>
			<button class="button big-geo-preview-btn" data-type="llms_txt" data-target="llms-txt-preview">Preview Content</button>
			<button class="button button-primary big-geo-generate-btn" data-type="llms_txt">Generate physical llms.txt file</button>
		</p>
		<textarea id="llms-txt-preview" class="large-text code" rows="15" style="display:none;" readonly></textarea>
	</div>
	<?php
}

/**
 * Render llms-full.txt Tab
 */
function big_geo_render_llms_full_tab( $llms_full ) {
	?>
	<div class="card">
		<h2>Module 2: llms-full.txt Configuration</h2>
		<form method="post">
			<?php wp_nonce_field( 'big_geo_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th>Enable Full Content</th>
					<td>
						<label>
							<input type="checkbox" name="big_geo_llms_full_enabled" value="1" <?php checked( get_option( 'big_geo_llms_full_enabled' ), '1' ); ?>>
							Allow AI models to read full post content
						</label>
					</td>
				</tr>
				<tr>
					<th>Content Cleaning</th>
					<td>
						<label>
							<input type="checkbox" name="big_geo_strip_shortcodes" value="1" <?php checked( get_option( 'big_geo_strip_shortcodes' ), '1' ); ?>>
							Strip WordPress shortcodes
						</label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="big_geo_save_settings" class="button button-primary" value="Save Settings">
			</p>
		</form>
		
		<hr>
		
		<h3>Generation Control</h3>
		<p>
			<button class="button big-geo-preview-btn" data-type="llms_full" data-target="llms-full-preview">Preview Content</button>
			<button class="button button-primary big-geo-generate-btn" data-type="llms_full">Generate physical llms-full.txt file</button>
		</p>
		<textarea id="llms-full-preview" class="large-text code" rows="15" style="display:none;" readonly></textarea>
	</div>
	<?php
}

/**
 * Render AI Crawlers Tab
 */
function big_geo_render_ai_crawlers_tab( $audit ) {
	$report = $audit->run_audit();
	?>
	<div class="card">
		<h2>Module 3: AI Crawler Audit</h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>AI Bot Name</th>
					<th>Organization</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['bots'] as $bot => $data ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $bot ); ?></strong></td>
						<td><?php echo esc_html( $data['label'] ); ?></td>
						<td>
							<?php if ( $data['status'] === 'allowed' ) : ?>
								<span class="status-badge success">Allowed</span>
							<?php else : ?>
								<span class="status-badge error">Blocked</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<hr>
		
		<h3>Fix AI Discovery (robots.txt)</h3>
		<?php if ( file_exists( ABSPATH . 'robots.txt' ) ) : ?>
			<div class="notice notice-warning inline">
				<p><strong>Physical robots.txt detected!</strong> WordPress filter is bypassed. You must update the file manually or use the button below if writable.</p>
			</div>
		<?php else : ?>
			<div class="notice notice-success inline">
				<p>No physical robots.txt found. Using virtual robots.txt filter.</p>
			</div>
		<?php endif; ?>
		
		<p>
			<button class="button button-primary" onclick="alert('Applying fix...')">Update robots.txt for AI</button>
		</p>
	</div>
	<?php
}

/**
 * Save Settings Logic
 */
function big_geo_save_settings() {
	if ( isset( $_POST['big_geo_post_types'] ) ) {
		update_option( 'big_geo_post_types', array_map( 'sanitize_text_field', $_POST['big_geo_post_types'] ) );
	}
	
	if ( isset( $_POST['big_geo_site_description'] ) ) {
		update_option( 'big_geo_site_description', sanitize_textarea_field( $_POST['big_geo_site_description'] ) );
	}
	
	update_option( 'big_geo_llms_full_enabled', isset( $_POST['big_geo_llms_full_enabled'] ) ? '1' : '0' );
	update_option( 'big_geo_strip_shortcodes', isset( $_POST['big_geo_strip_shortcodes'] ) ? '1' : '0' );
	
	add_settings_error( 'big_geo_messages', 'big_geo_saved', 'Settings saved successfully!', 'updated' );
}
