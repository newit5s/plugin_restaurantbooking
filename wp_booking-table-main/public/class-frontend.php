<?php
/**
 * Frontend Class - Xử lý hiển thị frontend và shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Frontend {
    
    public function __construct() {
        $this->init_ajax_handlers();
    }
    
    private function init_ajax_handlers() {
        add_action('wp_ajax_rb_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_rb_submit_booking', array($this, 'handle_booking_submission'));
        
        add_action('wp_ajax_rb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_rb_check_availability', array($this, 'check_availability'));
    }
    
    public function render_booking_form($atts) {
        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $settings = get_option('rb_settings', array(
            'opening_time' => '09:00',
            'closing_time' => '22:00',
            'time_slot_interval' => 30,
            'frontend_language' => 'vi',
        ));

        $default_language = isset($settings['frontend_language']) ? RB_I18n::sanitize_language($settings['frontend_language']) : 'vi';
        $translations = RB_I18n::get_section_translations('frontend', $default_language);
        $languages = RB_I18n::get_languages();
        $locations = RB_I18n::get_locations();
        $default_location = isset($settings['default_location']) ? RB_I18n::sanitize_location($settings['default_location']) : 'vn';

        $atts = shortcode_atts(array(
            'title' => $translations['modal_title'],
            'button_text' => $translations['button_text'],
            'show_button' => 'yes'
        ), $atts, 'restaurant_booking');

        static $instance = 0;
        $instance++;
        $widget_id = 'rb-widget-' . $instance;

        $opening_time = isset($settings['opening_time']) ? $settings['opening_time'] : '09:00';
        $closing_time = isset($settings['closing_time']) ? $settings['closing_time'] : '22:00';
        $time_interval = isset($settings['time_slot_interval']) ? intval($settings['time_slot_interval']) : 30;

        $time_slots = $this->generate_time_slots($opening_time, $closing_time, $time_interval);

        ob_start();
        ?>
        <div class="rb-booking-widget" data-rb-widget="<?php echo esc_attr($widget_id); ?>" data-default-language="<?php echo esc_attr($default_language); ?>" data-default-location="<?php echo esc_attr($default_location); ?>">
            <?php if ($atts['show_button'] === 'yes') : ?>
                <button type="button" class="rb-open-modal-btn" data-lang-key="button_text" data-lang-attr="text">
                    <?php echo esc_html($translations['button_text']); ?>
                </button>
            <?php endif; ?>

            <div id="rb-booking-modal" class="rb-modal">
                <div class="rb-modal-content">
                    <span class="rb-close" aria-label="Close">&times;</span>

                    <div class="rb-preferences">
                        <div class="rb-location-selector" data-lang-scope>
                            <h3 data-lang-key="location_selection_title" data-lang-attr="text"><?php echo esc_html($translations['location_selection_title']); ?></h3>
                            <p data-lang-key="location_selection_description" data-lang-attr="text"><?php echo esc_html($translations['location_selection_description']); ?></p>
                            <div class="rb-location-options">
                                <?php foreach ($locations as $code => $location) : ?>
                                    <?php
                                    $translation_key = 'location_option_' . $code;
                                    $label = isset($translations[$translation_key]) ? $translations[$translation_key] : $location['labels']['vi'];
                                    ?>
                                    <button type="button" class="rb-location-option<?php echo $code === $default_location ? ' active' : ''; ?>" data-location="<?php echo esc_attr($code); ?>" data-lang-key="<?php echo esc_attr($translation_key); ?>" data-lang-attr="text">
                                        <?php echo esc_html($label); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rb-language-selector" data-lang-scope>
                            <h3 data-lang-key="language_selection_title" data-lang-attr="text"><?php echo esc_html($translations['language_selection_title']); ?></h3>
                            <p data-lang-key="language_selection_description" data-lang-attr="text"><?php echo esc_html($translations['language_selection_description']); ?></p>
                            <div class="rb-language-options">
                                <?php foreach ($languages as $code => $language) : ?>
                                    <button type="button" class="rb-language-option<?php echo $code === $default_language ? ' active' : ''; ?>" data-lang="<?php echo esc_attr($code); ?>">
                                        <?php echo esc_html($language['flag'] . ' ' . $language['native']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <h2 data-lang-key="modal_title" data-lang-attr="text"><?php echo esc_html($translations['modal_title']); ?></h2>

                    <form id="rb-booking-form" class="rb-form" data-default-language="<?php echo esc_attr($default_language); ?>">
                        <?php wp_nonce_field('rb_booking_nonce', 'rb_nonce'); ?>
                        <input type="hidden" name="booking_language" id="rb_booking_language" value="<?php echo esc_attr($default_language); ?>">
                        <input type="hidden" name="booking_location" id="rb_booking_location" value="<?php echo esc_attr($default_location); ?>">

                        <div class="rb-section rb-section-schedule">
                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="rb_guest_count" data-lang-key="guest_count_label" data-lang-attr="text"><?php echo esc_html($translations['guest_count_label']); ?></label>
                                    <select id="rb_guest_count" name="guest_count" class="rb-schedule-field" required>
                                        <?php for ($i = 1; $i <= 20; $i++) : ?>
                                            <option value="<?php echo $i; ?>" data-lang-key="guest_option" data-lang-attr="text" data-count="<?php echo $i; ?>"><?php echo esc_html(sprintf($translations['guest_option'], $i)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="rb-form-group">
                                    <label for="rb_booking_date" data-lang-key="booking_date_label" data-lang-attr="text"><?php echo esc_html($translations['booking_date_label']); ?></label>
                                    <?php
                                    $max_days = isset($settings['max_advance_booking']) ? intval($settings['max_advance_booking']) : 30;
                                    $min_hours = isset($settings['min_advance_booking']) ? intval($settings['min_advance_booking']) : 2;
                                    $min_date = date('Y-m-d', strtotime("+{$min_hours} hours"));
                                    $max_date = date('Y-m-d', strtotime("+{$max_days} days"));
                                    ?>
                                    <input type="date" id="rb_booking_date" name="booking_date" class="rb-schedule-field"
                                           min="<?php echo $min_date; ?>"
                                           max="<?php echo $max_date; ?>" required>
                                </div>
                            </div>

                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="rb_booking_time" data-lang-key="booking_time_label" data-lang-attr="text"><?php echo esc_html($translations['booking_time_label']); ?></label>
                                    <select id="rb_booking_time" name="booking_time" class="rb-schedule-field" required>
                                        <option value="" data-lang-key="select_time_placeholder" data-lang-attr="text"><?php echo esc_html($translations['select_time_placeholder']); ?></option>
                                        <?php if (!empty($time_slots)) : ?>
                                            <?php foreach ($time_slots as $slot) : ?>
                                                <option value="<?php echo esc_attr($slot); ?>"><?php echo esc_html($slot); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="rb-form-group rb-availability-control">
                                    <label>&nbsp;</label>
                                    <button type="button" class="rb-btn-secondary rb-check-availability" data-lang-key="check_availability_button" data-lang-attr="text">
                                        <?php echo esc_html($translations['check_availability_button']); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="rb-availability-message" role="status"></div>
                        </div>

                        <div class="rb-section rb-section-details">
                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="rb_customer_name" data-lang-key="customer_name_label" data-lang-attr="text"><?php echo esc_html($translations['customer_name_label']); ?></label>
                                    <input type="text" id="rb_customer_name" name="customer_name" class="rb-contact-field" required disabled>
                                </div>

                                <div class="rb-form-group">
                                    <label for="rb_customer_phone" data-lang-key="customer_phone_label" data-lang-attr="text"><?php echo esc_html($translations['customer_phone_label']); ?></label>
                                    <input type="tel" id="rb_customer_phone" name="customer_phone" class="rb-contact-field" required disabled>
                                </div>
                            </div>

                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="rb_customer_email" data-lang-key="customer_email_label" data-lang-attr="text"><?php echo esc_html($translations['customer_email_label']); ?></label>
                                    <input type="email" id="rb_customer_email" name="customer_email" class="rb-contact-field" required disabled>
                                </div>
                            </div>

                            <div class="rb-form-group">
                                <label for="rb_special_requests" data-lang-key="special_requests_label" data-lang-attr="text"><?php echo esc_html($translations['special_requests_label']); ?></label>
                                <textarea id="rb_special_requests" name="special_requests" rows="3" class="rb-contact-field" disabled></textarea>
                            </div>
                        </div>

                        <div class="rb-form-actions">
                            <button type="submit" class="rb-btn-primary" data-lang-key="submit_button" data-lang-attr="text" disabled><?php echo esc_html($translations['submit_button']); ?></button>
                            <button type="button" class="rb-btn-cancel rb-close-modal" data-lang-key="cancel_button" data-lang-attr="text"><?php echo esc_html($translations['cancel_button']); ?></button>
                        </div>

                        <div id="rb-form-message" class="rb-form-message"></div>
                    </form>
                </div>
            </div>

            <?php if ($atts['show_button'] === 'no') : ?>
                <div class="rb-inline-form" data-lang-scope>
                    <div class="rb-preferences">
                        <div class="rb-location-selector" data-lang-scope>
                            <h3 data-lang-key="location_selection_title" data-lang-attr="text"><?php echo esc_html($translations['location_selection_title']); ?></h3>
                            <p data-lang-key="location_selection_description" data-lang-attr="text"><?php echo esc_html($translations['location_selection_description']); ?></p>
                            <div class="rb-location-options">
                                <?php foreach ($locations as $code => $location) : ?>
                                    <?php
                                    $translation_key = 'location_option_' . $code;
                                    $label = isset($translations[$translation_key]) ? $translations[$translation_key] : $location['labels']['vi'];
                                    ?>
                                    <button type="button" class="rb-location-option<?php echo $code === $default_location ? ' active' : ''; ?>" data-location="<?php echo esc_attr($code); ?>" data-lang-key="<?php echo esc_attr($translation_key); ?>" data-lang-attr="text">
                                        <?php echo esc_html($label); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rb-language-selector" data-lang-scope>
                            <h3 data-lang-key="language_selection_title" data-lang-attr="text"><?php echo esc_html($translations['language_selection_title']); ?></h3>
                            <p data-lang-key="language_selection_description" data-lang-attr="text"><?php echo esc_html($translations['language_selection_description']); ?></p>
                            <div class="rb-language-options">
                                <?php foreach ($languages as $code => $language) : ?>
                                    <button type="button" class="rb-language-option<?php echo $code === $default_language ? ' active' : ''; ?>" data-lang="<?php echo esc_attr($code); ?>">
                                        <?php echo esc_html($language['flag'] . ' ' . $language['native']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <h3 class="rb-inline-form-title" data-lang-key="inline_title" data-lang-attr="text"><?php echo esc_html($translations['inline_title']); ?></h3>
                    <form id="rb-booking-form-inline" class="rb-form" data-default-language="<?php echo esc_attr($default_language); ?>">
                        <?php wp_nonce_field('rb_booking_nonce', 'rb_nonce_inline'); ?>
                        <input type="hidden" name="booking_language" id="rb_booking_language_inline" value="<?php echo esc_attr($default_language); ?>">
                        <input type="hidden" name="booking_location" id="rb_booking_location_inline" value="<?php echo esc_attr($default_location); ?>">

                        <div class="rb-section rb-section-schedule">
                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="rb_guests_inline" data-lang-key="guest_count_label" data-lang-attr="text"><?php echo esc_html($translations['guest_count_label']); ?></label>
                                    <select id="rb_guests_inline" name="guest_count" class="rb-schedule-field" required>
                                        <?php for ($i = 1; $i <= 20; $i++) : ?>
                                            <option value="<?php echo $i; ?>" data-lang-key="guest_option" data-lang-attr="text" data-count="<?php echo $i; ?>"><?php echo esc_html(sprintf($translations['guest_option'], $i)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="rb-form-group">
                                    <label for="rb_date_inline" data-lang-key="inline_date_label" data-lang-attr="text"><?php echo esc_html($translations['inline_date_label']); ?></label>
                                    <input type="date" id="rb_date_inline" name="booking_date" class="rb-schedule-field"
                                           min="<?php echo $min_date; ?>"
                                           max="<?php echo $max_date; ?>" required>
                                </div>
                            </div>

                            <div class="rb-form-row">
                                <div class="rb-form-group">
                                    <label for="rb_time_inline" data-lang-key="inline_time_label" data-lang-attr="text"><?php echo esc_html($translations['inline_time_label']); ?></label>
                                    <select id="rb_time_inline" name="booking_time" class="rb-schedule-field" required>
                                        <option value="" data-lang-key="select_time_placeholder" data-lang-attr="text"><?php echo esc_html($translations['select_time_placeholder']); ?></option>
                                        <?php if (!empty($time_slots)) : ?>
                                            <?php foreach ($time_slots as $slot) : ?>
                                                <option value="<?php echo esc_attr($slot); ?>"><?php echo esc_html($slot); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="rb-form-group rb-availability-control">
                                    <label>&nbsp;</label>
                                    <button type="button" class="rb-btn-secondary rb-check-availability" data-lang-key="check_availability_button" data-lang-attr="text">
                                        <?php echo esc_html($translations['check_availability_button']); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="rb-availability-message" role="status"></div>
                        </div>

                        <div class="rb-section rb-section-details">
                            <div class="rb-form-grid">
                                <div class="rb-form-group">
                                    <label for="rb_name_inline" data-lang-key="customer_name_label" data-lang-attr="text"><?php echo esc_html($translations['customer_name_label']); ?></label>
                                    <input type="text" id="rb_name_inline" name="customer_name" class="rb-contact-field" required disabled>
                                </div>

                                <div class="rb-form-group">
                                    <label for="rb_phone_inline" data-lang-key="customer_phone_label" data-lang-attr="text"><?php echo esc_html($translations['customer_phone_label']); ?></label>
                                    <input type="tel" id="rb_phone_inline" name="customer_phone" class="rb-contact-field" required disabled>
                                </div>

                                <div class="rb-form-group">
                                    <label for="rb_email_inline" data-lang-key="customer_email_label" data-lang-attr="text"><?php echo esc_html($translations['customer_email_label']); ?></label>
                                    <input type="email" id="rb_email_inline" name="customer_email" class="rb-contact-field" required disabled>
                                </div>
                            </div>

                            <div class="rb-form-group">
                                <label for="rb_requests_inline" data-lang-key="special_requests_label" data-lang-attr="text"><?php echo esc_html($translations['special_requests_label']); ?></label>
                                <textarea id="rb_requests_inline" name="special_requests" rows="3" class="rb-contact-field" disabled></textarea>
                            </div>
                        </div>

                        <div class="rb-form-actions">
                            <button type="submit" class="rb-btn-primary" data-lang-key="inline_submit_button" data-lang-attr="text" disabled><?php echo esc_html($translations['inline_submit_button']); ?></button>
                        </div>

                        <div id="rb-form-message-inline" class="rb-form-message"></div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
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
 * Generate slots for one shift
 */
    private function generate_shift_slots($start, $end, $interval, $buffer = 0) {
        $slots = array();
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $step = ($interval + $buffer) * 60;
        
        while ($start_time < $end_time) {
            $slots[] = date('H:i', $start_time);
            $start_time += $step;
        }
        
        return $slots;
    }
  /**
 * Check if booking allowed on date
 */
    private function is_booking_allowed_on_date($date) {
        $settings = get_option('rb_settings', array());
        
        // Check special closed dates
        $closed_dates = isset($settings['special_closed_dates']) ? $settings['special_closed_dates'] : '';
        if (!empty($closed_dates)) {
            $dates_array = array_map('trim', explode("\n", $closed_dates));
            if (in_array($date, $dates_array)) {
                return false;
            }
        }
        
        // Check weekend
        $weekend_enabled = isset($settings['weekend_enabled']) && $settings['weekend_enabled'] === 'yes';
        $day_of_week = date('N', strtotime($date));
        
        if (!$weekend_enabled && ($day_of_week == 6 || $day_of_week == 7)) {
            return false;
        }
        
        // Check advance booking limits
        $min_advance = isset($settings['min_advance_booking']) ? intval($settings['min_advance_booking']) : 2;
        $max_advance = isset($settings['max_advance_booking']) ? intval($settings['max_advance_booking']) : 30;
        
        $booking_timestamp = strtotime($date);
        $now = current_time('timestamp');
        $min_timestamp = $now + ($min_advance * 3600);
        $max_timestamp = $now + ($max_advance * 86400);
        
        if ($booking_timestamp < $min_timestamp || $booking_timestamp > $max_timestamp) {
            return false;
        }
        
        return true;
    }  
    public function handle_booking_submission() {
        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $language = isset($_POST['booking_language']) ? RB_I18n::sanitize_language($_POST['booking_language']) : 'vi';
        $texts = RB_I18n::get_section_translations('frontend', $language);
        $location_input = isset($_POST['booking_location']) ? $_POST['booking_location'] : '';
        if (empty($location_input)) {
            wp_send_json_error(array('message' => $texts['location_required']));
            wp_die();
        }
        $location = RB_I18n::sanitize_location($location_input);

        $nonce = isset($_POST['rb_nonce']) ? $_POST['rb_nonce'] : (isset($_POST['rb_nonce_inline']) ? $_POST['rb_nonce_inline'] : '');
        if (!wp_verify_nonce($nonce, 'rb_booking_nonce')) {
            wp_send_json_error(array('message' => $texts['security_failed']));
            wp_die();
        }

        $required_fields = array('customer_name', 'customer_phone', 'customer_email', 'guest_count', 'booking_date', 'booking_time');

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => $texts['form_missing_fields']));
                wp_die();
            }
        }

        $booking_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'guest_count' => intval($_POST['guest_count']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'booking_time' => sanitize_text_field($_POST['booking_time']),
            'special_requests' => isset($_POST['special_requests']) ? sanitize_textarea_field($_POST['special_requests']) : '',
            'status' => 'pending',
            'booking_source' => 'website',
            'created_at' => current_time('mysql'),
            'language' => $language,
            'location' => $location
        );

        if (!is_email($booking_data['customer_email'])) {
            wp_send_json_error(array('message' => $texts['invalid_email']));
            wp_die();
        }

        if (!preg_match('/^[0-9]{6,15}$/', $booking_data['customer_phone'])) {
            wp_send_json_error(array('message' => $texts['invalid_phone']));
            wp_die();
        }

        $booking_date = strtotime($booking_data['booking_date']);
        $today = strtotime(date('Y-m-d'));

        if ($booking_date === false || $booking_date < $today) {
            wp_send_json_error(array('message' => $texts['invalid_date']));
            wp_die();
        }

        global $rb_booking;

        // Đảm bảo $rb_booking đã được khởi tạo
        if (!$rb_booking) {
            require_once RB_PLUGIN_DIR . 'includes/class-booking.php';
            $rb_booking = new RB_Booking();
        }

        $is_available = $rb_booking->is_time_slot_available(
            $booking_data['booking_date'],
            $booking_data['booking_time'],
            $booking_data['guest_count'],
            $location
        );

        if (!$is_available) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($booking_data['booking_date']));
            wp_send_json_error(array(
                'message' => sprintf(
                    $texts['no_availability_message'],
                    number_format_i18n($booking_data['guest_count']),
                    $booking_data['booking_time'],
                    $formatted_date
                )
            ));
            wp_die();
        }

        // *** THAY ĐỔI CHÍNH: Dùng create_booking() thay vì insert trực tiếp ***
        $booking_id = $rb_booking->create_booking($booking_data);

        if (is_wp_error($booking_id)) {
            wp_send_json_error(array('message' => $booking_id->get_error_message()));
            wp_die();
        }

        // Send admin notification
        $booking = $rb_booking->get_booking($booking_id);
        if ($booking && class_exists('RB_Email')) {
            $email = new RB_Email();
            $email->send_admin_notification($booking);
        }

        wp_send_json_success(array(
            'message' => $texts['success_message'],
            'booking_id' => $booking_id
        ));

        wp_die();
    }
    public function check_availability() {
        if (!class_exists('RB_I18n')) {
            require_once RB_PLUGIN_DIR . 'includes/class-i18n.php';
        }

        $language = isset($_POST['language']) ? RB_I18n::sanitize_language($_POST['language']) : 'vi';
        $texts = RB_I18n::get_section_translations('frontend', $language);

        if (!check_ajax_referer('rb_frontend_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => $texts['security_failed']));
            wp_die();
        }

        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $guests = intval($_POST['guests']);
        $location_input = isset($_POST['location']) ? $_POST['location'] : '';

        if (empty($location_input)) {
            wp_send_json_error(array('message' => $texts['location_required']));
            wp_die();
        }

        $location = RB_I18n::sanitize_location($location_input);

        global $rb_booking;
        $is_available = $rb_booking->is_time_slot_available($date, $time, $guests, $location);
        $count = $rb_booking->available_table_count($date, $time, $guests, $location);

        if ($is_available && $count > 0) {
            $message = sprintf($texts['availability_success'], number_format_i18n($count), number_format_i18n($guests));
            wp_send_json_success(array(
                'available' => true,
                'message' => $message,
                'count' => $count
            ));
        } else {
            wp_send_json_success(array(
                'available' => false,
                'message' => $texts['availability_fail']
            ));
        }

        wp_die();
    }
}