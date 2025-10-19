<?php
/**
 * Plugin Name: Restaurant Booking Manager
 * Plugin URI: https://github.com/newit5s/wp_booking-table
 * Description: Plugin quản lý đặt bàn nhà hàng hoàn chỉnh với giao diện thân thiện
 * Version: 1.0.0
 * Author: NewIT5S
 * Author URI: https://github.com/newit5s
 * License: GPL v2 or later
 * Text Domain: restaurant-booking
 * Domain Path: /languages
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('RB_VERSION', '1.0.0');
define('RB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RB_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Activation Hook - Tạo database tables
 */
register_activation_hook(__FILE__, 'rb_activate_plugin');
function rb_activate_plugin() {
    // Load database class và tạo tables
    require_once RB_PLUGIN_DIR . 'includes/class-database.php';
    require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
    $database = new RB_Database();
    $database->create_tables();
    
    // Set default options
    add_option('rb_settings', array(
        'max_tables' => 20,
        'opening_time' => '09:00',
        'closing_time' => '22:00',
        'time_slot_interval' => 30,
        'admin_email' => get_option('admin_email'),
        'enable_email' => 'yes',
        'default_location' => 'vn'
    ));
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation Hook
 */
register_deactivation_hook(__FILE__, 'rb_deactivate_plugin');
function rb_deactivate_plugin() {
    flush_rewrite_rules();
}

/**
 * Load plugin textdomain
 */
add_action('plugins_loaded', 'rb_load_textdomain');
function rb_load_textdomain() {
    load_plugin_textdomain('restaurant-booking', false, dirname(RB_PLUGIN_BASENAME) . '/languages');
}

/**
 * Initialize Plugin
 */
add_action('plugins_loaded', 'rb_init_plugin');
function rb_init_plugin() {
    // Load required files
    require_once RB_PLUGIN_DIR . 'includes/class-database.php';
    require_once RB_PLUGIN_DIR . 'includes/class-booking.php';
    require_once RB_PLUGIN_DIR . 'includes/class-customer.php';
    require_once RB_PLUGIN_DIR . 'includes/class-ajax.php';
    require_once RB_PLUGIN_DIR . 'includes/class-email.php';
    require_once RB_PLUGIN_DIR . 'includes/class-update-features.php';
    
    // Initialize Database
    global $rb_database;
    $rb_database = new RB_Database();
    
    // Initialize Booking Handler
    global $rb_booking;
    $rb_booking = new RB_Booking();
    
    // Initialize AJAX handlers
    new RB_Ajax();
    
    global $rb_customer;
    $rb_customer = new RB_Customer();
    
    // Initialize Email handler
    global $rb_email;
    $rb_email = new RB_Email();
    
    // Load Admin area
    if (is_admin()) {
        require_once RB_PLUGIN_DIR . 'admin/class-admin.php';
        new RB_Admin();
    }
    
    // Load Frontend
    // Load Frontend for normal frontend requests
    if ( ! is_admin() ) {
        require_once RB_PLUGIN_DIR . 'public/class-frontend.php';
        new RB_Frontend();
    }

    // ALSO load Frontend during AJAX (admin-ajax.php sets is_admin() = true)
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        if ( ! class_exists('RB_Frontend') ) {
            require_once RB_PLUGIN_DIR . 'public/class-frontend.php';
        }
        new RB_Frontend(); // registers rb_submit_booking / rb_check_availability
    }

}

/**
 * Enqueue admin scripts and styles
 */
add_action('admin_enqueue_scripts', 'rb_admin_enqueue_scripts');
function rb_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'restaurant-booking') !== false || strpos($hook, 'rb-') !== false) {
        wp_enqueue_style('rb-admin-css', RB_PLUGIN_URL . 'assets/css/admin.css', array(), RB_VERSION);
        wp_enqueue_script('rb-admin-js', RB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RB_VERSION, true);

        // Localize script
        wp_localize_script('rb-admin-js', 'rb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rb_admin_nonce')
        ));

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_timeline_screen = $screen && $screen->id === 'restaurant-booking_page_rb-timeline';

        if ($is_timeline_screen) {
            if (!class_exists('RB_I18n')) {
                require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
            }

            global $rb_booking;
            if (!$rb_booking instanceof RB_Booking) {
                require_once RB_PLUGIN_DIR . 'includes/class-booking.php';
                $rb_booking = new RB_Booking();
            }

            $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
            $language = RB_I18n::get_language_from_locale($locale);

            $settings = get_option('rb_settings', array());
            $default_location = isset($settings['default_location']) ? RB_I18n::sanitize_location($settings['default_location']) : 'vn';
            $today = current_time('mysql');
            $today_date = $today ? substr($today, 0, 10) : date('Y-m-d');

            $initial_payload = $rb_booking->get_timeline_payload($today_date, $default_location);
            if (is_wp_error($initial_payload)) {
                $initial_payload = null;
            }

            $locations = array();
            foreach (RB_I18n::get_locations() as $code => $location) {
                $locations[] = array(
                    'code' => $code,
                    'flag' => isset($location['flag']) ? $location['flag'] : '',
                    'label' => RB_I18n::get_location_label($code, $language),
                );
            }

            $status_colors = array(
                'pending' => '#f0ad4e',
                'confirmed' => '#5bc0de',
                'checked_in' => '#5cb85c',
                'completed' => '#428bca',
                'cancelled' => '#d9534f',
                'no-show' => '#795548',
            );

            $i18n = array(
                'loading' => __('Loading timeline…', 'restaurant-booking'),
                'noData' => __('No bookings found for this day.', 'restaurant-booking'),
                'error' => __('Unable to load timeline data. Please try again.', 'restaurant-booking'),
                'autoRefreshOn' => __('Auto refresh enabled', 'restaurant-booking'),
                'autoRefreshOff' => __('Auto refresh paused', 'restaurant-booking'),
                'checkInConfirm' => __('Mark this booking as checked-in?', 'restaurant-booking'),
                'checkOutConfirm' => __('Mark this booking as completed?', 'restaurant-booking'),
                'dragTooltip' => __('Drag to reschedule booking', 'restaurant-booking'),
                'tableUnavailable' => __('Table is currently disabled', 'restaurant-booking'),
                'statusAvailable' => __('Available', 'restaurant-booking'),
                'statusUnavailable' => __('Unavailable', 'restaurant-booking'),
                'modalTitle' => __('Reservation details', 'restaurant-booking'),
                'close' => __('Close', 'restaurant-booking'),
                'refresh' => __('Refreshing timeline…', 'restaurant-booking'),
                'today' => __('Today', 'restaurant-booking'),
                'previousDay' => __('Previous day', 'restaurant-booking'),
                'nextDay' => __('Next day', 'restaurant-booking'),
                'tableHeader' => __('Table', 'restaurant-booking'),
                'capacityLabel' => __('Capacity', 'restaurant-booking'),
                'guestLabel' => __('Guests', 'restaurant-booking'),
                'timeLabel' => __('Time', 'restaurant-booking'),
                'statusLabel' => __('Status', 'restaurant-booking'),
                'sourceLabel' => __('Source', 'restaurant-booking'),
                'notesLabel' => __('Notes', 'restaurant-booking'),
                'specialRequestLabel' => __('Special requests', 'restaurant-booking'),
                'checkIn' => __('Check-in', 'restaurant-booking'),
                'checkOut' => __('Check-out', 'restaurant-booking'),
                'toggleAvailability' => __('Toggle availability', 'restaurant-booking'),
                'dragDisabled' => __('Cannot move booking to an unavailable table.', 'restaurant-booking'),
                'updated' => __('Timeline updated', 'restaurant-booking'),
                'unassigned' => __('Unassigned', 'restaurant-booking'),
            );

            wp_enqueue_style('rb-admin-timeline', RB_PLUGIN_URL . 'assets/css/timeline.css', array('rb-admin-css'), RB_VERSION);
            wp_enqueue_script('rb-admin-timeline', RB_PLUGIN_URL . 'assets/js/admin-timeline.js', array('jquery'), RB_VERSION, true);

            wp_localize_script('rb-admin-timeline', 'rbTimelineData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rb_admin_nonce'),
                'locations' => $locations,
                'defaultLocation' => $default_location,
                'today' => $today_date,
                'initialData' => $initial_payload,
                'statusColors' => $status_colors,
                'i18n' => $i18n,
                'autoRefreshInterval' => apply_filters('rb_timeline_auto_refresh_interval', 60000),
            ));
        }
    }
}

/**
 * Enqueue frontend scripts and styles
 */
add_action('wp_enqueue_scripts', 'rb_frontend_enqueue_scripts');
function rb_frontend_enqueue_scripts() {
    wp_enqueue_style('rb-frontend-css', RB_PLUGIN_URL . 'assets/css/frontend.css', array(), RB_VERSION);
    wp_enqueue_script('rb-frontend-js', RB_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), RB_VERSION, true);

    if (!class_exists('RB_I18n')) {
        require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
    }

    $site_locale = function_exists('get_locale') ? get_locale() : 'vi';
    $default_language = RB_I18n::get_language_from_locale($site_locale);
    $fallback_language = RB_I18n::sanitize_language('vi');

    // Localize script for AJAX
    wp_localize_script('rb-frontend-js', 'rb_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rb_frontend_nonce'),
        'loading_text' => RB_I18n::translate('frontend', 'loading_text', $default_language),
        'error_text' => RB_I18n::translate('frontend', 'error_text', $default_language),
        'default_language' => $default_language,
        'fallback_language' => $fallback_language,
        'languages' => RB_I18n::get_languages(),
        'translations' => RB_I18n::get_frontend_translations(),
    ));
}

/**
 * Register shortcode
 */
add_shortcode('restaurant_booking', 'rb_booking_shortcode');
function rb_booking_shortcode($atts) {
    // Load frontend class if not loaded
    if (!class_exists('RB_Frontend')) {
        require_once RB_PLUGIN_DIR . 'public/class-frontend.php';
    }
    
    $frontend = new RB_Frontend();
    return $frontend->render_booking_form($atts);
}

/**
 * Add plugin action links
 */
add_filter('plugin_action_links_' . RB_PLUGIN_BASENAME, 'rb_plugin_action_links');
function rb_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=rb-settings') . '">' . __('Cài đặt', 'restaurant-booking') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Check plugin dependencies
 */
add_action('admin_notices', 'rb_check_dependencies');
function rb_check_dependencies() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.0', '<')) {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Restaurant Booking Manager yêu cầu PHP version 7.0 trở lên.', 'restaurant-booking'); ?></p>
        </div>
        <?php
    }
    
    // Check if tables exist
    global $wpdb;
    $table_name = $wpdb->prefix . 'rb_bookings';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Restaurant Booking Manager: Database tables chưa được tạo. Vui lòng deactivate và activate lại plugin.', 'restaurant-booking'); ?></p>
        </div>
        <?php
    }
}