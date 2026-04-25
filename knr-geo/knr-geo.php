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

define( 'BIG_GEO_VERSION', '1.0.0' );
define( 'BIG_GEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BIG_GEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BIG_GEO_PLUGIN_FILE', __FILE__ );

require_once BIG_GEO_PLUGIN_DIR . 'includes/class-llms-txt.php';
require_once BIG_GEO_PLUGIN_DIR . 'includes/class-llms-full.php';
require_once BIG_GEO_PLUGIN_DIR . 'includes/class-robots-audit.php';
require_once BIG_GEO_PLUGIN_DIR . 'admin/settings-page.php';
// require_once BIG_GEO_PLUGIN_DIR . 'admin/dashboard-widget.php';

register_activation_hook( __FILE__, 'big_geo_activate' );
register_deactivation_hook( __FILE__, 'big_geo_deactivate' );

function big_geo_activate() {
    $defaults = array(
        'big_geo_post_types'        => array( 'post', 'page' ),
        'big_geo_site_description'  => '',
        'big_geo_llms_txt_enabled'  => '1',
        'big_geo_llms_full_enabled' => '0',
        'big_geo_excluded_urls'     => '',
        'big_geo_strip_shortcodes'  => '1',
        'big_geo_robots_fix_active' => '0',
        'big_geo_custom_robots_url' => '',
    );
    foreach ( $defaults as $key => $val ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $val );
        }
    }
    flush_rewrite_rules();
}

function big_geo_deactivate() {
    flush_rewrite_rules();
}

// Boot modules
// AJAX Handlers for File Generation
add_action( 'wp_ajax_big_geo_generate_llms_txt', 'big_geo_generate_llms_txt_ajax' );
function big_geo_generate_llms_txt_ajax() {
	check_ajax_referer( 'big_geo_generate', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	
	$llms = new BIG_GEO_LLMS_Txt();
	$result = $llms->write_file();
	
	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

add_action( 'wp_ajax_big_geo_generate_llms_full', 'big_geo_generate_llms_full_ajax' );
function big_geo_generate_llms_full_ajax() {
	check_ajax_referer( 'big_geo_generate', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	
	$llms_full = new BIG_GEO_LLMS_Full();
	$result = $llms_full->write_file();
	
	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

add_action( 'wp_ajax_big_geo_preview_llms_txt', 'big_geo_preview_llms_txt_ajax' );
function big_geo_preview_llms_txt_ajax() {
	check_ajax_referer( 'big_geo_generate', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	
	$llms = new BIG_GEO_LLMS_Txt();
	$content = $llms->generate_content();
	
	wp_send_json_success( array( 'content' => $content ) );
}

add_action( 'wp_ajax_big_geo_preview_llms_full', 'big_geo_preview_llms_full_ajax' );
function big_geo_preview_llms_full_ajax() {
	check_ajax_referer( 'big_geo_generate', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	
	$llms_full = new BIG_GEO_LLMS_Full();
	$content = $llms_full->generate_content();
	
	wp_send_json_success( array( 'content' => $content ) );


// Admin assets
add_action( 'admin_enqueue_scripts', 'big_geo_admin_assets' );
function big_geo_admin_assets( $hook ) {
    if ( strpos( $hook, 'knr-geo' ) === false ) return;
    wp_enqueue_style( 'big-geo-admin', BIG_GEO_PLUGIN_URL . 'assets/admin.css', array(), BIG_GEO_VERSION );
    wp_enqueue_script( 'big-geo-admin', BIG_GEO_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), BIG_GEO_VERSION, true );
    wp_localize_script( 'big-geo-admin', 'bigGeo', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'big_geo_nonce' ),
    ) );
}

// AJAX: Run robots.txt audit
add_action( 'wp_ajax_big_geo_run_audit', 'big_geo_ajax_run_audit' );
function big_geo_ajax_run_audit() {
    check_ajax_referer( 'big_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $audit = new BIG_GEO_Robots_Audit();
    wp_send_json_success( $audit->run_audit() );
}

// AJAX: Apply virtual robots.txt fix
add_action( 'wp_ajax_big_geo_fix_robots', 'big_geo_ajax_fix_robots' );
function big_geo_ajax_fix_robots() {
    check_ajax_referer( 'big_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $audit = new BIG_GEO_Robots_Audit();
    wp_send_json_success( $audit->apply_virtual_fix() );
}

// AJAX: Write physical robots.txt
add_action( 'wp_ajax_big_geo_write_robots', 'big_geo_ajax_write_robots' );
function big_geo_ajax_write_robots() {
    check_ajax_referer( 'big_geo_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    $audit = new BIG_GEO_Robots_Audit();
    wp_send_json_success( $audit->write_physical_robots() );
}