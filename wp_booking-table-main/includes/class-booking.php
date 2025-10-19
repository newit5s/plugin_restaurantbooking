<?php
/**
 * Booking Class - Xử lý logic đặt bàn
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Booking {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    public function get_booking($booking_id) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ));
    }
    
    public function get_bookings($args = array()) {
        $defaults = array(
            'status' => '',
            'date' => '',
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'booking_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $where_clauses = array('1=1');
        
        if (!empty($args['status'])) {
            $where_clauses[] = $this->wpdb->prepare("status = %s", $args['status']);
        }
        
        if (!empty($args['date'])) {
            $where_clauses[] = $this->wpdb->prepare("booking_date = %s", $args['date']);
        }
        
        $where = implode(' AND ', $where_clauses);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $sql = "SELECT * FROM $table_name WHERE $where ORDER BY $orderby";
        
        if ($args['limit'] > 0) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    public function create_booking($data) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';

        $defaults = array(
            'status' => 'pending',
            'booking_source' => 'website',
            'created_at' => current_time('mysql'),
            'table_number' => null,
            'language' => 'vi',
            'location' => 'vn'
        );

        $data = wp_parse_args($data, $defaults);

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $data['language'] = isset($data['language']) ? RB_I18n::sanitize_language($data['language']) : 'vi';
        $data['location'] = isset($data['location']) ? RB_I18n::sanitize_location($data['location']) : 'vn';

        // Validate required fields
        $required = array('customer_name', 'customer_phone', 'customer_email', 'guest_count', 'booking_date', 'booking_time', 'location');

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Field %s is required', 'restaurant-booking'), $field));
            }
        }

        $result = $this->wpdb->insert($table_name, $data);

        if ($result === false) {
            return new WP_Error('db_error', __('Could not create booking', 'restaurant-booking'));
        }

        $booking_id = $this->wpdb->insert_id;

        // *** THAY ĐỔI CHÍNH: Đảm bảo class Customer được load và khởi tạo ***
        if (!class_exists('RB_Customer')) {
            require_once RB_PLUGIN_DIR . 'includes/class-customer.php';
        }

        global $rb_customer;
        if (!$rb_customer) {
            $rb_customer = new RB_Customer();
        }

        // Tự động cập nhật thông tin khách hàng vào CRM
        $rb_customer->update_customer_from_booking($booking_id);

        return $booking_id;
    }
    
    public function update_booking($booking_id, $data) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $result = $this->wpdb->update(
            $table_name,
            $data,
            array('id' => $booking_id)
        );
        
        return $result !== false;
    }
    
    public function delete_booking($booking_id) {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        return $this->wpdb->delete($table_name, array('id' => $booking_id));
    }
    
    public function confirm_booking($booking_id) {
        global $wpdb;
        $b_tbl = $wpdb->prefix . 'rb_bookings';

        $bk = $this->get_booking($booking_id);
        if (!$bk || $bk->status === 'confirmed') {
            return new WP_Error('rb_invalid', 'Booking không tồn tại hoặc đã confirmed.');
        }

        // Chọn bàn nhỏ nhất đủ chỗ
        $slot_table = $this->get_smallest_available_table(
            $bk->booking_date,
            $bk->booking_time,
            (int) $bk->guest_count,
            isset($bk->location) ? $bk->location : 'vn'
        );
        if (!$slot_table) {
            return new WP_Error('rb_no_table', 'Hết bàn phù hợp để xác nhận ở khung giờ này.');
        }

        $ok = $wpdb->update(
            $b_tbl,
            array(
                'status' => 'confirmed',
                'table_number' => (int)$slot_table->table_number,
                'confirmed_at' => current_time('mysql'),
            ),
            array('id' => (int)$booking_id),
            array('%s', '%d', '%s'),
            array('%d')
        );

        if (false === $ok) {
            return new WP_Error('rb_update_fail', 'Xác nhận thất bại, vui lòng thử lại.');
        }
        
        return true;
    }
    
    public function cancel_booking($booking_id) {
        $result = $this->update_booking($booking_id, array('status' => 'cancelled'));
        
        // Đánh dấu booking đã hủy trong CRM
        if ($result && class_exists('RB_Customer')) {
            global $rb_customer;
            if ($rb_customer) {
                $rb_customer->mark_cancelled($booking_id);
            }
        }
        
        return $result;
    }
    
    public function complete_booking($booking_id) {
        $result = $this->update_booking($booking_id, array('status' => 'completed'));
        
        // Đánh dấu booking đã hoàn thành trong CRM
        if ($result && class_exists('RB_Customer')) {
            global $rb_customer;
            if ($rb_customer) {
                $rb_customer->mark_completed($booking_id);
            }
        }
        
        return $result;
    }
    
    /**
     * Đánh dấu no-show (khách đặt nhưng không đến)
     */
    public function mark_no_show($booking_id) {
        $result = $this->update_booking($booking_id, array('status' => 'no-show'));
        
        if ($result && class_exists('RB_Customer')) {
            global $rb_customer;
            if ($rb_customer) {
                $rb_customer->mark_no_show($booking_id);
            }
        }
        
        return $result;
    }
    
    public function is_time_slot_available($date, $time, $guest_count, $location = 'vn', $exclude_booking_id = null) {
        global $wpdb;
        $tables_table = $wpdb->prefix . 'rb_tables';
        $bookings_table = $wpdb->prefix . 'rb_bookings';

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        if (is_numeric($location) && $exclude_booking_id === null) {
            $exclude_booking_id = (int) $location;
            $location = 'vn';
        }

        $location = RB_I18n::sanitize_location($location);

        // Tính tổng sức chứa
        $total_capacity = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(capacity) FROM {$tables_table} WHERE is_available = 1 AND location = %s",
            $location
        ));

        if ($total_capacity <= 0) {
            return false;
        }

        // Tính tổng số khách đã book (pending + confirmed)
        $sql = "SELECT SUM(guest_count)
            FROM {$bookings_table}
            WHERE booking_date = %s
            AND booking_time = %s
            AND location = %s
            AND status IN ('pending', 'confirmed', 'checked_in')";

        $params = array($date, $time, $location);

        if ($exclude_booking_id) {
            $sql .= ' AND id != %d';
            $params[] = (int) $exclude_booking_id;
        }

        $booked_guests = (int) $wpdb->get_var($wpdb->prepare($sql, $params));

        $remaining_capacity = $total_capacity - $booked_guests;

        return $remaining_capacity >= $guest_count;
    }

    public function get_smallest_available_table($date, $time, $guest_count, $location = 'vn') {
        global $wpdb;
        $t = $wpdb->prefix . 'rb_tables';
        $b = $wpdb->prefix . 'rb_bookings';

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $location = RB_I18n::sanitize_location($location);

        $sql = $wpdb->prepare(
            "SELECT t.table_number, t.capacity
             FROM {$t} t
             WHERE t.is_available = 1
              AND t.location = %s
              AND t.capacity >= %d
              AND t.table_number NOT IN (
                SELECT b.table_number
                FROM {$b} b
                WHERE b.booking_date = %s
                  AND b.booking_time = %s
                  AND b.location = %s
                  AND b.status IN ('confirmed', 'pending', 'checked_in')
                  AND b.table_number IS NOT NULL
              )
             ORDER BY t.capacity ASC, t.table_number ASC
             LIMIT 1",
            $location,
            (int) $guest_count,
            $date,
            $time,
            $location
        );

        return $wpdb->get_row($sql);
    }

    public function available_table_count($date, $time, $guest_count, $location = 'vn') {
        global $wpdb;
        $t = $wpdb->prefix . 'rb_tables';
        $b = $wpdb->prefix . 'rb_bookings';

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $location = RB_I18n::sanitize_location($location);

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$t} x
             WHERE x.is_available = 1
              AND x.location = %s
              AND x.capacity >= %d
              AND x.table_number NOT IN (
                SELECT y.table_number
                FROM {$b} y
                WHERE y.booking_date = %s
                  AND y.booking_time = %s
                  AND y.location = %s
                  AND y.status IN ('confirmed', 'pending', 'checked_in')
                  AND y.table_number IS NOT NULL
              )",
            $location,
            (int) $guest_count,
            $date,
            $time,
            $location
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Mark a booking as checked-in for the timeline dashboard.
     */
    public function check_in_booking($booking_id) {
        $booking = $this->get_booking($booking_id);

        if (!$booking) {
            return new WP_Error('rb_booking_not_found', __('Booking not found', 'restaurant-booking'));
        }

        if (in_array($booking->status, array('cancelled', 'completed'), true)) {
            return new WP_Error('rb_booking_invalid_status', __('Cannot check in this booking', 'restaurant-booking'));
        }

        $data = array('status' => 'checked_in');

        if (empty($booking->confirmed_at)) {
            $data['confirmed_at'] = current_time('mysql');
        }

        $updated = $this->update_booking($booking_id, $data);

        if (!$updated) {
            return new WP_Error('rb_booking_update_failed', __('Failed to update booking', 'restaurant-booking'));
        }

        return true;
    }

    /**
     * Mark a booking as completed / checked-out from timeline.
     */
    public function check_out_booking($booking_id) {
        $booking = $this->get_booking($booking_id);

        if (!$booking) {
            return new WP_Error('rb_booking_not_found', __('Booking not found', 'restaurant-booking'));
        }

        if ($booking->status === 'cancelled') {
            return new WP_Error('rb_booking_invalid_status', __('Cannot complete a cancelled booking', 'restaurant-booking'));
        }

        $updated = $this->update_booking($booking_id, array('status' => 'completed'));

        if (!$updated) {
            return new WP_Error('rb_booking_update_failed', __('Failed to update booking', 'restaurant-booking'));
        }

        if (class_exists('RB_Customer')) {
            global $rb_customer;
            if ($rb_customer) {
                $rb_customer->mark_completed($booking_id);
            }
        }

        return true;
    }

    /**
     * Move booking to another slot / table via timeline drag and drop.
     */
    public function move_booking($booking_id, $table_number, $date, $time, $location) {
        $booking = $this->get_booking($booking_id);

        if (!$booking) {
            return new WP_Error('rb_booking_not_found', __('Booking not found', 'restaurant-booking'));
        }

        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $location = RB_I18n::sanitize_location($location);

        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return new WP_Error('rb_booking_invalid_date', __('Invalid booking date', 'restaurant-booking'));
        }

        $time_obj = DateTime::createFromFormat('H:i', $time);
        if (!$time_obj) {
            return new WP_Error('rb_booking_invalid_time', __('Invalid booking time', 'restaurant-booking'));
        }

        $is_available = $this->is_time_slot_available($date, $time, (int) $booking->guest_count, $location, $booking_id);
        if (!$is_available) {
            return new WP_Error('rb_booking_unavailable', __('Selected slot is no longer available', 'restaurant-booking'));
        }

        $table_name = $this->wpdb->prefix . 'rb_bookings';
        $result = $this->wpdb->update(
            $table_name,
            array(
                'booking_date' => $date,
                'booking_time' => $time_obj->format('H:i'),
                'table_number' => $table_number,
                'location' => $location,
            ),
            array('id' => $booking_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('rb_booking_update_failed', __('Failed to update booking', 'restaurant-booking'));
        }

        return true;
    }

    /**
     * Build data payload for the admin timeline interface.
     */
    public function get_timeline_payload($date, $location) {
        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $location = RB_I18n::sanitize_location($location);

        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return new WP_Error('rb_booking_invalid_date', __('Invalid booking date', 'restaurant-booking'));
        }

        $settings = get_option('rb_settings', array());
        $interval = isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30;
        $interval = $interval > 0 ? $interval : 30;
        $block_duration = isset($settings['timeline_block_duration']) ? intval($settings['timeline_block_duration']) : 90;
        $block_duration = $block_duration > 0 ? $block_duration : $interval;

        $time_slots = $this->generate_timeline_slots($settings);

        $tables_table = $this->wpdb->prefix . 'rb_tables';
        $bookings_table = $this->wpdb->prefix . 'rb_bookings';

        $tables = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, table_number, capacity, is_available FROM {$tables_table} WHERE location = %s ORDER BY table_number ASC",
            $location
        ));

        $bookings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, customer_name, guest_count, booking_time, status, table_number, special_requests, booking_source, admin_notes"
            . " FROM {$bookings_table}"
            . " WHERE booking_date = %s AND location = %s ORDER BY booking_time ASC",
            $date,
            $location
        ));

        $table_payload = array();
        foreach ($tables as $table) {
            $table_payload[] = array(
                'id' => (int) $table->id,
                'table_number' => (int) $table->table_number,
                'capacity' => (int) $table->capacity,
                'is_available' => (int) $table->is_available,
            );
        }

        $booking_payload = array();
        foreach ($bookings as $booking) {
            $booking_payload[] = $this->format_timeline_booking($booking, $block_duration);
        }

        $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $language = RB_I18n::get_language_from_locale($locale);

        $status_labels = array(
            'pending' => __('Pending', 'restaurant-booking'),
            'confirmed' => __('Confirmed', 'restaurant-booking'),
            'checked_in' => __('Checked-in', 'restaurant-booking'),
            'completed' => __('Completed', 'restaurant-booking'),
            'cancelled' => __('Cancelled', 'restaurant-booking'),
            'no-show' => __('No show', 'restaurant-booking'),
        );

        $generated_at = current_time('mysql');
        $timezone_string = function_exists('wp_timezone_string') ? wp_timezone_string() : get_option('timezone_string');
        if (empty($timezone_string)) {
            $timezone_string = date_default_timezone_get();
        }

        return array(
            'date' => $date_obj->format('Y-m-d'),
            'location' => $location,
            'location_label' => RB_I18n::get_location_label($location, $language),
            'timeSlots' => $time_slots,
            'interval' => $interval,
            'blockDuration' => $block_duration,
            'tables' => $table_payload,
            'bookings' => $booking_payload,
            'statusLabels' => $status_labels,
            'counts' => array(
                'tables' => count($table_payload),
                'bookings' => count($booking_payload),
            ),
            'meta' => array(
                'generated_at' => $generated_at,
                'timezone' => $timezone_string,
            ),
        );
    }

    /**
     * Format booking row for timeline payload.
     */
    private function format_timeline_booking($booking, $block_duration) {
        $start = DateTime::createFromFormat('H:i', $booking->booking_time);
        if (!$start) {
            $start = DateTime::createFromFormat('H:i', '00:00');
        }

        $end = clone $start;
        $end->modify('+' . intval($block_duration) . ' minutes');

        return array(
            'id' => (int) $booking->id,
            'customer_name' => $booking->customer_name,
            'guest_count' => (int) $booking->guest_count,
            'table_number' => $booking->table_number ? (int) $booking->table_number : null,
            'status' => $booking->status,
            'start' => $start ? $start->format('H:i') : $booking->booking_time,
            'end' => $end->format('H:i'),
            'special_requests' => $booking->special_requests,
            'booking_source' => $booking->booking_source,
            'admin_notes' => $booking->admin_notes,
        );
    }

    /**
     * Build time slots respecting plugin working hour settings.
     */
    private function generate_timeline_slots($settings) {
        $mode = isset($settings['working_hours_mode']) ? $settings['working_hours_mode'] : 'simple';
        $interval = isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30;
        $interval = $interval > 0 ? $interval : 30;
        $buffer = isset($settings['booking_buffer_time']) ? intval($settings['booking_buffer_time']) : 0;

        $slots = array();

        if ($mode === 'advanced') {
            $morning_start = isset($settings['morning_shift_start']) ? $settings['morning_shift_start'] : '09:00';
            $morning_end = isset($settings['morning_shift_end']) ? $settings['morning_shift_end'] : '14:00';
            $evening_start = isset($settings['evening_shift_start']) ? $settings['evening_shift_start'] : '17:00';
            $evening_end = isset($settings['evening_shift_end']) ? $settings['evening_shift_end'] : '22:00';

            $slots = array_merge(
                $slots,
                $this->generate_timeline_shift_slots($morning_start, $morning_end, $interval, $buffer),
                $this->generate_timeline_shift_slots($evening_start, $evening_end, $interval, $buffer)
            );
        } else {
            $opening_time = isset($settings['opening_time']) ? $settings['opening_time'] : '09:00';
            $closing_time = isset($settings['closing_time']) ? $settings['closing_time'] : '22:00';

            if (isset($settings['lunch_break_enabled']) && $settings['lunch_break_enabled'] === 'yes') {
                $lunch_start = isset($settings['lunch_break_start']) ? $settings['lunch_break_start'] : '14:00';
                $lunch_end = isset($settings['lunch_break_end']) ? $settings['lunch_break_end'] : '17:00';

                $slots = array_merge(
                    $this->generate_timeline_shift_slots($opening_time, $lunch_start, $interval, $buffer),
                    $this->generate_timeline_shift_slots($lunch_end, $closing_time, $interval, $buffer)
                );
            } else {
                $slots = $this->generate_timeline_shift_slots($opening_time, $closing_time, $interval, $buffer);
            }
        }

        $slots = array_unique($slots);
        sort($slots);

        return array_values($slots);
    }

    /**
     * Generate time slots for a single shift.
     */
    private function generate_timeline_shift_slots($start, $end, $interval, $buffer = 0) {
        $slots = array();
        $start_time = strtotime($start);
        $end_time = strtotime($end);

        if (!$start_time || !$end_time) {
            return $slots;
        }

        $step = ($interval + max(0, $buffer)) * 60;

        while ($start_time < $end_time) {
            $slots[] = date('H:i', $start_time);
            $start_time += $step;
        }

        return $slots;
    }
}