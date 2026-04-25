<?php
/**
 * Big GEO - Admin Settings Page
 * Tabbed interface for managing llms.txt, llms-full.txt, and robots.txt audit
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

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

// Register robots_txt filter if virtual fix is active
add_action( 'init', function() {
    if ( get_option( 'big_geo_robots_fix_active', '0' ) === '1' ) {
        add_filter( 'robots_txt', 'BIG_GEO_Robots_Audit::inject_ai_bots_filter', 99, 2 );
    }
} );
// Save settings
add_action( 'admin_init', 'big_geo_save_settings' );
function big_geo_save_settings() {
    if ( ! isset( $_POST['big_geo_settings_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['big_geo_settings_nonce'], 'big_geo_save_settings' ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Save general settings
    if ( isset( $_POST['big_geo_post_types'] ) ) {
        update_option( 'big_geo_post_types', array_map( 'sanitize_text_field', (array) $_POST['big_geo_post_types'] ) );
    } else {
        update_option( 'big_geo_post_types', array() );
    }

    if ( isset( $_POST['big_geo_site_description'] ) ) {
        update_option( 'big_geo_site_description', sanitize_textarea_field( $_POST['big_geo_site_description'] ) );
    }

    if ( isset( $_POST['big_geo_excluded_urls'] ) ) {
        update_option( 'big_geo_excluded_urls', sanitize_textarea_field( $_POST['big_geo_excluded_urls'] ) );
    }

    update_option( 'big_geo_llms_txt_enabled', isset( $_POST['big_geo_llms_txt_enabled'] ) ? '1' : '0' );
    update_option( 'big_geo_llms_full_enabled', isset( $_POST['big_geo_llms_full_enabled'] ) ? '1' : '0' );
    update_option( 'big_geo_strip_shortcodes', isset( $_POST['big_geo_strip_shortcodes'] ) ? '1' : '0' );

    if ( isset( $_POST['big_geo_custom_robots_url'] ) ) {
        update_option( 'big_geo_custom_robots_url', esc_url_raw( $_POST['big_geo_custom_robots_url'] ) );
    }

    delete_transient( 'big_geo_llms_txt_cache' );
    delete_transient( 'big_geo_llms_full_cache' );

    add_settings_error( 'big_geo_messages', 'big_geo_message', 'Settings saved successfully.', 'success' );
}

// Settings page UI
function big_geo_settings_page() {
    $audit = new BIG_GEO_Robots_Audit();
    $llms  = new BIG_GEO_LLMS_Txt();
    ?>
    <div class="wrap big-geo-settings">
        <h1><span class="dashicons dashicons-admin-site-alt3"></span> Big GEO Settings</h1>
        <?php settings_errors( 'big_geo_messages' ); ?>

        <nav class="nav-tab-wrapper">
            <a href="#tab-dashboard" class="nav-tab nav-tab-active">Dashboard</a>
            <a href="#tab-llms-txt" class="nav-tab">llms.txt</a>
            <a href="#tab-llms-full" class="nav-tab">llms-full.txt</a>
            <a href="#tab-robots" class="nav-tab">AI Crawlers Audit</a>
        </nav>

        <div class="tab-content">
            <!-- Dashboard Tab -->
            <div id="tab-dashboard" class="tab-pane active">
                <h2>Big GEO Overview</h2>
                <div class="big-geo-cards">
                    <div class="big-geo-card">
                        <h3>llms.txt</h3>
                        <p><strong>Status:</strong> <?php echo get_option( 'big_geo_llms_txt_enabled', '1' ) === '1' ? '✅ Active' : '❌ Inactive'; ?></p>
                        <p><a href="<?php echo home_url( '/llms.txt' ); ?>" target="_blank">View llms.txt →</a></p>
                    </div>
                    <div class="big-geo-card">
                        <h3>llms-full.txt</h3>
                        <p><strong>Status:</strong> <?php echo get_option( 'big_geo_llms_full_enabled', '0' ) === '1' ? '✅ Active' : '❌ Inactive'; ?></p>
                        <p><a href="<?php echo home_url( '/llms-full.txt' ); ?>" target="_blank">View llms-full.txt →</a></p>
                    </div>
                    <div class="big-geo-card">
                        <h3>AI Crawler Audit</h3>
                        <?php
                        $results = $audit->run_audit();
                        $count_blocked = 0;
                        foreach ( $results['bots'] as $bot ) {
                            if ( $bot['status'] === 'blocked' ) $count_blocked++;
                        }
                        ?>
                        <p><strong>Allowed:</strong> <?php echo count( $results['bots'] ) - $count_blocked; ?>/<?php echo count( $results['bots'] ); ?> bots</p>
                        <p>Tier: <code><?php echo esc_html( $results['tier'] ); ?></code></p>
                    </div>
                </div>
            </div>

            <!-- llms.txt Tab -->
            <div id="tab-llms-txt" class="tab-pane">
                <h2>llms.txt Generator</h2>
                <form method="post">
                    <?php wp_nonce_field( 'big_geo_save_settings', 'big_geo_settings_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Enable llms.txt</th>
                            <td>
                                <label><input type="checkbox" name="big_geo_llms_txt_enabled" <?php checked( get_option( 'big_geo_llms_txt_enabled', '1' ), '1' ); ?>> Enable</label>
                            </td>
                        </tr>
                        <tr>
                            <th>Post Types to Include</th>
                            <td>
                                <?php
                                $pt_types = get_post_types( array( 'public' => true ), 'objects' );
                                $selected = get_option( 'big_geo_post_types', array( 'post', 'page' ) );
                                foreach ( $pt_types as $pt ) {
                                    $checked = in_array( $pt->name, $selected, true );
                                    echo '<label style="display:block;"><input type="checkbox" name="big_geo_post_types[]" value="' . esc_attr( $pt->name ) . '" ' . checked( $checked, true, false ) . '> ' . esc_html( $pt->labels->name ) . '</label>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Site Description</th>
                            <td>
                                <textarea name="big_geo_site_description" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'big_geo_site_description', '' ) ); ?></textarea>
                                <p class="description">Appears at the top of llms.txt. Leave empty to use tagline.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Excluded URLs</th>
                            <td>
                                <textarea name="big_geo_excluded_urls" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'big_geo_excluded_urls', '' ) ); ?></textarea>
                                <p class="description">One URL per line. These posts will be excluded from llms.txt.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>

                <h3>Preview llms.txt</h3>
                <button type="button" id="big-geo-preview-llms" class="button">Load Preview</button>
                <textarea id="big-geo-llms-preview" class="large-text code" rows="15" readonly></textarea>
            </div>

            <!-- llms-full.txt Tab -->
            <div id="tab-llms-full" class="tab-pane">
                <h2>llms-full.txt Generator</h2>
                <form method="post">
                    <?php wp_nonce_field( 'big_geo_save_settings', 'big_geo_settings_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Enable llms-full.txt</th>
                            <td>
                                <label><input type="checkbox" name="big_geo_llms_full_enabled" <?php checked( get_option( 'big_geo_llms_full_enabled', '0' ), '1' ); ?>> Enable</label>
                                <p class="description">⚠️ This exposes full post content. Use with caution.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Strip Shortcodes</th>
                            <td>
                                <label><input type="checkbox" name="big_geo_strip_shortcodes" <?php checked( get_option( 'big_geo_strip_shortcodes', '1' ), '1' ); ?>> Strip shortcodes from output</label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>

            <!-- Robots Audit Tab -->
            <div id="tab-robots" class="tab-pane">
                <h2>AI Crawler Audit & Fix</h2>
                <button type="button" id="big-geo-run-audit" class="button button-primary">Run Audit Now</button>
                <div id="big-geo-audit-results" style="margin-top:20px;"></div>

                <h3>Custom robots.txt URL (Optional)</h3>
                <form method="post">
                    <?php wp_nonce_field( 'big_geo_save_settings', 'big_geo_settings_nonce' ); ?>
                    <p>
                        <input type="url" name="big_geo_custom_robots_url" value="<?php echo esc_attr( get_option( 'big_geo_custom_robots_url', '' ) ); ?>" class="regular-text" placeholder="https://example.com/custom-robots.txt">
                        <?php submit_button( 'Save', 'secondary', 'submit', false ); ?>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
}