<?php
/**
 * Plugin Name: FIBB Communication Suite
 * Description: Gestion des réseaux sociaux et planification des publications pour le Festival International de Bridge de Bordeaux. Facebook, Instagram, LinkedIn + calendrier + Assistant Plan (wizard SEO).
 * Version: 2.1
 * Author: FIBB
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FIBB_COMM_VERSION',    '2.1' );
define( 'FIBB_COMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FIBB_COMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FIBB_COMM_OPTION',     'fibb_comm_settings' );

/* ═══════════════════════════════════════
   INCLUDES
═══════════════════════════════════════ */

require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-db.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-meta-api.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-linkedin-api.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-scheduler.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-newsletter-bridge.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-templates.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-seo.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-wizard.php';
require_once FIBB_COMM_PLUGIN_DIR . 'includes/class-fibb-comm-auto-instagram.php';
require_once FIBB_COMM_PLUGIN_DIR . 'admin/class-fibb-comm-admin.php';

/* ═══════════════════════════════════════
   ACTIVATION / DÉSACTIVATION
═══════════════════════════════════════ */

register_activation_hook( __FILE__, function () {
    FIBB_Comm_DB::install();
    FIBB_Comm_Scheduler::register_cron();
} );

register_deactivation_hook( __FILE__, function () {
    FIBB_Comm_Scheduler::unregister_cron();
} );

/* ═══════════════════════════════════════
   CRON — TOUTES LES 15 MINUTES
═══════════════════════════════════════ */

add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['every_15_minutes'] = [
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => 'Toutes les 15 minutes',
    ];
    return $schedules;
} );

add_action( FIBB_Comm_Scheduler::CRON_HOOK,    [ 'FIBB_Comm_Scheduler', 'dispatch' ] );
add_action( FIBB_Comm_Scheduler::IG_CRON_HOOK, [ 'FIBB_Comm_Scheduler', 'dispatch_instagram_queue' ] );

/* ═══════════════════════════════════════
   AUTO INSTAGRAM
═══════════════════════════════════════ */

$fibb_auto_instagram = new FIBB_Comm_Auto_Instagram();

add_action( 'add_attachment', function ( $attachment_id ) use ( $fibb_auto_instagram ) {
    $fibb_auto_instagram->on_media_upload( (int) $attachment_id );
} );

add_action( 'publish_post', function ( $post_id ) use ( $fibb_auto_instagram ) {
    $fibb_auto_instagram->on_post_published( (int) $post_id );
} );

/* ═══════════════════════════════════════
   ADMIN
═══════════════════════════════════════ */

if ( is_admin() ) {
    $fibb_admin = new FIBB_Comm_Admin();

    add_action( 'admin_menu',              [ $fibb_admin, 'register_menu' ] );
    add_action( 'admin_init',              [ $fibb_admin, 'handle_actions' ] );
    add_action( 'admin_enqueue_scripts',   [ $fibb_admin, 'enqueue_assets' ] );

    add_action( 'wp_ajax_fibb_comm_test_connection',    [ $fibb_admin, 'ajax_test_connection' ] );
    add_action( 'wp_ajax_fibb_comm_get_template',       [ $fibb_admin, 'ajax_get_template' ] );
    add_action( 'wp_ajax_fibb_comm_move_post',          [ $fibb_admin, 'ajax_move_post' ] );
    add_action( 'wp_ajax_fibb_comm_delete_post_ajax',   [ $fibb_admin, 'ajax_delete_post_ajax' ] );
    add_action( 'wp_ajax_fibb_comm_newsletter_preview', [ $fibb_admin, 'ajax_newsletter_preview' ] );
    add_action( 'wp_ajax_fibb_comm_newsletter_lists',   [ $fibb_admin, 'ajax_newsletter_get_lists' ] );

    add_action( 'wp_ajax_fibb_wizard_save_step',        [ $fibb_admin, 'ajax_wizard_save_step' ] );
    add_action( 'wp_ajax_fibb_wizard_get_state',        [ $fibb_admin, 'ajax_wizard_get_state' ] );
    add_action( 'wp_ajax_fibb_wizard_preview_template', [ $fibb_admin, 'ajax_wizard_preview_template' ] );
    add_action( 'wp_ajax_fibb_wizard_activate',         [ $fibb_admin, 'ajax_wizard_activate' ] );
    add_action( 'wp_ajax_fibb_wizard_seo_check',        [ $fibb_admin, 'ajax_wizard_seo_check' ] );

    add_action( 'wp_ajax_fibb_ig_queue_add',            [ $fibb_admin, 'ajax_ig_queue_add' ] );
    add_action( 'wp_ajax_fibb_ig_queue_remove',         [ $fibb_admin, 'ajax_ig_queue_remove' ] );
    add_action( 'wp_ajax_fibb_ig_preview_caption',      [ $fibb_admin, 'ajax_ig_preview_caption' ] );
    add_action( 'wp_ajax_fibb_ig_bulk_schedule',        [ $fibb_admin, 'ajax_ig_bulk_schedule' ] );
    add_action( 'wp_ajax_fibb_ig_publish_now',          [ $fibb_admin, 'ajax_ig_publish_now' ] );
    add_action( 'wp_ajax_fibb_ig_get_post',             [ $fibb_admin, 'ajax_ig_get_post' ] );

    add_action( 'admin_post_fibb_comm_newsletter_save', [ $fibb_admin, 'handle_newsletter_save' ] );
    add_action( 'admin_post_fibb_comm_newsletter_send', [ $fibb_admin, 'handle_newsletter_send' ] );
}
