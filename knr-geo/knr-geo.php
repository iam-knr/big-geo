<?php
/**
 * Plugin Name: Big GEO
 * Plugin URI: https://knr.digital/knr-geo
 * Description: Generative Engine Optimization toolkit for WordPress. Auto-generates llms.txt, llms-full.txt, and audits AI crawler access in robots.txt.
 * Version: 1.0.0
 * Author: KNR Digital
 * Author URI: https://knr.digital
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: knr-geo
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KNR_GEO_VERSION', '1.0.0' );
define( 'KNR_GEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KNR_GEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KNR_GEO_PLUGIN_FILE', __FILE__ );

require_once KNR_GEO_PLUGIN_DIR . 'includes/class-llms-txt.php';
require_once KNR_GEO_PLUGIN_DIR . 'includes/class-llms-full.php';
require_once KNR_GEO_PLUGIN_DIR . 'includes/class-robots-audit.php';
require_once KNR_GEO_PLUGIN_DIR . 'admin/settings-page.php';
require_once KNR_GEO_PLUGIN_DIR . 'admin/dashboard-widget.php';

register_activation_hook( __FILE__, 'knr_geo_activate' );
register_deactivation_hook( __FILE__, 'knr_geo_deactivate' );

function knr_geo_activate() {
    $defaults = array(
        'knr_geo_post_types'        => array( 'post', 'page' ),
        'knr_geo_site_description'  => '',
        'knr_geo_llms_txt_enabled'  => '1',
        'knr_geo_llms_full_enabled' => '0',
        'knr_geo_excluded_urls'     => '',
        'knr_geo_strip_shortcodes'  => '1',
        'knr_geo_robots_fix_active' => '0',
        'knr_geo_custom_robots_url' => '',
    );
    foreach ( $defaults as $key => $val ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $val );
        }
    }
    flush_rewrite_rules();
}

function knr_geo_deactivate() {
    flush_rewrite_rules();
}

// Boot modules
add_action( 'init', 'knr_geo_init' );
function knr_geo_init() {
    $llms = new KNR_GEO_LLMS_Txt();
    $llms->register_rewrite();
    $llms_full = new KNR_GEO_LLMS_Full();
    $llms_full->register_rewrite();
}

// Auto-regenerate on post save
add_action( 'save_post', 'knr_geo_on_save_post', 99 );
function knr_geo_on_save_post( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    delete_transient( 'knr_geo_llms_txt_cache' );
    delete_transient( 'knr_geo_llms_full_cache' );
}

// Admin assets
add_action( 'admin_enqueue_scripts', 'knr_geo_admin_assets' );
function knr_geo_admin_assets( $hook ) {
    if ( strpos( $hook, 'knr-geo' ) === false ) return;
    wp_enqueue_style( 'knr-geo-admin', KNR_GEO_PLUGIN_URL . 'assets/admin.css', array(), KNR_GEO_VERSION );
    wp_enqueue_script( 'knr-geo-admin', KNR_GEO_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), KNR_GEO_VERSION, true );
    wp_localize_script( 'knr-geo-admin', 'knrGeo', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'knr_geo_nonce' ),
    ) );
}

// AJAX: Regenerate llms.txt
add_action( 'wp_ajax_knr_geo_regenerate_llms', 'knr_geo_ajax_regenerate' );
function knr_geo_ajax_regenerate() {
    check_ajax_referer( 'knr_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    delete_transient( 'knr_geo_llms_txt_cache' );
    delete_transient( 'knr_geo_llms_full_cache' );
    $llms    = new KNR_GEO_LLMS_Txt();
    $content = $llms->generate();
    wp_send_json_success( array( 'content' => $content, 'message' => 'llms.txt regenerated successfully.' ) );
}

// AJAX: Run robots.txt audit
add_action( 'wp_ajax_knr_geo_run_audit', 'knr_geo_ajax_run_audit' );
function knr_geo_ajax_run_audit() {
    check_ajax_referer( 'knr_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $audit = new KNR_GEO_Robots_Audit();
    wp_send_json_success( $audit->run_audit() );
}

// AJAX: Apply virtual robots.txt fix
add_action( 'wp_ajax_knr_geo_fix_robots', 'knr_geo_ajax_fix_robots' );
function knr_geo_ajax_fix_robots() {
    check_ajax_referer( 'knr_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $audit = new KNR_GEO_Robots_Audit();
    wp_send_json_success( $audit->apply_virtual_fix() );
}

// AJAX: Write physical robots.txt
add_action( 'wp_ajax_knr_geo_write_robots', 'knr_geo_ajax_write_robots' );
function knr_geo_ajax_write_robots() {
    check_ajax_referer( 'knr_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $audit = new KNR_GEO_Robots_Audit();
    wp_send_json_success( $audit->write_physical_robots() );
}