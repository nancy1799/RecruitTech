<?php
/**
 * Plugin Name: RecruitTech
 * Description: A multi-company recruitment platform for WordPress
 * Version: 0.6
 * Author: RecruitTech Team
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'RECRUITTECH_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RECRUITTECH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RECRUITTECH_VERSION', '0.6' );

/**
 * Return the list of RecruitTech frontend shortcodes.
 *
 * @return array
 */
function recruittech_get_frontend_shortcodes() {
    return array(
        'recruittech_home',
        'recruittech_company_dashboard',
        'recruittech_company_create_job',
        'recruittech_job_seeker_dashboard',
        'recruittech_company_applications',
        'recruittech_company_jobs',
        'recruittech_my_applications',
        'recruittech_browse_jobs',
        'recruittech_job_details',
        'recruittech_notifications',
        'recruittech_company_profile',
        'recruittech_company_documents',
        'recruittech_job_seeker_profile',
        'recruittech_login',
        'recruittech_logout',
        'recruittech_registration',
        'recruittech_directory_search',
        'recruittech_profile_view',
        'recruittech_my_subscription',
    );
}

/**
 * Return true when the current page includes RecruitTech frontend content.
 *
 * @return bool
 */
function recruittech_is_frontend_recruittech_page() {
    if ( ! is_singular() ) {
        return false;
    }

    global $post;
    if ( empty( $post ) || ! is_a( $post, 'WP_Post' ) ) {
        return false;
    }

    $shortcodes = recruittech_get_frontend_shortcodes();
    foreach ( $shortcodes as $shortcode ) {
        if ( has_shortcode( $post->post_content, $shortcode ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Enqueue RecruitTech frontend assets on RecruitTech pages only.
 */
function recruittech_enqueue_frontend_assets() {
    if ( ! recruittech_is_frontend_recruittech_page() ) {
        return;
    }

    $version = filemtime( RECRUITTECH_PLUGIN_PATH . 'assets/css/recruittech.css' );

    // The plugin templates use Bootstrap 5 utility/grid classes (container, row,
    // col-*, card, btn, form-control, d-flex, etc.) plus Bootstrap Icons for
    // small icons in the dashboards. Load both from the CDN so that markup
    // renders correctly, then layer RecruitTech's own theme on top.
    wp_register_style(
        'bootstrap5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        array(),
        '5.3.3'
    );
    wp_register_style(
        'bootstrap-icons',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        array(),
        '1.11.3'
    );
    wp_register_script(
        'bootstrap5',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.3',
        true
    );

    wp_register_style(
        'recruittech-main',
        RECRUITTECH_PLUGIN_URL . 'assets/css/recruittech.css',
        array( 'bootstrap5', 'bootstrap-icons' ),
        $version
    );
    wp_register_style(
        'recruittech-dashboard',
        RECRUITTECH_PLUGIN_URL . 'assets/css/dashboard.css',
        array( 'recruittech-main' ),
        $version
    );
    wp_register_style(
        'recruittech-forms',
        RECRUITTECH_PLUGIN_URL . 'assets/css/forms.css',
        array( 'recruittech-main' ),
        $version
    );
    wp_register_style(
        'recruittech-tables',
        RECRUITTECH_PLUGIN_URL . 'assets/css/tables.css',
        array( 'recruittech-main' ),
        $version
    );
    wp_register_style(
        'recruittech-buttons',
        RECRUITTECH_PLUGIN_URL . 'assets/css/buttons.css',
        array( 'recruittech-main' ),
        $version
    );
    wp_register_style(
        'recruittech-responsive',
        RECRUITTECH_PLUGIN_URL . 'assets/css/responsive.css',
        array( 'recruittech-main' ),
        $version
    );
    wp_register_style(
        'recruittech-layout',
        RECRUITTECH_PLUGIN_URL . 'assets/css/layout.css',
        array( 'recruittech-main' ),
        filemtime( RECRUITTECH_PLUGIN_PATH . 'assets/css/layout.css' )
    );
    wp_register_style(
        'recruittech-ai-and-search',
        RECRUITTECH_PLUGIN_URL . 'assets/css/ai-and-search.css',
        array( 'recruittech-main' ),
        filemtime( RECRUITTECH_PLUGIN_PATH . 'assets/css/ai-and-search.css' )
    );

    wp_enqueue_style( 'bootstrap5' );
    wp_enqueue_style( 'bootstrap-icons' );
    wp_enqueue_style( 'recruittech-main' );
    wp_enqueue_style( 'recruittech-dashboard' );
    wp_enqueue_style( 'recruittech-forms' );
    wp_enqueue_style( 'recruittech-tables' );
    wp_enqueue_style( 'recruittech-buttons' );
    wp_enqueue_style( 'recruittech-responsive' );
    wp_enqueue_style( 'recruittech-layout' );
    wp_enqueue_style( 'recruittech-ai-and-search' );

    wp_register_script(
        'recruittech-main',
        RECRUITTECH_PLUGIN_URL . 'assets/js/recruittech.js',
        array( 'jquery', 'bootstrap5' ),
        filemtime( RECRUITTECH_PLUGIN_PATH . 'assets/js/recruittech.js' ),
        true
    );
    wp_localize_script(
        'recruittech-main',
        'recruittechAjax',
        array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'analyzeNonce' => wp_create_nonce( 'recruittech_ai_analyze' ),
        )
    );

    wp_enqueue_script( 'bootstrap5' );
    wp_enqueue_script( 'recruittech-main' );
}
add_action( 'wp_enqueue_scripts', 'recruittech_enqueue_frontend_assets' );

/**
 * Serve RecruitTech's own full-page template (templates/canvas.php) on
 * every page that contains a RecruitTech shortcode.
 *
 * This is what removes the theme's page title, header, and footer on
 * RecruitTech pages, and replaces them with RecruitTech's own navbar and
 * footer (see templates/partials/navbar.php and footer.php).
 *
 * @param string $template Template file WordPress was about to use.
 * @return string
 */
function recruittech_template_include( $template ) {
    if ( recruittech_is_frontend_recruittech_page() ) {
        return RECRUITTECH_PLUGIN_PATH . 'templates/canvas.php';
    }

    return $template;
}
add_filter( 'template_include', 'recruittech_template_include' );

// Require database setup file
require_once RECRUITTECH_PLUGIN_PATH . 'includes/db-setup.php';

// Require user roles file
require_once RECRUITTECH_PLUGIN_PATH . 'includes/roles.php';

// Require user registration file
require_once RECRUITTECH_PLUGIN_PATH . 'includes/registration.php';

// Require user login file
require_once RECRUITTECH_PLUGIN_PATH . 'includes/login.php';

// Require user logout file
require_once RECRUITTECH_PLUGIN_PATH . 'includes/logout.php';

// Require dashboard, company profile, admin verification, and security files
require_once RECRUITTECH_PLUGIN_PATH . 'includes/dashboards.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/home.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/company-profile.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/admin-company-verification.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/admin-job-seeker-verification.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/security.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/notifications.php';

require_once RECRUITTECH_PLUGIN_PATH . 'includes/job-seeker-profile.php';

// Require the People & Company Search + Profile View module
require_once RECRUITTECH_PLUGIN_PATH . 'includes/search.php';

// Require the AI Trinity modules (LLM client, text extraction/RAG, Agent workflow)
require_once RECRUITTECH_PLUGIN_PATH . 'includes/ai/class-ai-client.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/ai/class-text-extractor.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/ai/class-agent.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/ai-settings.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/ai-ajax.php';

// Require the company hiring-documents module (feeds the AI's lightweight RAG)
require_once RECRUITTECH_PLUGIN_PATH . 'includes/company-documents.php';

// Require the Subscriptions module (plans, payment gateways, enforcement,
// and the "My Subscription" page). Everything stays free/unlimited unless
// the admin turns it on at Settings > RecruitTech Subscriptions.
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions/class-subscription-manager.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions/class-subscription-cron.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions/class-payment-gateway.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions/class-paymob-gateway.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions-settings.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions-ajax.php';
require_once RECRUITTECH_PLUGIN_PATH . 'includes/subscriptions-page.php';

// Require automatic page creation for all RecruitTech shortcodes
require_once RECRUITTECH_PLUGIN_PATH . 'includes/page-setup.php';

// Register activation hook to create database tables and user roles
register_activation_hook( __FILE__, 'recruittech_create_tables' );
register_activation_hook( __FILE__, 'recruittech_create_roles' );

// Register activation hook to automatically create every RecruitTech page
// (login, registration, dashboards, job pages, etc.) so nothing needs to
// be created by hand.
register_activation_hook( __FILE__, 'recruittech_create_recruittech_pages' );

// Register activation hook to schedule the daily subscription-expiry cron
register_activation_hook( __FILE__, 'recruittech_subscription_schedule_cron' );

// Register uninstall hook to remove user roles
register_uninstall_hook( __FILE__, 'recruittech_remove_roles' );

// Register uninstall hook to clear the subscription-expiry cron event
register_uninstall_hook( __FILE__, 'recruittech_subscription_unschedule_cron' );
