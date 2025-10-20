<?php
/**
 * Admin Class - Quản lý backend
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Admin {

    private $admin_language;

    public function __construct() {
        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $this->admin_language = $this->determine_admin_language();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    private function determine_admin_language() {
        $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
        return RB_I18n::get_language_from_locale($locale);
    }

    private function get_admin_language() {
        if (empty($this->admin_language)) {
            $this->admin_language = $this->determine_admin_language();
        }

        return $this->admin_language;
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Đặt bàn', 'restaurant-booking'),
            __('Đặt bàn', 'restaurant-booking'),
            'manage_options',
            'restaurant-booking',
            array($this, 'display_dashboard_page'),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'restaurant-booking',
            __('Dashboard', 'restaurant-booking'),
            __('Dashboard', 'restaurant-booking'),
            'manage_options',
            'restaurant-booking',
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            'restaurant-booking',
            __('Lịch phục vụ', 'restaurant-booking'),
            __('Lịch phục vụ', 'restaurant-booking'),
            'manage_options',
            'rb-timeline',
            array($this, 'display_timeline_page')
        );

        add_submenu_page(
            'restaurant-booking',
            __('Danh sách đặt bàn', 'restaurant-booking'),
            __('Danh sách đặt bàn', 'restaurant-booking'),
            'manage_options',
            'rb-bookings-list',
            array($this, 'display_booking_list_page')
        );

        add_submenu_page(
            'restaurant-booking',
            __('Tạo đặt bàn', 'restaurant-booking'),
            __('Tạo đặt bàn', 'restaurant-booking'),
            'manage_options',
            'rb-create-booking',
            array($this, 'display_create_booking_page')
        );

        add_submenu_page(
            'restaurant-booking',
            __('Quản lý bàn', 'restaurant-booking'),
            __('Quản lý bàn', 'restaurant-booking'),
            'manage_options',
            'rb-tables',
            array($this, 'display_tables_page')
        );

        add_submenu_page(
            'restaurant-booking',
            __('Quản lý khách hàng', 'restaurant-booking'),
            __('Khách hàng', 'restaurant-booking'),
            'manage_options',
            'rb-customers',
            array($this, 'display_customers_page')
        );

        add_submenu_page(
            'restaurant-booking',
            __('Báo cáo', 'restaurant-booking'),
            __('Báo cáo', 'restaurant-booking'),
            'manage_options',
            'rb-reports',
            array($this, 'display_reports_page')
        );

        add_menu_page(
            __('Cấu hình Đặt bàn', 'restaurant-booking'),
            __('Cấu hình Đặt bàn', 'restaurant-booking'),
            'manage_options',
            'rb-booking-settings',
            array($this, 'display_settings_page'),
            'dashicons-admin-generic',
            31
        );

        add_submenu_page(
            'rb-booking-settings',
            __('Cài đặt', 'restaurant-booking'),
            __('Cài đặt', 'restaurant-booking'),
            'manage_options',
            'rb-booking-settings',
            array($this, 'display_settings_page')
        );

        add_submenu_page(
            'rb-booking-settings',
            __('Giờ hoạt động', 'restaurant-booking'),
            __('Giờ hoạt động', 'restaurant-booking'),
            'manage_options',
            'rb-settings-working-hours',
            array($this, 'display_settings_working_hours_page')
        );

        add_submenu_page(
            'rb-booking-settings',
            __('Thông báo', 'restaurant-booking'),
            __('Thông báo', 'restaurant-booking'),
            'manage_options',
            'rb-settings-notifications',
            array($this, 'display_settings_notifications_page')
        );

        add_submenu_page(
            'rb-booking-settings',
            __('Chính sách', 'restaurant-booking'),
            __('Chính sách', 'restaurant-booking'),
            'manage_options',
            'rb-settings-policies',
            array($this, 'display_settings_policies_page')
        );

        add_submenu_page(
            'rb-booking-settings',
            __('Cập nhật tính năng', 'restaurant-booking'),
            __('Cập nhật tính năng', 'restaurant-booking'),
            'manage_options',
            'rb-feature-updates',
            array($this, 'display_feature_updates_page')
        );
    }
    
    public function display_create_booking_page() {
        global $wpdb;
        $settings = get_option('rb_settings', array());

        $admin_language = $this->get_admin_language();

        $backend_texts = RB_I18n::get_section_translations('backend', $admin_language);
        $frontend_texts = RB_I18n::get_section_translations('frontend', $admin_language);
        $languages = RB_I18n::get_languages();
        $locations = RB_I18n::get_locations();
        $default_location = isset($settings['default_location']) ? RB_I18n::sanitize_location($settings['default_location']) : 'vn';

        $opening_time = isset($settings['opening_time']) ? $settings['opening_time'] : '09:00';
        $closing_time = isset($settings['closing_time']) ? $settings['closing_time'] : '22:00';
        $time_interval = isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30;

        $time_slots = $this->generate_time_slots($opening_time, $closing_time, $time_interval);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($backend_texts['create_booking_title']); ?></h1>
            
            <div class="card" style="max-width: 800px;">
                <form method="post" action="" id="rb-admin-create-booking-form">
                    <?php wp_nonce_field('rb_create_admin_booking', 'rb_nonce'); ?>
                    <input type="hidden" name="action" value="create_admin_booking">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="booking_location"><?php echo esc_html($backend_texts['booking_location_label']); ?></label>
                            </th>
                            <td>
                                <select name="booking_location" id="booking_location" required>
                                    <?php foreach ($locations as $code => $location) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($default_location, $code); ?>>
                                            <?php echo esc_html(RB_I18n::get_location_label($code, $admin_language)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php echo esc_html($backend_texts['booking_language_label']); ?>
                            </th>
                            <td>
                                <input type="hidden" name="booking_language" value="<?php echo esc_attr($admin_language); ?>">
                                <?php
                                $language_label = isset($languages[$admin_language])
                                    ? $languages[$admin_language]['flag'] . ' ' . $languages[$admin_language]['native']
                                    : strtoupper($admin_language);
                                $language_note = sprintf($backend_texts['booking_language_auto_note'], esc_html($language_label));
                                echo wp_kses_post('<p class="description">' . $language_note . '</p>');
                                ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="customer_name"><?php echo esc_html($backend_texts['customer_name_label']); ?></label>
                            </th>
                            <td>
                                <input type="text" name="customer_name" id="customer_name" 
                                       class="regular-text" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="customer_phone"><?php echo esc_html($backend_texts['customer_phone_label']); ?></label>
                            </th>
                            <td>
                                <input type="tel" name="customer_phone" id="customer_phone"
                                       class="regular-text" required pattern="[0-9]{6,15}">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="customer_email"><?php echo esc_html($backend_texts['customer_email_label']); ?></label>
                            </th>
                            <td>
                                <input type="email" name="customer_email" id="customer_email" 
                                       class="regular-text" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="guest_count"><?php echo esc_html($backend_texts['guest_count_label']); ?></label>
                            </th>
                            <td>
                                <select name="guest_count" id="guest_count" required>
                                    <?php for ($i = 1; $i <= 20; $i++) : ?>
                                        <option value="<?php echo $i; ?>"><?php echo esc_html(sprintf($frontend_texts['guest_option'], $i)); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="booking_date"><?php echo esc_html($backend_texts['booking_date_label']); ?></label>
                            </th>
                            <td>
                                <input type="date" name="booking_date" id="booking_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="booking_time"><?php echo esc_html($backend_texts['booking_time_label']); ?></label>
                            </th>
                            <td>
                                <select name="booking_time" id="booking_time" required>
                                    <option value=""><?php echo esc_html($frontend_texts['select_time_placeholder']); ?></option>
                                    <?php foreach ($time_slots as $slot) : ?>
                                        <option value="<?php echo esc_attr($slot); ?>">
                                            <?php echo esc_html($slot); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="booking_source"><?php echo esc_html($backend_texts['booking_source_label']); ?></label>
                            </th>
                            <td>
                                <select name="booking_source" id="booking_source" required>
                                    <option value="phone"><?php echo esc_html($backend_texts['source_phone']); ?></option>
                                    <option value="facebook"><?php echo esc_html($backend_texts['source_facebook']); ?></option>
                                    <option value="zalo"><?php echo esc_html($backend_texts['source_zalo']); ?></option>
                                    <option value="instagram"><?php echo esc_html($backend_texts['source_instagram']); ?></option>
                                    <option value="walk-in"><?php echo esc_html($backend_texts['source_walk_in']); ?></option>
                                    <option value="email"><?php echo esc_html($backend_texts['source_email']); ?></option>
                                    <option value="other"><?php echo esc_html($backend_texts['source_other']); ?></option>
                                </select>
                                <p class="description"><?php echo esc_html($backend_texts['booking_source_description']); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="special_requests"><?php echo esc_html($backend_texts['special_requests_label']); ?></label>
                            </th>
                            <td>
                                <textarea name="special_requests" id="special_requests" 
                                          rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="admin_notes"><?php echo esc_html($backend_texts['admin_notes_label']); ?></label>
                            </th>
                            <td>
                                <textarea name="admin_notes" id="admin_notes" 
                                          rows="3" class="large-text"></textarea>
                                <p class="description"><?php echo esc_html($backend_texts['admin_notes_description']); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_confirm"><?php echo esc_html($backend_texts['auto_confirm_label']); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_confirm" id="auto_confirm" value="1" checked>
                                    <?php echo esc_html($backend_texts['auto_confirm_description']); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php echo esc_html($backend_texts['submit_button']); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rb-bookings-list')); ?>" class="button"><?php echo esc_html($backend_texts['cancel_button']); ?></a>
                    </p>
                </form>
            </div>
            
            <div id="rb-availability-info" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 3px; display: none;">
                <h3><?php echo esc_html($backend_texts['availability_title']); ?></h3>
                <div id="rb-available-tables-list"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var $availabilityInfo = $('#rb-availability-info');
            var $availabilityList = $('#rb-available-tables-list');
            var availabilityNonce = '<?php echo wp_create_nonce("rb_frontend_nonce"); ?>';
            var language = '<?php echo esc_js($admin_language); ?>';

            function resetAvailability() {
                $availabilityList.empty();
                $availabilityInfo.hide();
            }

            function updateAvailabilityPreview() {
                var date = $('#booking_date').val();
                var time = $('#booking_time').val();
                var guests = $('#guest_count').val();
                var location = $('#booking_location').val();

                if (!date || !time || !guests || !location) {
                    resetAvailability();
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rb_check_availability',
                        date: date,
                        time: time,
                        guests: guests,
                        location: location,
                        language: language,
                        nonce: availabilityNonce
                    },
                    success: function(response) {
                        if (response && response.success && response.data) {
                            var html = '<p><strong><?php echo esc_js($backend_texts['availability_status']); ?>:</strong> ';
                            if (response.data.available) {
                                html += '<span style="color: green;"><?php echo esc_js($backend_texts['availability_free']); ?></span></p>';
                                html += '<p>' + response.data.message + '</p>';
                            } else {
                                html += '<span style="color: red;"><?php echo esc_js($backend_texts['availability_full']); ?></span></p>';
                                html += '<p>' + response.data.message + '</p>';
                            }
                            $availabilityList.html(html);
                            $availabilityInfo.show();
                        } else {
                            resetAvailability();
                        }
                    },
                    error: resetAvailability
                });
            }

            $('#booking_date, #booking_time, #guest_count, #booking_location').on('change', updateAvailabilityPreview);
        });
        </script>
        <?php
    }
    

    public function display_dashboard_page() {
        global $wpdb;

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $table_name = $wpdb->prefix . 'rb_bookings';
        $admin_language = $this->get_admin_language();
        $today = date('Y-m-d', current_time('timestamp'));

        $stats = array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'"),
            'confirmed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'confirmed'"),
            'completed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'"),
            'cancelled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'cancelled'"),
            'today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE booking_date = %s", $today))
        );

        $today_summary_raw = $wpdb->get_results(
            $wpdb->prepare("SELECT status, COUNT(*) as count FROM $table_name WHERE booking_date = %s GROUP BY status", $today),
            OBJECT_K
        );

        $today_summary = array(
            'pending' => isset($today_summary_raw['pending']) ? (int) $today_summary_raw['pending']->count : 0,
            'confirmed' => isset($today_summary_raw['confirmed']) ? (int) $today_summary_raw['confirmed']->count : 0,
            'completed' => isset($today_summary_raw['completed']) ? (int) $today_summary_raw['completed']->count : 0,
            'cancelled' => isset($today_summary_raw['cancelled']) ? (int) $today_summary_raw['cancelled']->count : 0,
        );

        $upcoming_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE booking_date >= %s ORDER BY booking_date ASC, booking_time ASC LIMIT 5",
                $today
            )
        );

        $recent_activity = $wpdb->get_results(
            "SELECT id, customer_name, status, booking_date, booking_time, created_at FROM $table_name ORDER BY created_at DESC LIMIT 6"
        );

        $source_stats = $wpdb->get_results(
            "SELECT booking_source, COUNT(*) as count FROM $table_name GROUP BY booking_source ORDER BY count DESC"
        );

        $location_stats = $wpdb->get_results(
            "SELECT location, COUNT(*) as count FROM $table_name GROUP BY location ORDER BY count DESC"
        );

        ?>
        <div class="wrap rb-dashboard-wrap">
            <h1 class="rb-dashboard-title"><?php echo esc_html__('Tổng quan vận hành đặt bàn', 'restaurant-booking'); ?></h1>

            <div class="rb-dashboard-actions">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rb-create-booking')); ?>">+ <?php echo esc_html__('Tạo đặt bàn', 'restaurant-booking'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rb-timeline')); ?>"><?php echo esc_html__('Xem lịch phục vụ', 'restaurant-booking'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rb-bookings-list')); ?>"><?php echo esc_html__('Quản lý danh sách', 'restaurant-booking'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rb-tables')); ?>"><?php echo esc_html__('Quản lý bàn', 'restaurant-booking'); ?></a>
            </div>

            <div class="rb-stats-grid">
                <div class="rb-stat-box">
                    <span class="rb-stat-label"><?php echo esc_html__('Tổng đặt bàn', 'restaurant-booking'); ?></span>
                    <span class="rb-stat-number"><?php echo esc_html($stats['total']); ?></span>
                </div>
                <div class="rb-stat-box">
                    <span class="rb-stat-label"><?php echo esc_html__('Đặt bàn hôm nay', 'restaurant-booking'); ?></span>
                    <span class="rb-stat-number rb-stat-today"><?php echo esc_html($stats['today']); ?></span>
                </div>
                <div class="rb-stat-box">
                    <span class="rb-stat-label"><?php echo esc_html__('Đang chờ xác nhận', 'restaurant-booking'); ?></span>
                    <span class="rb-stat-number rb-stat-pending"><?php echo esc_html($stats['pending']); ?></span>
                </div>
                <div class="rb-stat-box">
                    <span class="rb-stat-label"><?php echo esc_html__('Đã xác nhận', 'restaurant-booking'); ?></span>
                    <span class="rb-stat-number rb-stat-confirmed"><?php echo esc_html($stats['confirmed']); ?></span>
                </div>
                <div class="rb-stat-box">
                    <span class="rb-stat-label"><?php echo esc_html__('Hoàn thành', 'restaurant-booking'); ?></span>
                    <span class="rb-stat-number rb-stat-completed"><?php echo esc_html($stats['completed']); ?></span>
                </div>
                <div class="rb-stat-box">
                    <span class="rb-stat-label"><?php echo esc_html__('Đã hủy', 'restaurant-booking'); ?></span>
                    <span class="rb-stat-number rb-stat-cancelled"><?php echo esc_html($stats['cancelled']); ?></span>
                </div>
            </div>

            <div class="rb-dashboard-columns">
                <div class="rb-dashboard-column rb-dashboard-column--wide">
                    <div class="card">
                        <h2><?php echo esc_html__('Tình hình hôm nay', 'restaurant-booking'); ?></h2>
                        <ul class="rb-status-list">
                            <li><span><?php echo esc_html__('Chờ xác nhận', 'restaurant-booking'); ?></span><strong><?php echo esc_html($today_summary['pending']); ?></strong></li>
                            <li><span><?php echo esc_html__('Đã xác nhận', 'restaurant-booking'); ?></span><strong><?php echo esc_html($today_summary['confirmed']); ?></strong></li>
                            <li><span><?php echo esc_html__('Hoàn thành', 'restaurant-booking'); ?></span><strong><?php echo esc_html($today_summary['completed']); ?></strong></li>
                            <li><span><?php echo esc_html__('Đã hủy', 'restaurant-booking'); ?></span><strong><?php echo esc_html($today_summary['cancelled']); ?></strong></li>
                        </ul>
                    </div>

                    <div class="card">
                        <h2><?php echo esc_html__('Nguồn khách hàng hàng đầu', 'restaurant-booking'); ?></h2>
                        <?php if ($source_stats) : ?>
                            <ul class="rb-mini-list">
                                <?php foreach ($source_stats as $source) : ?>
                                    <li>
                                        <span><?php echo esc_html($this->get_source_label($source->booking_source)); ?></span>
                                        <strong><?php echo esc_html($source->count); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="description"><?php echo esc_html__('Chưa có dữ liệu nguồn khách.', 'restaurant-booking'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h2><?php echo esc_html__('Đặt bàn theo chi nhánh', 'restaurant-booking'); ?></h2>
                        <?php if ($location_stats) : ?>
                            <ul class="rb-mini-list">
                                <?php foreach ($location_stats as $location) :
                                    $location_code = $location->location ? RB_I18n::sanitize_location($location->location) : 'vn';
                                    ?>
                                    <li>
                                        <span><?php echo esc_html(RB_I18n::get_location_label($location_code, $admin_language)); ?></span>
                                        <strong><?php echo esc_html($location->count); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="description"><?php echo esc_html__('Chưa có dữ liệu đặt bàn theo chi nhánh.', 'restaurant-booking'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rb-dashboard-column">
                    <div class="card">
                        <div class="rb-card-header">
                            <h2><?php echo esc_html__('Booking sắp diễn ra', 'restaurant-booking'); ?></h2>
                            <a class="rb-card-link" href="<?php echo esc_url(admin_url('admin.php?page=rb-bookings-list')); ?>"><?php echo esc_html__('Xem tất cả', 'restaurant-booking'); ?></a>
                        </div>
                        <?php if ($upcoming_bookings) : ?>
                            <table class="wp-list-table widefat fixed striped rb-mini-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Khách hàng', 'restaurant-booking'); ?></th>
                                        <th><?php echo esc_html__('Ngày/Giờ', 'restaurant-booking'); ?></th>
                                        <th><?php echo esc_html__('Số khách', 'restaurant-booking'); ?></th>
                                        <th><?php echo esc_html__('Trạng thái', 'restaurant-booking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_bookings as $booking) : ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo esc_html($booking->id); ?></strong><br>
                                                <?php echo esc_html($booking->customer_name); ?><br>
                                                <span class="rb-muted"><?php echo esc_html($booking->customer_phone); ?></span>
                                            </td>
                                            <td>
                                                <?php echo esc_html(date_i18n('d/m/Y', strtotime($booking->booking_date))); ?><br>
                                                <span class="rb-muted"><?php echo esc_html($booking->booking_time); ?></span>
                                            </td>
                                            <td style="text-align:center;"><?php echo esc_html($booking->guest_count); ?></td>
                                            <td>
                                                <span class="rb-status rb-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($this->get_status_label($booking->status)); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p class="description"><?php echo esc_html__('Không có booking sắp tới.', 'restaurant-booking'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h2><?php echo esc_html__('Hoạt động gần đây', 'restaurant-booking'); ?></h2>
                        <?php if ($recent_activity) : ?>
                            <ul class="rb-activity-list">
                                <?php foreach ($recent_activity as $activity) : ?>
                                    <li>
                                        <div>
                                            <strong>#<?php echo esc_html($activity->id); ?></strong>
                                            <?php echo esc_html($activity->customer_name); ?>
                                            <span class="rb-status rb-status-<?php echo esc_attr($activity->status); ?>"><?php echo esc_html($this->get_status_label($activity->status)); ?></span>
                                        </div>
                                        <span class="rb-activity-time"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($activity->created_at))); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="description"><?php echo esc_html__('Chưa có hoạt động nào.', 'restaurant-booking'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .rb-dashboard-wrap .rb-dashboard-title {
                margin-bottom: 15px;
            }
            .rb-dashboard-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 20px;
            }
            .rb-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap: 15px;
                margin-bottom: 25px;
            }
            .rb-stat-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 6px;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .rb-stat-label {
                font-size: 12px;
                text-transform: uppercase;
                color: #6b6b6b;
                letter-spacing: .04em;
            }
            .rb-stat-number {
                font-size: 30px;
                font-weight: 700;
            }
            .rb-stat-today { color: #2563eb; }
            .rb-stat-pending { color: #d97706; }
            .rb-stat-confirmed { color: #047857; }
            .rb-stat-completed { color: #059669; }
            .rb-stat-cancelled { color: #dc2626; }
            .rb-dashboard-columns {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
            }
            .rb-dashboard-column .card {
                margin-bottom: 20px;
            }
            .rb-status-list,
            .rb-mini-list,
            .rb-activity-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .rb-status-list li,
            .rb-mini-list li {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 6px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .rb-status-list li:last-child,
            .rb-mini-list li:last-child {
                border-bottom: none;
            }
            .rb-mini-table td,
            .rb-mini-table th {
                padding: 8px 10px;
            }
            .rb-muted { color: #6b7280; font-size: 12px; }
            .rb-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 10px;
            }
            .rb-card-link { font-size: 12px; text-decoration: none; }
            .rb-activity-list li {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .rb-activity-list li:last-child { border-bottom: none; }
            .rb-activity-time { color: #6b7280; font-size: 12px; }
            @media (max-width: 1024px) {
                .rb-dashboard-columns {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }

    public function display_booking_list_page() {
        global $wpdb;

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $table_name = $wpdb->prefix . 'rb_bookings';
        $admin_language = $this->get_admin_language();
        $locations = RB_I18n::get_locations();
        $languages = RB_I18n::get_languages();

        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $filter_source = isset($_GET['filter_source']) ? sanitize_text_field($_GET['filter_source']) : '';
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        $filter_location = isset($_GET['filter_location']) ? sanitize_text_field($_GET['filter_location']) : '';
        $sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'created_at';
        $sort_order = isset($_GET['sort_order']) ? sanitize_text_field($_GET['sort_order']) : 'DESC';

        $where_clauses = array('1=1');

        if (!empty($filter_status)) {
            $where_clauses[] = $wpdb->prepare("status = %s", $filter_status);
        }

        if (!empty($filter_source)) {
            $where_clauses[] = $wpdb->prepare("booking_source = %s", $filter_source);
        }

        if (!empty($filter_location)) {
            $where_clauses[] = $wpdb->prepare("location = %s", $filter_location);
        }

        if (!empty($filter_date_from) && !empty($filter_date_to)) {
            $where_clauses[] = $wpdb->prepare("booking_date BETWEEN %s AND %s", $filter_date_from, $filter_date_to);
        } elseif (!empty($filter_date_from)) {
            $where_clauses[] = $wpdb->prepare("booking_date >= %s", $filter_date_from);
        } elseif (!empty($filter_date_to)) {
            $where_clauses[] = $wpdb->prepare("booking_date <= %s", $filter_date_to);
        }

        $where = implode(' AND ', $where_clauses);

        $allowed_sort = array('id', 'customer_name', 'booking_date', 'booking_time', 'guest_count', 'status', 'booking_source', 'created_at', 'location', 'language');
        if (!in_array($sort_by, $allowed_sort, true)) {
            $sort_by = 'created_at';
        }

        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        $bookings = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY $sort_by $sort_order");

        $stats = array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'"),
            'confirmed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'confirmed'"),
            'completed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'"),
            'cancelled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'cancelled'")
        );

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Danh sách đặt bàn', 'restaurant-booking'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rb-create-booking')); ?>" class="page-title-action"><?php echo esc_html__('Tạo đặt bàn mới', 'restaurant-booking'); ?></a>
            </h1>

            <div class="rb-stats-grid" style="margin-bottom: 25px;">
                <div class="rb-stat-box">
                    <h3><?php echo esc_html__('Tổng đặt bàn', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number"><?php echo esc_html($stats['total']); ?></p>
                </div>
                <div class="rb-stat-box">
                    <h3><?php echo esc_html__('Chờ xác nhận', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color:#d97706;"><?php echo esc_html($stats['pending']); ?></p>
                </div>
                <div class="rb-stat-box">
                    <h3><?php echo esc_html__('Đã xác nhận', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color:#047857;"><?php echo esc_html($stats['confirmed']); ?></p>
                </div>
                <div class="rb-stat-box">
                    <h3><?php echo esc_html__('Hoàn thành', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color:#059669;"><?php echo esc_html($stats['completed']); ?></p>
                </div>
                <div class="rb-stat-box">
                    <h3><?php echo esc_html__('Đã hủy', 'restaurant-booking'); ?></h3>
                    <p class="rb-stat-number" style="color:#dc2626;"><?php echo esc_html($stats['cancelled']); ?></p>
                </div>
            </div>

            <div class="rb-filters-section" style="background:#fff; padding:20px; margin-bottom:20px; border:1px solid #ccd0d4; border-radius:3px;">
                <h2 style="margin-top:0;"><?php echo esc_html__('Bộ lọc & Sắp xếp', 'restaurant-booking'); ?></h2>
                <form method="get" action="" style="display:flex; gap:15px; flex-wrap:wrap; align-items:end;">
                    <input type="hidden" name="page" value="rb-bookings-list">
                    <div>
                        <label for="filter_status" style="display:block; font-weight:600;"><?php echo esc_html__('Trạng thái', 'restaurant-booking'); ?></label>
                        <select name="filter_status" id="filter_status" class="regular-text">
                            <option value=""><?php echo esc_html__('Tất cả', 'restaurant-booking'); ?></option>
                            <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php echo esc_html__('Chờ xác nhận', 'restaurant-booking'); ?></option>
                            <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>><?php echo esc_html__('Đã xác nhận', 'restaurant-booking'); ?></option>
                            <option value="completed" <?php selected($filter_status, 'completed'); ?>><?php echo esc_html__('Hoàn thành', 'restaurant-booking'); ?></option>
                            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>><?php echo esc_html__('Đã hủy', 'restaurant-booking'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="filter_source" style="display:block; font-weight:600;"><?php echo esc_html__('Nguồn', 'restaurant-booking'); ?></label>
                        <select name="filter_source" id="filter_source" class="regular-text">
                            <option value=""><?php echo esc_html__('Tất cả', 'restaurant-booking'); ?></option>
                            <option value="website" <?php selected($filter_source, 'website'); ?>>Website</option>
                            <option value="phone" <?php selected($filter_source, 'phone'); ?>><?php echo esc_html__('Điện thoại', 'restaurant-booking'); ?></option>
                            <option value="facebook" <?php selected($filter_source, 'facebook'); ?>>Facebook</option>
                            <option value="zalo" <?php selected($filter_source, 'zalo'); ?>>Zalo</option>
                            <option value="instagram" <?php selected($filter_source, 'instagram'); ?>>Instagram</option>
                            <option value="walk-in" <?php selected($filter_source, 'walk-in'); ?>><?php echo esc_html__('Khách vãng lai', 'restaurant-booking'); ?></option>
                            <option value="email" <?php selected($filter_source, 'email'); ?>>Email</option>
                            <option value="other" <?php selected($filter_source, 'other'); ?>><?php echo esc_html__('Khác', 'restaurant-booking'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="filter_location" style="display:block; font-weight:600;"><?php echo esc_html__('Khu vực', 'restaurant-booking'); ?></label>
                        <select name="filter_location" id="filter_location" class="regular-text">
                            <option value=""><?php echo esc_html__('Tất cả', 'restaurant-booking'); ?></option>
                            <?php foreach ($locations as $code => $location) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($filter_location, $code); ?>>
                                    <?php echo esc_html(RB_I18n::get_location_label($code, $admin_language)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="filter_date_from" style="display:block; font-weight:600;"><?php echo esc_html__('Từ ngày', 'restaurant-booking'); ?></label>
                        <input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" class="regular-text">
                    </div>

                    <div>
                        <label for="filter_date_to" style="display:block; font-weight:600;"><?php echo esc_html__('Đến ngày', 'restaurant-booking'); ?></label>
                        <input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" class="regular-text">
                    </div>

                    <div>
                        <label for="sort_by" style="display:block; font-weight:600;"><?php echo esc_html__('Sắp xếp theo', 'restaurant-booking'); ?></label>
                        <select name="sort_by" id="sort_by" class="regular-text">
                            <option value="created_at" <?php selected($sort_by, 'created_at'); ?>><?php echo esc_html__('Ngày tạo', 'restaurant-booking'); ?></option>
                            <option value="booking_date" <?php selected($sort_by, 'booking_date'); ?>><?php echo esc_html__('Ngày phục vụ', 'restaurant-booking'); ?></option>
                            <option value="booking_time" <?php selected($sort_by, 'booking_time'); ?>><?php echo esc_html__('Giờ', 'restaurant-booking'); ?></option>
                            <option value="customer_name" <?php selected($sort_by, 'customer_name'); ?>><?php echo esc_html__('Tên khách', 'restaurant-booking'); ?></option>
                            <option value="guest_count" <?php selected($sort_by, 'guest_count'); ?>><?php echo esc_html__('Số khách', 'restaurant-booking'); ?></option>
                            <option value="status" <?php selected($sort_by, 'status'); ?>><?php echo esc_html__('Trạng thái', 'restaurant-booking'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="sort_order" style="display:block; font-weight:600;"><?php echo esc_html__('Thứ tự', 'restaurant-booking'); ?></label>
                        <select name="sort_order" id="sort_order" class="regular-text">
                            <option value="DESC" <?php selected($sort_order, 'DESC'); ?>><?php echo esc_html__('Mới nhất trước', 'restaurant-booking'); ?></option>
                            <option value="ASC" <?php selected($sort_order, 'ASC'); ?>><?php echo esc_html__('Cũ nhất trước', 'restaurant-booking'); ?></option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="button button-primary" style="margin-top:0;"><?php echo esc_html__('Lọc kết quả', 'restaurant-booking'); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rb-bookings-list')); ?>" class="button"><?php echo esc_html__('Xóa bộ lọc', 'restaurant-booking'); ?></a>
                    </div>
                </form>
            </div>

            <p style="margin-bottom:10px;">
                <strong><?php printf(esc_html__('Hiển thị %d kết quả', 'restaurant-booking'), count($bookings)); ?></strong>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th><?php echo esc_html__('Khách hàng', 'restaurant-booking'); ?></th>
                        <th><?php echo esc_html__('Điện thoại', 'restaurant-booking'); ?></th>
                        <th><?php echo esc_html__('Ngày/Giờ', 'restaurant-booking'); ?></th>
                        <th style="width:80px; text-align:center;"><?php echo esc_html__('Số khách', 'restaurant-booking'); ?></th>
                        <th style="width:70px; text-align:center;"><?php echo esc_html__('Bàn số', 'restaurant-booking'); ?></th>
                        <th style="width:120px;"><?php echo esc_html__('Khu vực', 'restaurant-booking'); ?></th>
                        <th style="width:120px;"><?php echo esc_html__('Ngôn ngữ', 'restaurant-booking'); ?></th>
                        <th style="width:100px;"><?php echo esc_html__('Nguồn', 'restaurant-booking'); ?></th>
                        <th style="width:110px;"><?php echo esc_html__('Trạng thái', 'restaurant-booking'); ?></th>
                        <th style="width:220px;"><?php echo esc_html__('Hành động', 'restaurant-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bookings) : ?>
                        <?php foreach ($bookings as $booking) : ?>
                            <tr>
                                <td><?php echo esc_html($booking->id); ?></td>
                                <td><strong><?php echo esc_html($booking->customer_name); ?></strong></td>
                                <td><?php echo esc_html($booking->customer_phone); ?></td>
                                <td>
                                    <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($booking->booking_date))); ?></strong><br>
                                    <span class="rb-muted"><?php echo esc_html($booking->booking_time); ?></span>
                                </td>
                                <td style="text-align:center;"><?php echo esc_html($booking->guest_count); ?></td>
                                <td style="text-align:center;"><?php echo $booking->table_number ? '<strong>Bàn ' . esc_html($booking->table_number) . '</strong>' : '-'; ?></td>
                                <td><?php echo esc_html(RB_I18n::get_location_label(isset($booking->location) ? $booking->location : 'vn', $admin_language)); ?></td>
                                <td><?php echo esc_html($this->get_language_label(isset($booking->language) ? $booking->language : 'vi')); ?></td>
                                <td>
                                    <?php
                                    $source = isset($booking->booking_source) ? $booking->booking_source : 'website';
                                    echo '<span style="font-size:11px; padding:2px 6px; background:#e8e8e8; border-radius:3px;">' . esc_html($this->get_source_label($source)) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <span class="rb-status rb-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($this->get_status_label($booking->status)); ?></span>
                                </td>
                                <td>
                                    <?php if ($booking->status === 'pending') : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rb-bookings-list&action=confirm&id=' . $booking->id), 'rb_action')); ?>" class="button button-primary button-small"><?php echo esc_html__('Xác nhận', 'restaurant-booking'); ?></a>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rb-bookings-list&action=cancel&id=' . $booking->id), 'rb_action')); ?>" class="button button-small"><?php echo esc_html__('Hủy', 'restaurant-booking'); ?></a>
                                    <?php elseif ($booking->status === 'confirmed') : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rb-bookings-list&action=complete&id=' . $booking->id), 'rb_action')); ?>" class="button button-small"><?php echo esc_html__('Hoàn thành', 'restaurant-booking'); ?></a>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rb-bookings-list&action=cancel&id=' . $booking->id), 'rb_action')); ?>" class="button button-small"><?php echo esc_html__('Hủy', 'restaurant-booking'); ?></a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rb-bookings-list&action=delete&id=' . $booking->id), 'rb_action')); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Bạn có chắc muốn xóa?', 'restaurant-booking')); ?>');"><?php echo esc_html__('Xóa', 'restaurant-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="11" style="text-align:center; padding:40px;"><?php echo esc_html__('Không có đặt bàn nào.', 'restaurant-booking'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
            .rb-filters-section .button { margin-top: 0; }
            .rb-muted { color: #6b7280; font-size: 12px; }
        </style>
        <?php
    }


    public function display_tables_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';

        $admin_language = $this->get_admin_language();

        $locations = RB_I18n::get_locations();

        $tables = $wpdb->get_results("SELECT * FROM $table_name ORDER BY location, table_number");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quản lý bàn', 'restaurant-booking'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Thêm bàn mới', 'restaurant-booking'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('rb_add_table', 'rb_nonce'); ?>
                    <input type="hidden" name="action" value="add_table">
                    <table class="form-table">
                        <tr>
                            <th><label for="table_number">Số bàn</label></th>
                            <td>
                                <input type="number" name="table_number" id="table_number" min="1" required class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="capacity">Sức chứa</label></th>
                            <td>
                                <input type="number" name="capacity" id="capacity" min="1" max="20" required class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="table_location">Khu vực</label></th>
                            <td>
                                <select name="location" id="table_location" required>
                                    <?php foreach ($locations as $code => $location) : ?>
                                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html(RB_I18n::get_location_label($code, $admin_language)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Thêm bàn</button>
                    </p>
                </form>
            </div>
            
            <h2>Danh sách bàn</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Số bàn</th>
                        <th>Sức chứa</th>
                        <th>Khu vực</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tables) : ?>
                        <?php foreach ($tables as $table) : ?>
                            <tr>
                                <td><?php echo esc_html($table->table_number); ?></td>
                                <td><?php echo esc_html($table->capacity); ?> người</td>
                                <td><?php echo esc_html(RB_I18n::get_location_label(isset($table->location) ? $table->location : 'vn', $admin_language)); ?></td>
                                <td>
                                    <?php if ($table->is_available) : ?>
                                        <span style="color: green;">✓ Hoạt động</span>
                                    <?php else : ?>
                                        <span style="color: red;">✗ Tạm ngưng</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button 
                                        class="rb-toggle-table button button-small"
                                        data-table-id="<?php echo $table->id; ?>"
                                        data-available="<?php echo $table->is_available ? '1' : '0'; ?>">
                                        <?php echo $table->is_available ? 'Tạm ngưng' : 'Kích hoạt'; ?>
                                    </button>
                                    <a href="?page=rb-tables&action=delete_table&id=<?php echo $table->id; ?>&_wpnonce=<?php echo wp_create_nonce('rb_action'); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('Bạn có chắc muốn xóa bàn này?')">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Chưa có bàn nào. Vui lòng thêm bàn mới.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    

/**
 * Enhanced Customers Page - Add to class-admin.php
 * Thay thế hàm display_customers_page() hiện tại
 */

    public function display_customers_page() {
        global $wpdb, $rb_customer;
        $customer_table = $wpdb->prefix . 'rb_customers';
        
        // Get filters
        $filter_vip = isset($_GET['filter_vip']) ? sanitize_text_field($_GET['filter_vip']) : '';
        $filter_blacklist = isset($_GET['filter_blacklist']) ? sanitize_text_field($_GET['filter_blacklist']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'total_bookings';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        // Build query arguments
        $query_args = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        );
        
        if ($filter_vip === 'yes') {
            $query_args['vip_only'] = true;
        }
        
        if ($filter_blacklist !== '') {
            $query_args['blacklisted'] = $filter_blacklist === 'yes' ? 1 : 0;
        }
        
        // Get customers
        $customers = $rb_customer->get_customers($query_args);
        
        // Get stats
        $stats = $rb_customer->get_stats();
        $vip_suggestions = $rb_customer->get_vip_suggestions();
        $problematic = $rb_customer->get_problematic_customers();
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Quản lý khách hàng', 'restaurant-booking'); ?>
                <span class="subtitle" style="margin-left: 15px; color: #666; font-size: 14px;">
                    CRM & Customer Insights
                </span>
            </h1>
            
            <!-- Stats Dashboard -->
            <div class="rb-stats-grid" style="margin-bottom: 30px;">
                <div class="rb-stat-box">
                    <h3>Tổng khách hàng</h3>
                    <p class="rb-stat-number"><?php echo $stats['total']; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3>Khách VIP</h3>
                    <p class="rb-stat-number" style="color: #f39c12;">⭐ <?php echo $stats['vip']; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3>Blacklisted</h3>
                    <p class="rb-stat-number" style="color: #e74c3c;">🚫 <?php echo $stats['blacklisted']; ?></p>
                </div>
                
                <div class="rb-stat-box">
                    <h3>Mới tháng này</h3>
                    <p class="rb-stat-number" style="color: #3498db;">✨ <?php echo $stats['new_this_month']; ?></p>
                </div>
            </div>
            
            <!-- VIP Suggestions -->
            <?php if (!empty($vip_suggestions)) : ?>
            <div class="notice notice-info" style="margin-bottom: 20px;">
                <p><strong>💡 Gợi ý nâng cấp VIP:</strong> 
                    Có <?php echo count($vip_suggestions); ?> khách hàng đủ điều kiện VIP (≥5 lượt hoàn thành)
                    <button type="button" class="button button-small" onclick="jQuery('#vip-suggestions').toggle()">Xem chi tiết</button>
                </p>
                <div id="vip-suggestions" style="display: none; margin-top: 10px;">
                    <?php foreach ($vip_suggestions as $sugg) : ?>
                        <div style="padding: 8px; background: #f9f9f9; margin: 5px 0; border-radius: 3px;">
                            <strong><?php echo esc_html($sugg->name); ?></strong> 
                            (<?php echo esc_html($sugg->phone); ?>) - 
                            <?php echo $sugg->completed_bookings; ?> lượt hoàn thành
                            <button class="button button-small rb-set-vip" data-customer-id="<?php echo $sugg->id; ?>">
                                Set VIP
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Problematic Customers Warning -->
            <?php if (!empty($problematic)) : ?>
            <div class="notice notice-warning" style="margin-bottom: 20px;">
                <p><strong>⚠️ Cảnh báo:</strong> 
                    Có <?php echo count($problematic); ?> khách hàng có vấn đề (nhiều cancel/no-show)
                    <button type="button" class="button button-small" onclick="jQuery('#problematic-list').toggle()">Xem chi tiết</button>
                </p>
                <div id="problematic-list" style="display: none; margin-top: 10px;">
                    <?php foreach ($problematic as $prob) : ?>
                        <div style="padding: 8px; background: #fff3cd; margin: 5px 0; border-radius: 3px;">
                            <strong><?php echo esc_html($prob->name); ?></strong> - 
                            Tỷ lệ vấn đề: <?php echo round($prob->problem_rate, 1); ?>% 
                            (<?php echo $prob->problem_count; ?>/<?php echo $prob->total_bookings; ?>)
                            <button class="button button-small rb-blacklist" data-customer-id="<?php echo $prob->id; ?>">
                                Blacklist
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Filters Section -->
            <div class="card" style="margin-bottom: 20px; padding: 15px;">
                <h3 style="margin-top: 0;">🔍 Tìm kiếm & Lọc</h3>
                <form method="get" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                    <input type="hidden" name="page" value="rb-customers">
                    
                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tìm kiếm</label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" 
                            placeholder="Tên, SĐT, Email..." style="width: 100%;">
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">VIP</label>
                        <select name="filter_vip" style="width: 100%;">
                            <option value="">Tất cả</option>
                            <option value="yes" <?php selected($filter_vip, 'yes'); ?>>Chỉ VIP</option>
                            <option value="no" <?php selected($filter_vip, 'no'); ?>>Không VIP</option>
                        </select>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Blacklist</label>
                        <select name="filter_blacklist" style="width: 100%;">
                            <option value="">Tất cả</option>
                            <option value="yes" <?php selected($filter_blacklist, 'yes'); ?>>Bị cấm</option>
                            <option value="no" <?php selected($filter_blacklist, 'no'); ?>>Bình thường</option>
                        </select>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Sắp xếp</label>
                        <select name="orderby" style="width: 100%;">
                            <option value="total_bookings" <?php selected($orderby, 'total_bookings'); ?>>Tổng đặt bàn</option>
                            <option value="completed_bookings" <?php selected($orderby, 'completed_bookings'); ?>>Hoàn thành</option>
                            <option value="last_visit" <?php selected($orderby, 'last_visit'); ?>>Lần cuối</option>
                            <option value="first_visit" <?php selected($orderby, 'first_visit'); ?>>Lần đầu</option>
                            <option value="name" <?php selected($orderby, 'name'); ?>>Tên</option>
                        </select>
                    </div>
                    
                    <div style="flex: 1; min-width: 100px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Thứ tự</label>
                        <select name="order" style="width: 100%;">
                            <option value="DESC" <?php selected($order, 'DESC'); ?>>Giảm dần</option>
                            <option value="ASC" <?php selected($order, 'ASC'); ?>>Tăng dần</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="button button-primary">Áp dụng</button>
                        <a href="?page=rb-customers" class="button">Xóa bộ lọc</a>
                    </div>
                </form>
            </div>
            
            <!-- Results Count -->
            <p style="margin-bottom: 10px;">
                <strong><?php printf(__('Hiển thị %d kết quả', 'restaurant-booking'), count($customers)); ?></strong>
            </p>
            
            <!-- Customers Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Tên khách</th>
                        <th>Điện thoại</th>
                        <th>Email</th>
                        <th style="width: 80px;">Tổng đặt</th>
                        <th style="width: 80px;">Hoàn thành</th>
                        <th style="width: 80px;">Đã hủy</th>
                        <th style="width: 80px;">No-show</th>
                        <th style="width: 100px;">Lần cuối</th>
                        <th style="width: 100px;">Trạng thái</th>
                        <th style="width: 200px;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customers) : ?>
                        <?php foreach ($customers as $customer) : 
                            $success_rate = $customer->total_bookings > 0 
                                ? round(($customer->completed_bookings / $customer->total_bookings) * 100, 1) 
                                : 0;
                            $problem_rate = $customer->total_bookings > 0 
                                ? round((($customer->no_shows + $customer->cancelled_bookings) / $customer->total_bookings) * 100, 1) 
                                : 0;
                        ?>
                            <tr data-customer-id="<?php echo $customer->id; ?>">
                                <td><?php echo $customer->id; ?></td>
                                <td>
                                    <strong><?php echo esc_html($customer->name); ?></strong>
                                    <?php if ($customer->vip_status) : ?>
                                        <span style="color: gold; font-size: 16px;" title="VIP">⭐</span>
                                    <?php endif; ?>
                                    <?php if ($customer->blacklisted) : ?>
                                        <span style="color: red; font-size: 16px;" title="Blacklisted">🚫</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($customer->phone); ?></td>
                                <td><?php echo esc_html($customer->email); ?></td>
                                <td style="text-align: center;">
                                    <strong><?php echo $customer->total_bookings; ?></strong>
                                </td>
                                <td style="text-align: center; color: green;">
                                    <strong><?php echo $customer->completed_bookings; ?></strong>
                                    <br><small><?php echo $success_rate; ?>%</small>
                                </td>
                                <td style="text-align: center; color: orange;">
                                    <?php echo $customer->cancelled_bookings; ?>
                                </td>
                                <td style="text-align: center; color: red;">
                                    <?php echo $customer->no_shows; ?>
                                    <?php if ($problem_rate > 30) : ?>
                                        <br><small style="color: red;">⚠️ <?php echo $problem_rate; ?>%</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $customer->last_visit ? date('d/m/Y', strtotime($customer->last_visit)) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($customer->vip_status) : ?>
                                        <span class="rb-badge" style="background: #f39c12; color: white;">VIP</span>
                                    <?php endif; ?>
                                    <?php if ($customer->blacklisted) : ?>
                                        <span class="rb-badge" style="background: #e74c3c; color: white;">Cấm</span>
                                    <?php elseif ($problem_rate > 50) : ?>
                                        <span class="rb-badge" style="background: #ff6b6b; color: white;">Vấn đề</span>
                                    <?php elseif ($customer->completed_bookings >= 5) : ?>
                                        <span class="rb-badge" style="background: #27ae60; color: white;">Loyal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button button-small rb-view-history" 
                                            data-customer-id="<?php echo $customer->id; ?>"
                                            data-customer-phone="<?php echo esc_attr($customer->phone); ?>">
                                        Lịch sử
                                    </button>
                                    
                                    <?php if (!$customer->vip_status && $customer->completed_bookings >= 3) : ?>
                                        <button class="button button-small rb-set-vip" 
                                                data-customer-id="<?php echo $customer->id; ?>">
                                            Set VIP
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$customer->blacklisted && $problem_rate > 50) : ?>
                                        <button class="button button-small rb-blacklist" 
                                                data-customer-id="<?php echo $customer->id; ?>">
                                            Blacklist
                                        </button>
                                    <?php elseif ($customer->blacklisted) : ?>
                                        <button class="button button-small rb-unblacklist" 
                                                data-customer-id="<?php echo $customer->id; ?>">
                                            Bỏ cấm
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px;">
                                <p style="font-size: 16px; color: #666;">Chưa có khách hàng nào.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Customer History Modal -->
        <div id="rb-customer-history-modal" class="rb-modal" style="display: none;">
            <div class="rb-modal-content" style="max-width: 800px;">
                <span class="rb-close">&times;</span>
                <h2>Lịch sử đặt bàn</h2>
                <div id="rb-customer-history-content"></div>
            </div>
        </div>
        
        <style>
            .rb-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // View history
            $('.rb-view-history').on('click', function() {
                var phone = $(this).data('customer-phone');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rb_get_customer_history',
                        phone: phone,
                        nonce: '<?php echo wp_create_nonce("rb_admin_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<table class="wp-list-table widefat">';
                            html += '<thead><tr><th>Ngày</th><th>Giờ</th><th>Số khách</th><th>Bàn</th><th>Trạng thái</th></tr></thead>';
                            html += '<tbody>';
                            
                            $.each(response.data.history, function(i, booking) {
                                html += '<tr>';
                                html += '<td>' + booking.booking_date + '</td>';
                                html += '<td>' + booking.booking_time + '</td>';
                                html += '<td>' + booking.guest_count + '</td>';
                                html += '<td>' + (booking.table_number || '-') + '</td>';
                                html += '<td>' + booking.status + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                            
                            $('#rb-customer-history-content').html(html);
                            $('#rb-customer-history-modal').show();
                        }
                    }
                });
            });
            
            // Close modal
            $('.rb-close').on('click', function() {
                $('#rb-customer-history-modal').hide();
            });
            
            // Set VIP
            $('.rb-set-vip').on('click', function() {
                var customerId = $(this).data('customer-id');
                if (confirm('Nâng cấp khách hàng này lên VIP?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rb_set_customer_vip',
                            customer_id: customerId,
                            status: 1,
                            nonce: '<?php echo wp_create_nonce("rb_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                }
            });
            
            // Blacklist
            $('.rb-blacklist').on('click', function() {
                var customerId = $(this).data('customer-id');
                if (confirm('Blacklist khách hàng này? Họ sẽ không thể đặt bàn nữa.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rb_set_customer_blacklist',
                            customer_id: customerId,
                            status: 1,
                            nonce: '<?php echo wp_create_nonce("rb_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                }
            });
            
            // Unblacklist
            $('.rb-unblacklist').on('click', function() {
                var customerId = $(this).data('customer-id');
                if (confirm('Bỏ blacklist khách hàng này?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rb_set_customer_blacklist',
                            customer_id: customerId,
                            status: 0,
                            nonce: '<?php echo wp_create_nonce("rb_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    public function display_settings_page($section = null) {
        $settings = get_option('rb_settings', array());

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $admin_language = $this->get_admin_language();
        $locations = RB_I18n::get_locations();
        $languages = RB_I18n::get_languages();

        // Default values
        $defaults = array(
            'working_hours_mode' => 'simple', // simple or advanced
            'opening_time' => '09:00',
            'closing_time' => '22:00',
            'default_location' => 'vn',
            'lunch_break_enabled' => 'no',
            'lunch_break_start' => '14:00',
            'lunch_break_end' => '17:00',
            'morning_shift_start' => '09:00',
            'morning_shift_end' => '14:00',
            'evening_shift_start' => '17:00',
            'evening_shift_end' => '22:00',
            'time_slot_interval' => 30,
            'booking_buffer_time' => 0,
            'min_advance_booking' => 2, // hours
            'max_advance_booking' => 30, // days
            'max_guests_per_booking' => 20,
            'auto_confirm_enabled' => 'no',
            'require_deposit' => 'no',
            'deposit_amount' => 100000,
            'deposit_for_guests' => 10,
            'admin_email' => get_option('admin_email'),
            'enable_email' => 'yes',
            'enable_sms' => 'no',
            'sms_api_key' => '',
            'reminder_hours_before' => 24,
            'special_closed_dates' => '',
            'cancellation_hours' => 2,
            'weekend_enabled' => 'yes',
            'no_show_auto_blacklist' => 3,
        );
        
        $settings = wp_parse_args($settings, $defaults);

        $tabs = array(
            'hours' => array('label' => '🕐 Giờ làm việc', 'target' => '#tab-hours'),
            'booking' => array('label' => '📅 Đặt bàn', 'target' => '#tab-booking'),
            'notifications' => array('label' => '🔔 Thông báo', 'target' => '#tab-notifications'),
            'policies' => array('label' => '📋 Chính sách', 'target' => '#tab-policies'),
            'advanced' => array('label' => '🔧 Nâng cao', 'target' => '#tab-advanced'),
        );

        if ($section === null && isset($_GET['section'])) {
            $section = sanitize_text_field(wp_unslash($_GET['section']));
        }

        if ($section === null) {
            $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $page_section_map = array(
                'rb-settings-working-hours' => 'hours',
                'rb-settings-notifications' => 'notifications',
                'rb-settings-policies' => 'policies',
            );
            if (isset($page_section_map[$current_page])) {
                $section = $page_section_map[$current_page];
            }
        }

        $active_tab = isset($tabs[$section]) ? $section : 'hours';
        ?>
        <div class="wrap">
            <h1>⚙️ Cài đặt Restaurant Booking</h1>
            
            <form method="post" action="" id="rb-settings-form">
                <?php wp_nonce_field('rb_save_settings', 'rb_nonce'); ?>
                <input type="hidden" name="action" value="save_settings">
                
                <!-- Tab Navigation -->
                <h2 class="nav-tab-wrapper">
                    <?php foreach ($tabs as $tab_key => $tab_data) : ?>
                        <a href="<?php echo esc_attr($tab_data['target']); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr($tab_data['target']); ?>">
                            <?php echo esc_html($tab_data['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </h2>
                
                <!-- Tab 1: Working Hours -->
                <div id="tab-hours" class="rb-tab-content" style="display: <?php echo $active_tab === 'hours' ? 'block' : 'none'; ?>;">
                    <h2>Giờ làm việc của nhà hàng</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Chế độ cài đặt</th>
                            <td>
                                <label>
                                    <input type="radio" name="rb_settings[working_hours_mode]" value="simple" 
                                        <?php checked($settings['working_hours_mode'], 'simple'); ?>>
                                    <strong>Đơn giản</strong> - Chỉ 1 ca làm việc
                                </label>
                                <br>
                                <label style="margin-top: 10px; display: inline-block;">
                                    <input type="radio" name="rb_settings[working_hours_mode]" value="advanced" 
                                        <?php checked($settings['working_hours_mode'], 'advanced'); ?>>
                                    <strong>Nâng cao</strong> - 2 ca (sáng & tối), có giờ nghỉ trưa
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Simple Mode -->
                    <div id="simple-hours-section" style="display: <?php echo $settings['working_hours_mode'] == 'simple' ? 'block' : 'none'; ?>">
                        <h3>Cài đặt giờ làm việc đơn giản</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="opening_time">Giờ mở cửa</label>
                                </th>
                                <td>
                                    <input type="time" name="rb_settings[opening_time]" id="opening_time" 
                                        value="<?php echo esc_attr($settings['opening_time']); ?>" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="closing_time">Giờ đóng cửa</label>
                                </th>
                                <td>
                                    <input type="time" name="rb_settings[closing_time]" id="closing_time" 
                                        value="<?php echo esc_attr($settings['closing_time']); ?>" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="lunch_break_enabled">Có giờ nghỉ trưa?</label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="rb_settings[lunch_break_enabled]" id="lunch_break_enabled" 
                                            value="yes" <?php checked($settings['lunch_break_enabled'], 'yes'); ?>>
                                        Nhà hàng có giờ nghỉ trưa
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <div id="lunch-break-times" style="display: <?php echo $settings['lunch_break_enabled'] == 'yes' ? 'block' : 'none'; ?>; margin-left: 30px;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Giờ bắt đầu nghỉ</th>
                                    <td>
                                        <input type="time" name="rb_settings[lunch_break_start]" 
                                            value="<?php echo esc_attr($settings['lunch_break_start']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Giờ kết thúc nghỉ</th>
                                    <td>
                                        <input type="time" name="rb_settings[lunch_break_end]" 
                                            value="<?php echo esc_attr($settings['lunch_break_end']); ?>" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Advanced Mode -->
                    <div id="advanced-hours-section" style="display: <?php echo $settings['working_hours_mode'] == 'advanced' ? 'block' : 'none'; ?>">
                        <h3>Cài đặt 2 ca làm việc</h3>
                        
                        <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                            <h4 style="margin-top: 0;">🌅 Ca sáng</h4>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Giờ bắt đầu ca sáng</th>
                                    <td>
                                        <input type="time" name="rb_settings[morning_shift_start]" 
                                            value="<?php echo esc_attr($settings['morning_shift_start']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Giờ kết thúc ca sáng</th>
                                    <td>
                                        <input type="time" name="rb_settings[morning_shift_end]" 
                                            value="<?php echo esc_attr($settings['morning_shift_end']); ?>" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
                            <h4 style="margin-top: 0;">🌙 Ca tối</h4>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Giờ bắt đầu ca tối</th>
                                    <td>
                                        <input type="time" name="rb_settings[evening_shift_start]" 
                                            value="<?php echo esc_attr($settings['evening_shift_start']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Giờ kết thúc ca tối</th>
                                    <td>
                                        <input type="time" name="rb_settings[evening_shift_end]" 
                                            value="<?php echo esc_attr($settings['evening_shift_end']); ?>" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="weekend_enabled">Mở cửa cuối tuần</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rb_settings[weekend_enabled]" id="weekend_enabled" 
                                        value="yes" <?php checked($settings['weekend_enabled'], 'yes'); ?>>
                                    Nhận đặt bàn vào thứ 7 & Chủ nhật
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Tab 2: Booking Settings -->
                <div id="tab-booking" class="rb-tab-content" style="display: <?php echo $active_tab === 'booking' ? 'block' : 'none'; ?>;">
                    <h2>Cài đặt đặt bàn</h2>

                    <p class="description">
                        Plugin tự động sử dụng ngôn ngữ quản trị WordPress: <strong>
                            <?php
                            if (isset($languages[$admin_language])) {
                                echo esc_html($languages[$admin_language]['flag'] . ' ' . $languages[$admin_language]['native']);
                            } else {
                                echo esc_html(strtoupper($admin_language));
                            }
                            ?>
                        </strong>.
                    </p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="rb_default_location">Khu vực mặc định</label>
                            </th>
                            <td>
                                <select name="rb_settings[default_location]" id="rb_default_location">
                                    <?php foreach ($locations as $code => $location) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['default_location'], $code); ?>>
                                            <?php echo esc_html(RB_I18n::get_location_label($code, $this->get_admin_language())); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Khu vực mặc định hiển thị với khách khi mở form đặt bàn.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="time_slot_interval">Khoảng thời gian mỗi slot</label>
                            </th>
                            <td>
                                <select name="rb_settings[time_slot_interval]" id="time_slot_interval">
                                    <option value="15" <?php selected($settings['time_slot_interval'], 15); ?>>15 phút</option>
                                    <option value="30" <?php selected($settings['time_slot_interval'], 30); ?>>30 phút</option>
                                    <option value="45" <?php selected($settings['time_slot_interval'], 45); ?>>45 phút</option>
                                    <option value="60" <?php selected($settings['time_slot_interval'], 60); ?>>60 phút</option>
                                </select>
                                <p class="description">Khoảng cách giữa các khung giờ có thể đặt</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="booking_buffer_time">Thời gian buffer</label>
                            </th>
                            <td>
                                <select name="rb_settings[booking_buffer_time]" id="booking_buffer_time">
                                    <option value="0" <?php selected($settings['booking_buffer_time'], 0); ?>>Không có</option>
                                    <option value="15" <?php selected($settings['booking_buffer_time'], 15); ?>>15 phút</option>
                                    <option value="30" <?php selected($settings['booking_buffer_time'], 30); ?>>30 phút</option>
                                </select>
                                <p class="description">Khoảng thời gian trống giữa các booking (để dọn dẹp bàn)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="min_advance_booking">Đặt trước tối thiểu</label>
                            </th>
                            <td>
                                <input type="number" name="rb_settings[min_advance_booking]" id="min_advance_booking" 
                                    value="<?php echo esc_attr($settings['min_advance_booking']); ?>" min="0" max="48" class="small-text"> giờ
                                <p class="description">Khách phải đặt trước ít nhất bao nhiêu giờ</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_advance_booking">Đặt trước tối đa</label>
                            </th>
                            <td>
                                <input type="number" name="rb_settings[max_advance_booking]" id="max_advance_booking" 
                                    value="<?php echo esc_attr($settings['max_advance_booking']); ?>" min="1" max="90" class="small-text"> ngày
                                <p class="description">Khách có thể đặt trước tối đa bao nhiêu ngày</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_guests_per_booking">Số khách tối đa</label>
                            </th>
                            <td>
                                <input type="number" name="rb_settings[max_guests_per_booking]" id="max_guests_per_booking" 
                                    value="<?php echo esc_attr($settings['max_guests_per_booking']); ?>" min="1" max="100" class="small-text"> người
                                <p class="description">Số lượng khách tối đa cho 1 booking</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="auto_confirm_enabled">Tự động xác nhận</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rb_settings[auto_confirm_enabled]" id="auto_confirm_enabled" 
                                        value="yes" <?php checked($settings['auto_confirm_enabled'], 'yes'); ?>>
                                    Tự động xác nhận & gán bàn khi có booking mới
                                </label>
                                <p class="description">Nếu tắt, admin phải xác nhận thủ công</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Tab 3: Notifications -->
                <div id="tab-notifications" class="rb-tab-content" style="display: <?php echo $active_tab === 'notifications' ? 'block' : 'none'; ?>;">
                    <h2>Cài đặt thông báo</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="admin_email">Email quản trị</label>
                            </th>
                            <td>
                                <input type="email" name="rb_settings[admin_email]" id="admin_email" 
                                    value="<?php echo esc_attr($settings['admin_email']); ?>" class="regular-text">
                                <p class="description">Email nhận thông báo đặt bàn mới</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Email cho khách hàng</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rb_settings[enable_email]" value="yes" 
                                        <?php checked($settings['enable_email'], 'yes'); ?>>
                                    Gửi email xác nhận cho khách hàng
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="reminder_hours_before">Nhắc lịch trước</label>
                            </th>
                            <td>
                                <input type="number" name="rb_settings[reminder_hours_before]" id="reminder_hours_before" 
                                    value="<?php echo esc_attr($settings['reminder_hours_before']); ?>" min="1" max="72" class="small-text"> giờ
                                <p class="description">Gửi email nhắc nhở khách trước bao nhiêu giờ</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">SMS Notification</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rb_settings[enable_sms]" value="yes" 
                                        <?php checked($settings['enable_sms'], 'yes'); ?>>
                                    Bật thông báo SMS (cần API key)
                                </label>
                                <br><br>
                                <input type="text" name="rb_settings[sms_api_key]" placeholder="Nhập SMS API Key..." 
                                    value="<?php echo esc_attr($settings['sms_api_key']); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Tab 4: Policies -->
                <div id="tab-policies" class="rb-tab-content" style="display: <?php echo $active_tab === 'policies' ? 'block' : 'none'; ?>;">
                    <h2>Chính sách & Quy định</h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="require_deposit">Yêu cầu đặt cọc</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rb_settings[require_deposit]" id="require_deposit" 
                                        value="yes" <?php checked($settings['require_deposit'], 'yes'); ?>>
                                    Yêu cầu khách đặt cọc
                                </label>
                            </td>
                        </tr>
                        
                        <tr id="deposit-settings" style="display: <?php echo $settings['require_deposit'] == 'yes' ? 'table-row' : 'none'; ?>;">
                            <th scope="row">Chi tiết đặt cọc</th>
                            <td>
                                <label>
                                    Số tiền cọc: 
                                    <input type="number" name="rb_settings[deposit_amount]" 
                                        value="<?php echo esc_attr($settings['deposit_amount']); ?>" class="regular-text"> VNĐ
                                </label>
                                <br><br>
                                <label>
                                    Áp dụng cho booking từ: 
                                    <input type="number" name="rb_settings[deposit_for_guests]" 
                                        value="<?php echo esc_attr($settings['deposit_for_guests']); ?>" class="small-text"> khách trở lên
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cancellation_hours">Hủy miễn phí trước</label>
                            </th>
                            <td>
                                <input type="number" name="rb_settings[cancellation_hours]" id="cancellation_hours" 
                                    value="<?php echo esc_attr($settings['cancellation_hours']); ?>" min="0" max="48" class="small-text"> giờ
                                <p class="description">Khách có thể hủy miễn phí trước bao nhiêu giờ</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="no_show_auto_blacklist">Auto-blacklist No-show</label>
                            </th>
                            <td>
                                <input type="number" name="rb_settings[no_show_auto_blacklist]" id="no_show_auto_blacklist" 
                                    value="<?php echo esc_attr($settings['no_show_auto_blacklist']); ?>" min="1" max="10" class="small-text"> lần
                                <p class="description">Tự động blacklist khách sau bao nhiêu lần no-show</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="special_closed_dates">Ngày nghỉ đặc biệt</label>
                            </th>
                            <td>
                                <textarea name="rb_settings[special_closed_dates]" id="special_closed_dates" 
                                    rows="4" class="large-text" placeholder="Mỗi ngày một dòng (định dạng: YYYY-MM-DD)&#10;Ví dụ:&#10;2025-01-01&#10;2025-04-30&#10;2025-09-02"><?php echo esc_textarea($settings['special_closed_dates']); ?></textarea>
                                <p class="description">Danh sách ngày nghỉ lễ, tết không nhận booking</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Tab 5: Advanced -->
                <div id="tab-advanced" class="rb-tab-content" style="display: <?php echo $active_tab === 'advanced' ? 'block' : 'none'; ?>;">
                    <h2>Cài đặt nâng cao</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Database Cleanup</th>
                            <td>
                                <button type="button" class="button" id="cleanup-old-bookings">
                                    🗑️ Xóa booking cũ hơn 6 tháng
                                </button>
                                <p class="description">Dọn dẹp dữ liệu cũ để tối ưu database</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Export Data</th>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=rb-booking-settings&action=export_csv'); ?>" class="button">
                                    📊 Export tất cả booking ra CSV
                                </a>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Reset Plugin</th>
                            <td>
                                <button type="button" class="button button-secondary" id="reset-plugin" 
                                    style="border-color: #dc3545; color: #dc3545;">
                                    ⚠️ Reset toàn bộ dữ liệu
                                </button>
                                <p class="description" style="color: #dc3545;">
                                    <strong>Cảnh báo:</strong> Xóa TẤT CẢ bookings, tables, customers!
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">💾 Lưu tất cả cài đặt</button>
                </p>
            </form>
        </div>
        
        <style>
            .rb-tab-content {
                background: white;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-top: none;
            }
            .nav-tab-wrapper {
                margin-bottom: 0;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.rb-tab-content').hide();
                $(target).show();
            });
            
            // Toggle working hours mode
            $('input[name="rb_settings[working_hours_mode]"]').on('change', function() {
                if ($(this).val() === 'simple') {
                    $('#simple-hours-section').show();
                    $('#advanced-hours-section').hide();
                } else {
                    $('#simple-hours-section').hide();
                    $('#advanced-hours-section').show();
                }
            });
            
            // Toggle lunch break
            $('#lunch_break_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#lunch-break-times').show();
                } else {
                    $('#lunch-break-times').hide();
                }
            });
            
            // Toggle deposit settings
            $('#require_deposit').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#deposit-settings').show();
                } else {
                    $('#deposit-settings').hide();
                }
            });
            
            // Cleanup old bookings
            $('#cleanup-old-bookings').on('click', function() {
                if (confirm('Xóa tất cả booking cũ hơn 6 tháng? Hành động này không thể hoàn tác!')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rb_cleanup_old_bookings',
                            nonce: '<?php echo wp_create_nonce("rb_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Đã xóa ' + response.data.deleted + ' booking cũ!');
                            }
                        }
                    });
                }
            });
            
            // Reset plugin
            $('#reset-plugin').on('click', function() {
                var confirm1 = confirm('CẢNH BÁO: Bạn sắp XÓA TOÀN BỘ dữ liệu!\n\nTiếp tục?');
                if (confirm1) {
                    var confirm2 = confirm('Lần xác nhận cuối cùng!\n\nĐiều này sẽ xóa:\n- Tất cả bookings\n- Tất cả tables\n- Tất cả customers\n- Tất cả settings\n\nBạn CHẮC CHẮN muốn reset?');
                    if (confirm2) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'rb_reset_plugin',
                                nonce: '<?php echo wp_create_nonce("rb_admin_nonce"); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Đã reset plugin thành công!');
                                    location.reload();
                                }
                            }
                        });
                    }
                }
            });
        });
        </script>
        <?php
    }
    public function display_settings_working_hours_page() {
        $this->display_settings_page('hours');
    }

    public function display_settings_notifications_page() {
        $this->display_settings_page('notifications');
    }

    public function display_settings_policies_page() {
        $this->display_settings_page('policies');
    }


    public function display_reports_page() {
        global $wpdb;

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $table_name = $wpdb->prefix . 'rb_bookings';
        $admin_language = $this->get_admin_language();

        $status_data = $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table_name GROUP BY status", OBJECT_K);
        $monthly_summary = $wpdb->get_results(
            "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS period, COUNT(*) AS total, SUM(guest_count) AS guests
            FROM $table_name
            GROUP BY period
            ORDER BY period DESC
            LIMIT 6"
        );
        $source_stats = $wpdb->get_results(
            "SELECT booking_source, COUNT(*) AS count FROM $table_name GROUP BY booking_source ORDER BY count DESC"
        );
        $peak_hours = $wpdb->get_results(
            "SELECT booking_time, COUNT(*) AS count FROM $table_name GROUP BY booking_time ORDER BY count DESC LIMIT 8"
        );
        $top_customers = $wpdb->get_results(
            "SELECT customer_phone, customer_name, COUNT(*) AS bookings
            FROM $table_name
            WHERE customer_phone <> ''
            GROUP BY customer_phone, customer_name
            ORDER BY bookings DESC
            LIMIT 5"
        );
        $location_stats = $wpdb->get_results(
            "SELECT location, COUNT(*) AS count FROM $table_name GROUP BY location ORDER BY count DESC"
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Báo cáo đặt bàn', 'restaurant-booking'); ?></h1>
            <p class="description"><?php echo esc_html__('Theo dõi hiệu suất và xu hướng đặt bàn để tối ưu vận hành.', 'restaurant-booking'); ?></p>

            <div class="rb-report-grid">
                <div class="card">
                    <h2><?php echo esc_html__('Tổng quan trạng thái', 'restaurant-booking'); ?></h2>
                    <ul class="rb-mini-list">
                        <li><span><?php echo esc_html__('Chờ xác nhận', 'restaurant-booking'); ?></span><strong><?php echo isset($status_data['pending']) ? esc_html($status_data['pending']->count) : '0'; ?></strong></li>
                        <li><span><?php echo esc_html__('Đã xác nhận', 'restaurant-booking'); ?></span><strong><?php echo isset($status_data['confirmed']) ? esc_html($status_data['confirmed']->count) : '0'; ?></strong></li>
                        <li><span><?php echo esc_html__('Hoàn thành', 'restaurant-booking'); ?></span><strong><?php echo isset($status_data['completed']) ? esc_html($status_data['completed']->count) : '0'; ?></strong></li>
                        <li><span><?php echo esc_html__('Đã hủy', 'restaurant-booking'); ?></span><strong><?php echo isset($status_data['cancelled']) ? esc_html($status_data['cancelled']->count) : '0'; ?></strong></li>
                    </ul>
                </div>

                <div class="card">
                    <h2><?php echo esc_html__('Nguồn khách hàng', 'restaurant-booking'); ?></h2>
                    <?php if ($source_stats) : ?>
                        <ul class="rb-mini-list">
                            <?php foreach ($source_stats as $source) : ?>
                                <li><span><?php echo esc_html($this->get_source_label($source->booking_source)); ?></span><strong><?php echo esc_html($source->count); ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html__('Chưa có dữ liệu nguồn khách.', 'restaurant-booking'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2><?php echo esc_html__('Khung giờ cao điểm', 'restaurant-booking'); ?></h2>
                    <?php if ($peak_hours) : ?>
                        <ul class="rb-mini-list">
                            <?php foreach ($peak_hours as $row) : ?>
                                <li><span><?php echo esc_html($row->booking_time); ?></span><strong><?php echo esc_html($row->count); ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html__('Chưa có dữ liệu giờ cao điểm.', 'restaurant-booking'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2><?php echo esc_html__('Khách hàng thân thiết', 'restaurant-booking'); ?></h2>
                    <?php if ($top_customers) : ?>
                        <ul class="rb-mini-list">
                            <?php foreach ($top_customers as $customer) : ?>
                                <li>
                                    <span><?php echo esc_html($customer->customer_name ?: $customer->customer_phone); ?></span>
                                    <strong><?php printf(esc_html__('%d lượt', 'restaurant-booking'), $customer->bookings); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html__('Chưa có dữ liệu khách thân thiết.', 'restaurant-booking'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('Xu hướng theo tháng', 'restaurant-booking'); ?></h2>
                <?php if ($monthly_summary) : ?>
                    <table class="wp-list-table widefat striped rb-mini-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Tháng', 'restaurant-booking'); ?></th>
                                <th><?php echo esc_html__('Số booking', 'restaurant-booking'); ?></th>
                                <th><?php echo esc_html__('Tổng khách', 'restaurant-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_summary as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n('m/Y', strtotime($row->period . '-01'))); ?></td>
                                    <td><?php echo esc_html($row->total); ?></td>
                                    <td><?php echo esc_html($row->guests ?: 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="description"><?php echo esc_html__('Chưa có dữ liệu theo tháng.', 'restaurant-booking'); ?></p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('Phân bố theo chi nhánh', 'restaurant-booking'); ?></h2>
                <?php if ($location_stats) : ?>
                    <table class="wp-list-table widefat striped rb-mini-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Chi nhánh', 'restaurant-booking'); ?></th>
                                <th><?php echo esc_html__('Số booking', 'restaurant-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($location_stats as $row) :
                                $location_code = $row->location ? RB_I18n::sanitize_location($row->location) : 'vn';
                                ?>
                                <tr>
                                    <td><?php echo esc_html(RB_I18n::get_location_label($location_code, $admin_language)); ?></td>
                                    <td><?php echo esc_html($row->count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="description"><?php echo esc_html__('Chưa có dữ liệu chi nhánh.', 'restaurant-booking'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .rb-report-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .rb-mini-table td,
            .rb-mini-table th {
                padding: 8px 12px;
            }
        </style>
        <?php
    }


    public function display_feature_updates_page() {
        if (!class_exists('RB_Update_Features')) {
            require_once RB_PLUGIN_DIR . 'includes/class-update-features.php';
        }

        $phases = RB_Update_Features::get_all_features();
        $status_labels = RB_Update_Features::get_status_labels();
        $last_updated = RB_Update_Features::get_last_updated_at();
        $last_updated_display = '';
        $notification_settings = RB_Update_Features::get_notification_settings();
        $subscribers_text = RB_Update_Features::get_subscribers_text();
        $available_tokens = RB_Update_Features::get_available_interest_tokens();
        $changelog_entries = RB_Update_Features::get_changelog_entries(8);

        if (!empty($last_updated)) {
            $timestamp = strtotime($last_updated);
            if ($timestamp) {
                $date_format = get_option('date_format') . ' ' . get_option('time_format');
                $last_updated_display = date_i18n($date_format, $timestamp);
            }
        }
        ?>
        <div class="wrap rb-feature-updates-wrap">
            <h1><?php esc_html_e('Lộ trình cập nhật tính năng', 'restaurant-booking'); ?></h1>
            <p class="description">
                <?php esc_html_e('Theo dõi tiến độ các hạng mục cải tiến để phối hợp với đội vận hành và hỗ trợ khách hàng.', 'restaurant-booking'); ?>
            </p>

            <?php if ($last_updated_display) : ?>
                <p class="rb-feature-updates-meta">
                    <span class="dashicons dashicons-update"></span>
                    <?php
                    printf(
                        esc_html__('Lần cập nhật gần nhất: %s', 'restaurant-booking'),
                        '<strong>' . esc_html($last_updated_display) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($changelog_entries)) : ?>
                <div class="rb-feature-changelog">
                    <h2><?php esc_html_e('Nhật ký cập nhật gần đây', 'restaurant-booking'); ?></h2>
                    <ul>
                        <?php foreach ($changelog_entries as $entry) : ?>
                            <?php
                            $entry_timestamp = isset($entry['timestamp']) ? strtotime($entry['timestamp']) : false;
                            $entry_date = $entry_timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $entry_timestamp) : '';
                            ?>
                            <li>
                                <span class="rb-changelog-date"><?php echo esc_html($entry_date); ?></span>
                                <span class="rb-changelog-feature"><?php echo esc_html($entry['feature_name']); ?></span>
                                <span class="rb-changelog-summary"><?php echo esc_html($entry['summary']); ?></span>
                                <?php if (!empty($entry['tags'])) : ?>
                                    <span class="rb-changelog-tags"><?php echo esc_html(implode(', ', $entry['tags'])); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('rb_save_feature_updates', 'rb_nonce'); ?>
                <input type="hidden" name="action" value="save_feature_updates">

                <?php if (empty($phases)) : ?>
                    <p><?php esc_html_e('Hiện chưa có dữ liệu roadmap để hiển thị.', 'restaurant-booking'); ?></p>
                <?php else : ?>
                    <?php foreach ($phases as $phase => $phase_data) : ?>
                        <div class="rb-feature-phase-card">
                            <div class="rb-feature-phase-header">
                                <div>
                                    <h2><?php echo esc_html($phase_data['title']); ?></h2>
                                    <?php if (!empty($phase_data['summary'])) : ?>
                                        <p class="rb-feature-phase-summary"><?php echo wp_kses_post($phase_data['summary']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($phase_data['themes'])) : ?>
                                    <div class="rb-feature-phase-themes">
                                        <?php foreach ($phase_data['themes'] as $theme) : ?>
                                            <span class="rb-feature-phase-theme"><?php echo esc_html($theme); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (empty($phase_data['features'])) : ?>
                                <p><?php esc_html_e('Chưa có hạng mục nào trong phase này.', 'restaurant-booking'); ?></p>
                            <?php else : ?>
                                <table class="widefat striped rb-feature-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Hạng mục', 'restaurant-booking'); ?></th>
                                            <th class="rb-feature-status-heading"><?php esc_html_e('Trạng thái', 'restaurant-booking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($phase_data['features'] as $feature) : ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($feature['name']); ?></strong>
                                                    <?php if (!empty($feature['description'])) : ?>
                                                        <p class="description"><?php echo wp_kses_post($feature['description']); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($feature['benefit'])) : ?>
                                                        <p class="rb-feature-benefit">
                                                            <span class="dashicons dashicons-awards"></span>
                                                            <?php echo wp_kses_post($feature['benefit']); ?>
                                                        </p>
                                                    <?php endif; ?>

                                                    <div class="rb-feature-meta">
                                                        <?php if (!empty($feature['owner'])) : ?>
                                                            <span class="rb-feature-owner">
                                                                <span class="dashicons dashicons-admin-users"></span>
                                                                <?php echo esc_html(sprintf(__('Owner: %s', 'restaurant-booking'), $feature['owner'])); ?>
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if (!empty($feature['success_metric'])) : ?>
                                                            <span class="rb-feature-metric">
                                                                <span class="dashicons dashicons-chart-line"></span>
                                                                <?php echo esc_html($feature['success_metric']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($feature['tags'])) : ?>
                                                        <div class="rb-feature-tags">
                                                            <?php foreach ($feature['tags'] as $tag) : ?>
                                                                <span class="rb-feature-tag"><?php echo esc_html($tag); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="rb-feature-status-cell">
                                                    <span class="rb-feature-status-badge status-<?php echo esc_attr($feature['status']); ?>">
                                                        <?php echo esc_html($feature['status_label']); ?>
                                                    </span>
                                                    <label class="screen-reader-text" for="feature-status-<?php echo esc_attr($phase . '-' . $feature['id']); ?>">
                                                        <?php esc_html_e('Cập nhật trạng thái', 'restaurant-booking'); ?>
                                                    </label>
                                                    <select id="feature-status-<?php echo esc_attr($phase . '-' . $feature['id']); ?>"
                                                            name="feature_status[<?php echo esc_attr($phase); ?>][<?php echo esc_attr($feature['id']); ?>]">
                                                        <?php foreach ($status_labels as $key => $label) : ?>
                                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($feature['status'], $key); ?>>
                                                                <?php echo esc_html($label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="rb-feature-notifications">
                    <h2><?php esc_html_e('Thông báo tự động', 'restaurant-booking'); ?></h2>
                    <p class="description"><?php esc_html_e('Thiết lập email thông báo mỗi khi trạng thái roadmap thay đổi để team và khách hàng chủ động nắm thông tin.', 'restaurant-booking'); ?></p>

                    <label class="rb-feature-notifications-toggle">
                        <input type="checkbox" name="feature_notifications[enabled]" value="1" <?php checked($notification_settings['enabled']); ?>>
                        <?php esc_html_e('Kích hoạt gửi email tự động', 'restaurant-booking'); ?>
                    </label>

                    <fieldset class="rb-feature-notifications-statuses">
                        <legend><?php esc_html_e('Thông báo khi trạng thái chuyển sang', 'restaurant-booking'); ?></legend>
                        <?php foreach ($status_labels as $key => $label) : ?>
                            <label>
                                <input type="checkbox" name="feature_notifications[notify_statuses][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $notification_settings['notify_statuses'], true)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <label for="subscriber-preferences">
                        <strong><?php esc_html_e('Danh sách nhận thông báo', 'restaurant-booking'); ?></strong>
                    </label>
                    <textarea id="subscriber-preferences" name="subscriber_preferences" rows="5" class="widefat" placeholder="email@example.com|analytics,completed"><?php echo esc_textarea($subscribers_text); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Mỗi dòng gồm email và tuỳ chọn sau dấu “|”. Ví dụ: user@example.com|automation,completed. Để trống phần tuỳ chọn nếu muốn nhận mọi cập nhật.', 'restaurant-booking'); ?>
                    </p>

                    <?php if (!empty($available_tokens)) : ?>
                        <div class="rb-feature-tokens">
                            <p><strong><?php esc_html_e('Từ khoá ưu tiên khả dụng', 'restaurant-booking'); ?>:</strong></p>
                            <ul>
                                <?php foreach ($available_tokens as $token => $label) : ?>
                                    <li><code><?php echo esc_html($token); ?></code> – <?php echo esc_html($label); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <?php submit_button(__('Lưu cập nhật roadmap', 'restaurant-booking')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_admin_actions() {
        if (!isset($_GET['page']) || (strpos($_GET['page'], 'restaurant-booking') === false && strpos($_GET['page'], 'rb-') === false)) {
            return;
        }

        // Handle export CSV (GET request)
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isset($_GET['page']) && $_GET['page'] === 'rb-booking-settings') {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            $this->export_bookings_csv();
            exit; // Important: exit after export
        }

        if (!isset($_GET['_wpnonce']) && !isset($_POST['rb_nonce'])) {
            return;
        }

        if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'rb_action')) {
                wp_die(__('Security check failed', 'restaurant-booking'));
            }

            $action = sanitize_text_field($_GET['action']);
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            switch ($action) {
                case 'confirm':
                    $this->confirm_booking($id);
                    break;
                case 'cancel':
                    $this->cancel_booking($id);
                    break;
                case 'complete':
                    $this->complete_booking($id);
                    break;
                case 'delete':
                    $this->delete_booking($id);
                    break;
                case 'delete_table':
                    $this->delete_table($id);
                    break;
            }
        }

        if (isset($_POST['action']) && isset($_POST['rb_nonce'])) {
            $action = sanitize_text_field($_POST['action']);

            switch ($action) {
                case 'save_settings':
                    if (wp_verify_nonce($_POST['rb_nonce'], 'rb_save_settings')) {
                        $this->save_settings();
                    }
                    break;
                case 'add_table':
                    if (wp_verify_nonce($_POST['rb_nonce'], 'rb_add_table')) {
                        $this->add_table();
                    }
                    break;
                case 'create_admin_booking':
                    if (wp_verify_nonce($_POST['rb_nonce'], 'rb_create_admin_booking')) {
                        $this->create_admin_booking();
                    }
                    break;
                case 'save_feature_updates':
                    if (wp_verify_nonce($_POST['rb_nonce'], 'rb_save_feature_updates')) {
                        if (!class_exists('RB_Update_Features')) {
                            require_once RB_PLUGIN_DIR . 'includes/class-update-features.php';
                        }

                        $statuses = isset($_POST['feature_status']) ? wp_unslash($_POST['feature_status']) : array();
                        $changed = RB_Update_Features::bulk_update_statuses($statuses);

                        $notification_settings = isset($_POST['feature_notifications']) ? wp_unslash($_POST['feature_notifications']) : array();
                        $notifications_changed = RB_Update_Features::save_notification_settings($notification_settings);

                        $subscriber_preferences = isset($_POST['subscriber_preferences']) ? wp_unslash($_POST['subscriber_preferences']) : '';
                        $subscribers_changed = RB_Update_Features::save_subscriber_preferences_from_text($subscriber_preferences);

                        $message = ($changed || $notifications_changed || $subscribers_changed) ? 'feature_updates_saved' : 'feature_updates_noop';

                        wp_redirect(admin_url('admin.php?page=rb-feature-updates&message=' . $message));
                        exit;
                    }
                    break;
            }
        }
    }
    
    private function create_admin_booking() {
        global $wpdb, $rb_booking;

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $language = isset($_POST['booking_language']) ? RB_I18n::sanitize_language($_POST['booking_language']) : 'vi';
        $location = isset($_POST['booking_location']) ? RB_I18n::sanitize_location($_POST['booking_location']) : 'vn';

        $booking_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'guest_count' => intval($_POST['guest_count']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'booking_time' => sanitize_text_field($_POST['booking_time']),
            'booking_source' => sanitize_text_field($_POST['booking_source']),
            'special_requests' => isset($_POST['special_requests']) ? sanitize_textarea_field($_POST['special_requests']) : '',
            'admin_notes' => isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '',
            'status' => 'pending',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'language' => $language,
            'location' => $location
        );

        $is_available = $rb_booking->is_time_slot_available(
            $booking_data['booking_date'],
            $booking_data['booking_time'],
            $booking_data['guest_count'],
            $location
        );
        
        if (!$is_available) {
            wp_redirect(admin_url('admin.php?page=rb-create-booking&message=no_availability'));
            exit;
        }
        
        $booking_id = $rb_booking->create_booking($booking_data);
        
        if (is_wp_error($booking_id)) {
            wp_redirect(admin_url('admin.php?page=rb-create-booking&message=error'));
            exit;
        }
        
        if (isset($_POST['auto_confirm']) && $_POST['auto_confirm'] == '1') {
            $result = $rb_booking->confirm_booking($booking_id);
            
            if (!is_wp_error($result)) {
                $booking = $rb_booking->get_booking($booking_id);
                if ($booking && class_exists('RB_Email')) {
                    $email = new RB_Email();
                    $email->send_confirmation_email($booking);
                }
            }
        }
        
        wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=admin_booking_created'));
        exit;
    }
    
    private function confirm_booking($id) {
        global $wpdb, $rb_booking;
        $table_name = $wpdb->prefix . 'rb_bookings';

        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

        if (!$booking) {
            wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=booking_not_found'));
            exit;
        }

        $result = $rb_booking->confirm_booking($id);

        if (is_wp_error($result)) {
            $error_message = urlencode($result->get_error_message());
            wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=no_tables&error=' . $error_message));
            exit;
        }

        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if ($booking && class_exists('RB_Email')) {
            $email = new RB_Email();
            $email->send_confirmation_email($booking);
        }

        wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=confirmed'));
        exit;
    }
    
    private function cancel_booking($id) {
        global $rb_booking;
        $rb_booking->cancel_booking($id);
        
        wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=cancelled'));
        exit;
    }
    
    private function complete_booking($id) {
        global $rb_booking;
        $rb_booking->complete_booking($id);
        
        wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=completed'));
        exit;
    }
    
    private function delete_booking($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_redirect(admin_url('admin.php?page=rb-bookings-list&message=deleted'));
        exit;
    }
    
    private function delete_table($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_redirect(admin_url('admin.php?page=rb-tables&message=deleted'));
        exit;
    }
    
    private function add_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_tables';

        $table_number = intval($_POST['table_number']);
        $capacity = intval($_POST['capacity']);

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $location = isset($_POST['location']) ? RB_I18n::sanitize_location($_POST['location']) : 'vn';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE table_number = %d", $table_number));

        if ($exists) {
            wp_redirect(admin_url('admin.php?page=rb-tables&message=exists'));
            exit;
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'table_number' => $table_number,
                'capacity' => $capacity,
                'location' => $location,
                'is_available' => 1,
                'created_at' => current_time('mysql')
            )
        );
        
        wp_redirect(admin_url('admin.php?page=rb-tables&message=added'));
        exit;
    }
    
    private function save_settings() {
        $settings = isset($_POST['rb_settings']) ? $_POST['rb_settings'] : array();

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $clean_settings = array(
            // Working hours
            'working_hours_mode' => isset($settings['working_hours_mode']) ? sanitize_text_field($settings['working_hours_mode']) : 'simple',
            'opening_time' => isset($settings['opening_time']) ? sanitize_text_field($settings['opening_time']) : '09:00',
            'closing_time' => isset($settings['closing_time']) ? sanitize_text_field($settings['closing_time']) : '22:00',
            'default_location' => isset($settings['default_location']) ? RB_I18n::sanitize_location($settings['default_location']) : 'vn',
            'lunch_break_enabled' => isset($settings['lunch_break_enabled']) ? 'yes' : 'no',
            'lunch_break_start' => isset($settings['lunch_break_start']) ? sanitize_text_field($settings['lunch_break_start']) : '14:00',
            'lunch_break_end' => isset($settings['lunch_break_end']) ? sanitize_text_field($settings['lunch_break_end']) : '17:00',
            'morning_shift_start' => isset($settings['morning_shift_start']) ? sanitize_text_field($settings['morning_shift_start']) : '09:00',
            'morning_shift_end' => isset($settings['morning_shift_end']) ? sanitize_text_field($settings['morning_shift_end']) : '14:00',
            'evening_shift_start' => isset($settings['evening_shift_start']) ? sanitize_text_field($settings['evening_shift_start']) : '17:00',
            'evening_shift_end' => isset($settings['evening_shift_end']) ? sanitize_text_field($settings['evening_shift_end']) : '22:00',
            'weekend_enabled' => isset($settings['weekend_enabled']) ? 'yes' : 'no',
            
            // Booking settings
            'time_slot_interval' => isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30,
            'booking_buffer_time' => isset($settings['booking_buffer_time']) ? intval($settings['booking_buffer_time']) : 0,
            'min_advance_booking' => isset($settings['min_advance_booking']) ? intval($settings['min_advance_booking']) : 2,
            'max_advance_booking' => isset($settings['max_advance_booking']) ? intval($settings['max_advance_booking']) : 30,
            'max_guests_per_booking' => isset($settings['max_guests_per_booking']) ? intval($settings['max_guests_per_booking']) : 20,
            'auto_confirm_enabled' => isset($settings['auto_confirm_enabled']) ? 'yes' : 'no',
            
            // Notifications
            'admin_email' => isset($settings['admin_email']) ? sanitize_email($settings['admin_email']) : get_option('admin_email'),
            'enable_email' => isset($settings['enable_email']) ? 'yes' : 'no',
            'enable_sms' => isset($settings['enable_sms']) ? 'yes' : 'no',
            'sms_api_key' => isset($settings['sms_api_key']) ? sanitize_text_field($settings['sms_api_key']) : '',
            'reminder_hours_before' => isset($settings['reminder_hours_before']) ? intval($settings['reminder_hours_before']) : 24,
            
            // Policies
            'require_deposit' => isset($settings['require_deposit']) ? 'yes' : 'no',
            'deposit_amount' => isset($settings['deposit_amount']) ? intval($settings['deposit_amount']) : 100000,
            'deposit_for_guests' => isset($settings['deposit_for_guests']) ? intval($settings['deposit_for_guests']) : 10,
            'cancellation_hours' => isset($settings['cancellation_hours']) ? intval($settings['cancellation_hours']) : 2,
            'no_show_auto_blacklist' => isset($settings['no_show_auto_blacklist']) ? intval($settings['no_show_auto_blacklist']) : 3,
            'special_closed_dates' => isset($settings['special_closed_dates']) ? sanitize_textarea_field($settings['special_closed_dates']) : '',
        );
        
        update_option('rb_settings', $clean_settings);
        
        wp_redirect(admin_url('admin.php?page=rb-booking-settings&message=saved'));
        exit;
    }
    
    public function display_admin_notices() {
        if (!isset($_GET['message'])) {
            return;
        }

        $message = sanitize_text_field($_GET['message']);
        $text = '';
        $type = 'success';

        switch ($message) {
            case 'admin_booking_created':
                $text = 'Đã tạo đặt bàn thành công!';
                break;
            case 'no_availability':
                $text = 'Không còn bàn trống cho thời gian này!';
                $type = 'error';
                break;
            case 'confirmed':
                $text = 'Đặt bàn đã được xác nhận.';
                break;
            case 'cancelled':
                $text = 'Đặt bàn đã được hủy.';
                break;
            case 'completed':
                $text = 'Đặt bàn đã hoàn thành.';
                break;
            case 'deleted':
                $text = 'Đã xóa thành công.';
                break;
            case 'saved':
                $text = '✅ Cài đặt đã được lưu thành công!';
                break;
            case 'added':
                $text = 'Đã thêm bàn mới.';
                break;
            case 'exists':
                $text = 'Số bàn này đã tồn tại.';
                $type = 'error';
                break;
            case 'booking_not_found':
                $text = 'Không tìm thấy đặt bàn.';
                $type = 'error';
                break;
            case 'no_tables':
                $error_detail = isset($_GET['error']) ? urldecode($_GET['error']) : 'Hết bàn trống';
                $text = 'Không thể xác nhận: ' . $error_detail;
                $type = 'error';
                break;
            case 'feature_updates_saved':
                $text = 'Roadmap tính năng đã được cập nhật.';
                break;
            case 'feature_updates_noop':
                $text = 'Không có thay đổi trạng thái nào được lưu.';
                $type = 'info';
                break;
        }

        if ($text) {
            ?>
            <div class="notice notice-<?php echo $type; ?> is-dismissible">
                <p><?php echo $text; ?></p>
            </div>
            <?php
        }
    }
        
    public function display_timeline_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $admin_language = $this->get_admin_language();
        $backend_texts = RB_I18n::get_section_translations('backend', $admin_language);
        $settings = get_option('rb_settings', array());
        $locations = RB_I18n::get_locations();
        $default_location = isset($settings['default_location']) ? RB_I18n::sanitize_location($settings['default_location']) : 'vn';
        $today = current_time('mysql');
        $today_date = $today ? substr($today, 0, 10) : date('Y-m-d');

        $title = isset($backend_texts['timeline_title']) ? $backend_texts['timeline_title'] : __('Booking timeline', 'restaurant-booking');
        $location_label = isset($backend_texts['timeline_location_filter']) ? $backend_texts['timeline_location_filter'] : __('Location', 'restaurant-booking');
        $date_label = isset($backend_texts['timeline_date_label']) ? $backend_texts['timeline_date_label'] : __('Date', 'restaurant-booking');
        $auto_refresh_label = isset($backend_texts['timeline_auto_refresh_label']) ? $backend_texts['timeline_auto_refresh_label'] : __('Auto refresh', 'restaurant-booking');
        $manual_refresh_label = isset($backend_texts['timeline_manual_refresh_label']) ? $backend_texts['timeline_manual_refresh_label'] : __('Refresh timeline', 'restaurant-booking');

        ?>
        <div class="wrap rb-timeline-wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <div class="rb-timeline-quick-actions">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rb-create-booking')); ?>">+ <?php echo esc_html__('Tạo đặt bàn', 'restaurant-booking'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rb-bookings-list')); ?>"><?php echo esc_html__('Danh sách đặt bàn', 'restaurant-booking'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rb-tables')); ?>"><?php echo esc_html__('Quản lý bàn', 'restaurant-booking'); ?></a>
            </div>

            <div id="rb-timeline-toolbar" class="rb-timeline-toolbar">
                <div class="rb-toolbar-group">
                    <label for="rb-timeline-location" class="rb-toolbar-label"><?php echo esc_html($location_label); ?></label>
                    <select id="rb-timeline-location" class="rb-timeline-select">
                        <?php foreach ($locations as $code => $location) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($default_location, $code); ?>>
                                <?php echo esc_html((isset($location['flag']) ? $location['flag'] . ' ' : '') . RB_I18n::get_location_label($code, $admin_language)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rb-toolbar-group rb-toolbar-date">
                    <label for="rb-timeline-date" class="rb-toolbar-label"><?php echo esc_html($date_label); ?></label>
                    <div class="rb-date-controls">
                        <button type="button" class="button rb-timeline-prev" aria-label="<?php echo esc_attr__('Previous day', 'restaurant-booking'); ?>">&lsaquo;</button>
                        <input type="date" id="rb-timeline-date" class="rb-timeline-date" value="<?php echo esc_attr($today_date); ?>" />
                        <button type="button" class="button rb-timeline-next" aria-label="<?php echo esc_attr__('Next day', 'restaurant-booking'); ?>">&rsaquo;</button>
                        <button type="button" class="button rb-timeline-today"><?php echo esc_html__('Today', 'restaurant-booking'); ?></button>
                    </div>
                </div>

                <div class="rb-toolbar-group rb-toolbar-actions">
                    <label class="rb-switch">
                        <input type="checkbox" id="rb-timeline-auto-refresh" checked>
                        <span class="rb-switch-label"><?php echo esc_html($auto_refresh_label); ?></span>
                    </label>
                    <button type="button" class="button button-secondary rb-timeline-refresh">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html($manual_refresh_label); ?>
                    </button>
                </div>
            </div>

            <div id="rb-timeline-status" class="rb-timeline-status" role="status" aria-live="polite"></div>

            <div id="rb-timeline-app" class="rb-timeline-app" data-default-date="<?php echo esc_attr($today_date); ?>" data-default-location="<?php echo esc_attr($default_location); ?>">
                <div class="rb-timeline-empty-state">
                    <span class="spinner is-active"></span>
                    <p><?php echo esc_html__('Loading timeline…', 'restaurant-booking'); ?></p>
                </div>
            </div>
        </div>

        <style>
            .rb-timeline-quick-actions {
                margin-bottom: 15px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
        </style>

        <div id="rb-timeline-modal" class="rb-timeline-modal" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="rb-timeline-modal__dialog" role="document">
                <button type="button" class="rb-timeline-modal__close" aria-label="<?php echo esc_attr__('Close', 'restaurant-booking'); ?>">&times;</button>
                <div class="rb-timeline-modal__content"></div>
            </div>
        </div>
        <?php
    }

    private function get_status_label($status) {
        $labels = array(
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'cancelled' => 'Đã hủy',
            'completed' => 'Hoàn thành',
            'checked_in' => 'Đã check-in'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    private function get_source_label($source) {
        $labels = array(
            'website' => '🌐 Website',
            'phone' => '📞 Điện thoại',
            'facebook' => '📘 Facebook',
            'zalo' => '💬 Zalo',
            'instagram' => '📷 Instagram',
            'walk-in' => '🚶 Vãng lai',
            'email' => '✉️ Email',
            'other' => '❓ Khác'
        );

        return isset($labels[$source]) ? $labels[$source] : $source;
    }

    private function get_language_label($code) {
        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $languages = RB_I18n::get_languages();

        if (isset($languages[$code])) {
            $language = $languages[$code];
            return trim($language['flag'] . ' ' . $language['native']);
        }

        return strtoupper($code);
    }

/**
 * Generate time slots với hỗ trợ giờ nghỉ trưa & 2 ca
 * Thay thế function generate_time_slots() cũ
 */
    private function generate_time_slots($start = null, $end = null, $interval = null) {
        $settings = get_option('rb_settings', array());
        
        $mode = isset($settings['working_hours_mode']) ? $settings['working_hours_mode'] : 'simple';
        $interval = $interval ?: (isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30);
        $buffer = isset($settings['booking_buffer_time']) ? intval($settings['booking_buffer_time']) : 0;
        
        $slots = array();
        
        if ($mode === 'advanced') {
            // Advanced mode: 2 shifts
            $morning_start = isset($settings['morning_shift_start']) ? $settings['morning_shift_start'] : '09:00';
            $morning_end = isset($settings['morning_shift_end']) ? $settings['morning_shift_end'] : '14:00';
            $evening_start = isset($settings['evening_shift_start']) ? $settings['evening_shift_start'] : '17:00';
            $evening_end = isset($settings['evening_shift_end']) ? $settings['evening_shift_end'] : '22:00';
            
            // Morning shift
            $slots = array_merge($slots, $this->generate_shift_slots($morning_start, $morning_end, $interval, $buffer));
            
            // Evening shift
            $slots = array_merge($slots, $this->generate_shift_slots($evening_start, $evening_end, $interval, $buffer));
            
        } else {
            // Simple mode
            $start = $start ?: (isset($settings['opening_time']) ? $settings['opening_time'] : '09:00');
            $end = $end ?: (isset($settings['closing_time']) ? $settings['closing_time'] : '22:00');
            
            $has_lunch_break = isset($settings['lunch_break_enabled']) && $settings['lunch_break_enabled'] === 'yes';
            
            if ($has_lunch_break) {
                $lunch_start = isset($settings['lunch_break_start']) ? $settings['lunch_break_start'] : '14:00';
                $lunch_end = isset($settings['lunch_break_end']) ? $settings['lunch_break_end'] : '17:00';
                
                // Before lunch
                $slots = array_merge($slots, $this->generate_shift_slots($start, $lunch_start, $interval, $buffer));
                
                // After lunch
                $slots = array_merge($slots, $this->generate_shift_slots($lunch_end, $end, $interval, $buffer));
            } else {
                // No lunch break
                $slots = $this->generate_shift_slots($start, $end, $interval, $buffer);
            }
        }
        
        return $slots;
    }

    /**
     * Generate slots cho 1 ca cụ thể
     */
    private function generate_shift_slots($start, $end, $interval, $buffer = 0) {
        $slots = array();
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $step = ($interval + $buffer) * 60; // Convert to seconds
        
        while ($start_time < $end_time) {
            $slots[] = date('H:i', $start_time);
            $start_time += $step;
        }
        
        return $slots;
    }

    /**
     * Kiểm tra xem 1 ngày có phải ngày nghỉ đặc biệt không
     */
    public function is_special_closed_date($date) {
        $settings = get_option('rb_settings', array());
        $closed_dates = isset($settings['special_closed_dates']) ? $settings['special_closed_dates'] : '';
        
        if (empty($closed_dates)) {
            return false;
        }
        
        $dates_array = explode("\n", $closed_dates);
        $dates_array = array_map('trim', $dates_array);
        
        return in_array($date, $dates_array);
    }

    /**
     * Kiểm tra xem có thể booking vào ngày này không
     */
    public function is_booking_allowed_on_date($date) {
        $settings = get_option('rb_settings', array());
        
        // Check special closed dates
        if ($this->is_special_closed_date($date)) {
            return false;
        }
        
        // Check weekend
        $weekend_enabled = isset($settings['weekend_enabled']) && $settings['weekend_enabled'] === 'yes';
        $day_of_week = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)
        
        if (!$weekend_enabled && ($day_of_week == 6 || $day_of_week == 7)) {
            return false;
        }
        
        // Check advance booking limits
        $min_advance = isset($settings['min_advance_booking']) ? intval($settings['min_advance_booking']) : 2;
        $max_advance = isset($settings['max_advance_booking']) ? intval($settings['max_advance_booking']) : 30;
        
        $booking_datetime = strtotime($date);
        $now = current_time('timestamp');
        $min_datetime = $now + ($min_advance * 3600); // hours to seconds
        $max_datetime = $now + ($max_advance * 86400); // days to seconds
        
        if ($booking_datetime < $min_datetime || $booking_datetime > $max_datetime) {
            return false;
        }
        
        return true;
    }

    /**
     * Lấy thông tin giờ làm việc để hiển thị cho frontend
     */
    public function get_working_hours_info() {
        $settings = get_option('rb_settings', array());
        $mode = isset($settings['working_hours_mode']) ? $settings['working_hours_mode'] : 'simple';
        
        $info = array(
            'mode' => $mode,
            'time_slots' => $this->generate_time_slots()
        );
        
        if ($mode === 'advanced') {
            $info['morning_shift'] = array(
                'start' => isset($settings['morning_shift_start']) ? $settings['morning_shift_start'] : '09:00',
                'end' => isset($settings['morning_shift_end']) ? $settings['morning_shift_end'] : '14:00'
            );
            $info['evening_shift'] = array(
                'start' => isset($settings['evening_shift_start']) ? $settings['evening_shift_start'] : '17:00',
                'end' => isset($settings['evening_shift_end']) ? $settings['evening_shift_end'] : '22:00'
            );
        } else {
            $info['opening_time'] = isset($settings['opening_time']) ? $settings['opening_time'] : '09:00';
            $info['closing_time'] = isset($settings['closing_time']) ? $settings['closing_time'] : '22:00';
            
            if (isset($settings['lunch_break_enabled']) && $settings['lunch_break_enabled'] === 'yes') {
                $info['lunch_break'] = array(
                    'start' => isset($settings['lunch_break_start']) ? $settings['lunch_break_start'] : '14:00',
                    'end' => isset($settings['lunch_break_end']) ? $settings['lunch_break_end'] : '17:00'
                );
            }
        }
        
        return $info;
    }
    private function export_bookings_csv() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rb_bookings';
        
        $bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=restaurant-bookings-' . date('Y-m-d') . '.csv');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, array(
            'ID',
            'Tên khách',
            'Điện thoại',
            'Email',
            'Ngày đặt',
            'Giờ đặt',
            'Số khách',
            'Bàn số',
            'Nguồn',
            'Trạng thái',
            'Yêu cầu đặc biệt',
            'Ghi chú admin',
            'Ngày tạo'
        ));
        
        // Data rows
        foreach ($bookings as $booking) {
            fputcsv($output, array(
                $booking->id,
                $booking->customer_name,
                $booking->customer_phone,
                $booking->customer_email,
                $booking->booking_date,
                $booking->booking_time,
                $booking->guest_count,
                $booking->table_number ?: '-',
                $booking->booking_source ?: 'website',
                $booking->status,
                $booking->special_requests ?: '',
                isset($booking->admin_notes) ? $booking->admin_notes : '',
                $booking->created_at
            ));
        }
        
        fclose($output);
        exit;
    }
}