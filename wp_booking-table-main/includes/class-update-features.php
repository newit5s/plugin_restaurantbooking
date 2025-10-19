<?php
/**
 * Manage roadmap update features for Restaurant Booking.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Update_Features {
    const OPTION_KEY = 'rb_feature_update_statuses';
    const OPTION_UPDATED_AT = 'rb_feature_update_statuses_updated_at';
    const DATA_DIR = 'update-feature';
    const OPTION_NOTIFICATIONS = 'rb_feature_update_notifications';
    const OPTION_SUBSCRIBERS = 'rb_feature_update_subscribers';
    const OPTION_CHANGELOG = 'rb_feature_update_changelog';
    const CHANGELOG_LIMIT = 50;

    /**
     * Get all phase definitions without saved overrides applied.
     *
     * @return array
     */
    private static function get_phase_definitions() {
        $data = array();
        $pattern = trailingslashit(RB_PLUGIN_DIR . self::DATA_DIR) . 'phase-*.json';
        $files = glob($pattern);

        if (empty($files)) {
            return $data;
        }

        sort($files);

        foreach ($files as $file) {
            $phase = self::extract_phase_from_filename($file);
            if (!$phase) {
                continue;
            }

            $phase_data = self::parse_phase_file($file, $phase);
            if (!empty($phase_data)) {
                $data[$phase] = $phase_data;
            }
        }

        return $data;
    }

    /**
     * Extract phase identifier from filename.
     *
     * @param string $file
     * @return string
     */
    private static function extract_phase_from_filename($file) {
        $basename = basename($file, '.json');
        if (strpos($basename, 'phase-') !== 0) {
            return '';
        }

        $phase = substr($basename, strlen('phase-'));
        return sanitize_key($phase);
    }

    /**
     * Parse a single phase JSON file.
     *
     * @param string $file
     * @param string $phase
     * @return array
     */
    private static function parse_phase_file($file, $phase) {
        if (!file_exists($file)) {
            return array();
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return array();
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return array();
        }

        $features = array();
        if (isset($decoded['features']) && is_array($decoded['features'])) {
            foreach ($decoded['features'] as $feature) {
                if (!is_array($feature)) {
                    continue;
                }

                $feature_id = isset($feature['id']) ? sanitize_key($feature['id']) : '';
                if (empty($feature_id) && !empty($feature['name'])) {
                    $feature_id = sanitize_key($feature['name']);
                }

                if (empty($feature_id)) {
                    continue;
                }

                $default_status = isset($feature['default_status']) ? $feature['default_status'] : 'planned';

                $features[] = array(
                    'id' => $feature_id,
                    'name' => isset($feature['name']) ? wp_kses_post($feature['name']) : $feature_id,
                    'description' => isset($feature['description']) ? wp_kses_post($feature['description']) : '',
                    'benefit' => isset($feature['benefit']) ? wp_kses_post($feature['benefit']) : '',
                    'owner' => isset($feature['owner']) ? sanitize_text_field($feature['owner']) : '',
                    'success_metric' => isset($feature['success_metric']) ? sanitize_text_field($feature['success_metric']) : '',
                    'tags' => isset($feature['tags']) && is_array($feature['tags']) ? array_map('sanitize_text_field', $feature['tags']) : array(),
                    'default_status' => self::normalize_status($default_status, 'planned'),
                );
            }
        }

        return array(
            'phase' => $phase,
            'title' => isset($decoded['title']) ? sanitize_text_field($decoded['title']) : sprintf(__('Phase %s', 'restaurant-booking'), $phase),
            'summary' => isset($decoded['summary']) ? wp_kses_post($decoded['summary']) : '',
            'themes' => isset($decoded['themes']) && is_array($decoded['themes']) ? array_map('sanitize_text_field', $decoded['themes']) : array(),
            'features' => $features,
        );
    }

    /**
     * Return a map of feature data including saved overrides.
     *
     * @return array
     */
    public static function get_all_features() {
        $phases = self::get_phase_definitions();
        if (empty($phases)) {
            return array();
        }

        $statuses = self::get_saved_statuses();
        $labels = self::get_status_labels();

        foreach ($phases as $phase => &$phase_data) {
            foreach ($phase_data['features'] as &$feature) {
                $id = $feature['id'];
                $default_status = $feature['default_status'];
                $status = isset($statuses[$phase][$id]) ? self::normalize_status($statuses[$phase][$id], $default_status) : $default_status;
                $feature['status'] = $status;
                $feature['status_label'] = isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
            }
        }

        return $phases;
    }

    /**
     * Get available interest tokens (phases, themes, tags) for notifications.
     *
     * @return array
     */
    public static function get_available_interest_tokens() {
        $phases = self::get_phase_definitions();
        $tokens = array();

        foreach ($phases as $phase => $phase_data) {
            $phase_token = sanitize_key('phase-' . $phase);
            $tokens[$phase_token] = isset($phase_data['title']) ? $phase_data['title'] : $phase;

            if (!empty($phase_data['themes'])) {
                foreach ($phase_data['themes'] as $theme) {
                    $tokens[sanitize_key($theme)] = $theme;
                }
            }

            if (!empty($phase_data['features'])) {
                foreach ($phase_data['features'] as $feature) {
                    $tokens[sanitize_key($feature['id'])] = $feature['name'];

                    if (!empty($feature['tags'])) {
                        foreach ($feature['tags'] as $tag) {
                            $tokens[sanitize_key($tag)] = $tag;
                        }
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * Allowed status options.
     *
     * @return array
     */
    public static function get_status_labels() {
        return array(
            'planned' => __('Planned', 'restaurant-booking'),
            'in_progress' => __('In progress', 'restaurant-booking'),
            'completed' => __('Completed', 'restaurant-booking'),
        );
    }

    /**
     * Sanitize a status value.
     *
     * @param string $status
     * @param string $fallback
     * @return string
     */
    public static function normalize_status($status, $fallback = 'planned') {
        $status = sanitize_key($status);
        $allowed = array_keys(self::get_status_labels());

        if (in_array($status, $allowed, true)) {
            return $status;
        }

        return in_array($fallback, $allowed, true) ? $fallback : 'planned';
    }

    /**
     * Retrieve saved status overrides.
     *
     * @return array
     */
    private static function get_saved_statuses() {
        $saved = get_option(self::OPTION_KEY, array());
        return is_array($saved) ? $saved : array();
    }

    /**
     * Persist status overrides.
     *
     * @param array $statuses
     * @return void
     */
    private static function save_statuses($statuses) {
        update_option(self::OPTION_KEY, $statuses);
        update_option(self::OPTION_UPDATED_AT, current_time('mysql'));
    }

    /**
     * Save a collection of feature statuses submitted from the admin screen.
     *
     * @param array $submitted
     * @return bool True when the stored values changed.
     */
    public static function bulk_update_statuses($submitted) {
        if (!is_array($submitted)) {
            return false;
        }

        $phases = self::get_phase_definitions();
        if (empty($phases)) {
            return false;
        }

        $clean = array();
        $changes = array();
        $current_statuses = self::get_saved_statuses();

        foreach ($phases as $phase => $phase_data) {
            if (empty($phase_data['features'])) {
                continue;
            }

            foreach ($phase_data['features'] as $feature) {
                $id = $feature['id'];
                $default_status = $feature['default_status'];
                $submitted_status = isset($submitted[$phase][$id]) ? $submitted[$phase][$id] : $default_status;
                $normalized = self::normalize_status($submitted_status, $default_status);

                $current_status = $default_status;
                if (isset($current_statuses[$phase][$id])) {
                    $current_status = self::normalize_status($current_statuses[$phase][$id], $default_status);
                }

                if ($normalized !== $current_status) {
                    $changes[] = self::build_change_payload($phase, $phase_data, $feature, $current_status, $normalized);
                }

                if ($normalized !== $default_status) {
                    if (!isset($clean[$phase])) {
                        $clean[$phase] = array();
                    }
                    $clean[$phase][$id] = $normalized;
                }
            }
        }

        $current = self::get_saved_statuses();
        if ($current === $clean && empty($changes)) {
            return false;
        }

        self::save_statuses($clean);
        if (!empty($changes)) {
            self::handle_post_update_actions($changes);
        }
        return true;
    }

    /**
     * Get last time statuses were updated.
     *
     * @return string
     */
    public static function get_last_updated_at() {
        $value = get_option(self::OPTION_UPDATED_AT, '');
        return is_string($value) ? $value : '';
    }

    /**
     * Get stored notification settings.
     *
     * @return array
     */
    public static function get_notification_settings() {
        $defaults = array(
            'enabled' => true,
            'notify_statuses' => array('in_progress', 'completed'),
        );

        $stored = get_option(self::OPTION_NOTIFICATIONS, array());
        if (!is_array($stored)) {
            $stored = array();
        }

        $enabled = isset($stored['enabled']) ? (bool) $stored['enabled'] : $defaults['enabled'];
        $notify_statuses = array();
        if (isset($stored['notify_statuses']) && is_array($stored['notify_statuses'])) {
            foreach ($stored['notify_statuses'] as $status) {
                $normalized = self::normalize_status($status, 'planned');
                if (!in_array($normalized, $notify_statuses, true)) {
                    $notify_statuses[] = $normalized;
                }
            }
        }

        if (empty($notify_statuses)) {
            $notify_statuses = $defaults['notify_statuses'];
        }

        return array(
            'enabled' => $enabled,
            'notify_statuses' => $notify_statuses,
        );
    }

    /**
     * Persist notification settings.
     *
     * @param array $settings
     * @return bool
     */
    public static function save_notification_settings($settings) {
        if (!is_array($settings)) {
            $settings = array();
        }

        $clean = array();
        $clean['enabled'] = isset($settings['enabled']) && (int) $settings['enabled'] === 1;

        $clean['notify_statuses'] = array();
        if (isset($settings['notify_statuses']) && is_array($settings['notify_statuses'])) {
            foreach ($settings['notify_statuses'] as $status) {
                $normalized = self::normalize_status($status, 'planned');
                if (!in_array($normalized, $clean['notify_statuses'], true)) {
                    $clean['notify_statuses'][] = $normalized;
                }
            }
        }

        if (empty($clean['notify_statuses'])) {
            $defaults = self::get_notification_settings();
            $clean['notify_statuses'] = $defaults['notify_statuses'];
        }

        $current = self::get_notification_settings();
        if ($current === $clean) {
            return false;
        }

        update_option(self::OPTION_NOTIFICATIONS, $clean);
        return true;
    }

    /**
     * Get subscriber preferences.
     *
     * @return array
     */
    public static function get_subscribers() {
        $stored = get_option(self::OPTION_SUBSCRIBERS, array());
        if (!is_array($stored)) {
            return array();
        }

        $subscribers = array();
        foreach ($stored as $subscriber) {
            if (!is_array($subscriber) || empty($subscriber['email'])) {
                continue;
            }

            $email = sanitize_email($subscriber['email']);
            if (empty($email)) {
                continue;
            }

            $preferences = array();
            if (isset($subscriber['preferences']) && is_array($subscriber['preferences'])) {
                foreach ($subscriber['preferences'] as $preference) {
                    $key = sanitize_key($preference);
                    if (!empty($key) && !in_array($key, $preferences, true)) {
                        $preferences[] = $key;
                    }
                }
            }

            $subscribers[] = array(
                'email' => $email,
                'preferences' => $preferences,
            );
        }

        return $subscribers;
    }

    /**
     * Get subscriber preferences as editable text.
     *
     * @return string
     */
    public static function get_subscribers_text() {
        $subscribers = self::get_subscribers();
        if (empty($subscribers)) {
            return '';
        }

        $lines = array();
        foreach ($subscribers as $subscriber) {
            $line = $subscriber['email'];
            if (!empty($subscriber['preferences'])) {
                $line .= '|' . implode(', ', $subscriber['preferences']);
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Save subscriber preferences provided as text.
     *
     * @param string $text
     * @return bool
     */
    public static function save_subscriber_preferences_from_text($text) {
        if (!is_string($text)) {
            $text = '';
        }

        $parsed = self::parse_subscriber_preferences($text);
        $current = get_option(self::OPTION_SUBSCRIBERS, array());

        if ($current === $parsed) {
            return false;
        }

        update_option(self::OPTION_SUBSCRIBERS, $parsed);
        return true;
    }

    /**
     * Parse subscriber preferences text into structured array.
     *
     * @param string $text
     * @return array
     */
    private static function parse_subscriber_preferences($text) {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $subscribers = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $email = sanitize_email($parts[0]);

            if (empty($email)) {
                continue;
            }

            $preferences = array();
            if (isset($parts[1]) && !empty($parts[1])) {
                $raw_preferences = preg_split('/\s*,\s*/', $parts[1]);
                foreach ($raw_preferences as $preference) {
                    $key = sanitize_key($preference);
                    if (!empty($key) && !in_array($key, $preferences, true)) {
                        $preferences[] = $key;
                    }
                }
            }

            $subscribers[] = array(
                'email' => $email,
                'preferences' => $preferences,
            );
        }

        return $subscribers;
    }

    /**
     * Retrieve stored changelog entries.
     *
     * @param int $limit
     * @return array
     */
    public static function get_changelog_entries($limit = 10) {
        $entries = get_option(self::OPTION_CHANGELOG, array());
        if (!is_array($entries)) {
            return array();
        }

        if (!is_int($limit) || $limit <= 0) {
            return $entries;
        }

        return array_slice($entries, 0, $limit);
    }

    /**
     * Build change payload used for changelog and notifications.
     *
     * @param string $phase
     * @param array  $phase_data
     * @param array  $feature
     * @param string $old_status
     * @param string $new_status
     * @return array
     */
    private static function build_change_payload($phase, $phase_data, $feature, $old_status, $new_status) {
        $phase_title = isset($phase_data['title']) ? $phase_data['title'] : $phase;
        $themes = isset($phase_data['themes']) && is_array($phase_data['themes']) ? array_map('sanitize_text_field', $phase_data['themes']) : array();
        $tags = isset($feature['tags']) && is_array($feature['tags']) ? array_map('sanitize_text_field', $feature['tags']) : array();

        $feature_id = sanitize_key($feature['id']);

        return array(
            'phase' => sanitize_key($phase),
            'phase_title' => sanitize_text_field($phase_title),
            'feature_id' => $feature_id,
            'feature_name' => sanitize_text_field(wp_strip_all_tags($feature['name'])),
            'old_status' => $old_status,
            'new_status' => $new_status,
            'tags' => $tags,
            'themes' => $themes,
            'description' => isset($feature['description']) ? wp_kses_post($feature['description']) : '',
            'benefit' => isset($feature['benefit']) ? wp_kses_post($feature['benefit']) : '',
            'interest_keys' => self::build_interest_keys($phase, $themes, $tags, $feature_id, $old_status, $new_status),
        );
    }

    /**
     * Build interest keys for matching subscriber preferences.
     *
     * @param string $phase
     * @param array  $themes
     * @param array  $tags
     * @param string $feature_id
     * @param string $old_status
     * @param string $new_status
     * @return array
     */
    private static function build_interest_keys($phase, $themes, $tags, $feature_id, $old_status, $new_status) {
        $keys = array();

        $keys[] = sanitize_key($phase);
        $keys[] = sanitize_key('phase-' . $phase);
        if (!empty($feature_id)) {
            $keys[] = sanitize_key($feature_id);
        }

        foreach ($themes as $theme) {
            $keys[] = sanitize_key($theme);
        }

        foreach ($tags as $tag) {
            $keys[] = sanitize_key($tag);
        }

        if (!empty($old_status)) {
            $keys[] = sanitize_key($old_status);
        }

        if (!empty($new_status)) {
            $keys[] = sanitize_key($new_status);
        }

        $keys = array_filter($keys);

        return array_values(array_unique($keys));
    }

    /**
     * Handle changelog persistence and notifications after status updates.
     *
     * @param array $changes
     * @return void
     */
    private static function handle_post_update_actions($changes) {
        if (empty($changes)) {
            return;
        }

        $labels = self::get_status_labels();
        foreach ($changes as &$change) {
            $change['summary'] = self::generate_change_summary($change, $labels);
        }
        unset($change);

        self::persist_changelog_entries($changes);
        self::send_notifications($changes);
    }

    /**
     * Generate a localized summary for a change entry.
     *
     * @param array $change
     * @param array $labels
     * @return string
     */
    private static function generate_change_summary($change, $labels) {
        $old_label = isset($labels[$change['old_status']]) ? $labels[$change['old_status']] : ucfirst($change['old_status']);
        $new_label = isset($labels[$change['new_status']]) ? $labels[$change['new_status']] : ucfirst($change['new_status']);

        return sprintf(
            __('Chuyển trạng thái từ %1$s sang %2$s.', 'restaurant-booking'),
            $old_label,
            $new_label
        );
    }

    /**
     * Persist changelog entries to the database.
     *
     * @param array $changes
     * @return void
     */
    private static function persist_changelog_entries($changes) {
        $existing = get_option(self::OPTION_CHANGELOG, array());
        if (!is_array($existing)) {
            $existing = array();
        }

        $timestamp = current_time('mysql');
        foreach ($changes as $change) {
            $entry = array(
                'timestamp' => $timestamp,
                'phase' => $change['phase'],
                'phase_title' => $change['phase_title'],
                'feature_id' => $change['feature_id'],
                'feature_name' => $change['feature_name'],
                'summary' => $change['summary'],
                'old_status' => $change['old_status'],
                'new_status' => $change['new_status'],
                'tags' => array_map('sanitize_text_field', (array) $change['tags']),
                'themes' => array_map('sanitize_text_field', (array) $change['themes']),
            );

            array_unshift($existing, $entry);
        }

        if (count($existing) > self::CHANGELOG_LIMIT) {
            $existing = array_slice($existing, 0, self::CHANGELOG_LIMIT);
        }

        update_option(self::OPTION_CHANGELOG, $existing);
    }

    /**
     * Send notifications to subscribers for relevant changes.
     *
     * @param array $changes
     * @return void
     */
    private static function send_notifications($changes) {
        $settings = self::get_notification_settings();
        if (empty($settings['enabled'])) {
            return;
        }

        $statuses_to_notify = isset($settings['notify_statuses']) ? $settings['notify_statuses'] : array();
        $filtered_changes = array();
        foreach ($changes as $change) {
            if (!empty($statuses_to_notify) && !in_array($change['new_status'], $statuses_to_notify, true)) {
                continue;
            }
            $filtered_changes[] = $change;
        }

        if (empty($filtered_changes)) {
            return;
        }

        $subscribers = self::get_subscribers();

        if (empty($subscribers)) {
            $admin_email = sanitize_email(get_option('admin_email'));
            if (!empty($admin_email)) {
                $subscribers[] = array(
                    'email' => $admin_email,
                    'preferences' => array(),
                );
            }
        }

        if (empty($subscribers)) {
            return;
        }

        foreach ($subscribers as $subscriber) {
            $recipient_changes = array();

            foreach ($filtered_changes as $change) {
                if (self::subscriber_interested_in_change($subscriber, $change)) {
                    $recipient_changes[] = $change;
                }
            }

            if (empty($recipient_changes)) {
                continue;
            }

            $body = self::build_email_body($recipient_changes);
            $subject = sprintf(
                __('[%s] Cập nhật roadmap tính năng', 'restaurant-booking'),
                get_bloginfo('name')
            );

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($subscriber['email'], $subject, $body, $headers);
        }
    }

    /**
     * Determine if a subscriber should receive a change notification.
     *
     * @param array $subscriber
     * @param array $change
     * @return bool
     */
    private static function subscriber_interested_in_change($subscriber, $change) {
        if (empty($subscriber['preferences'])) {
            return true;
        }

        $keys = isset($change['interest_keys']) ? $change['interest_keys'] : array();
        if (empty($keys)) {
            return true;
        }

        foreach ($subscriber['preferences'] as $preference) {
            if (in_array($preference, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build email body for change notifications.
     *
     * @param array $changes
     * @param array $labels
     * @return string
     */
    private static function build_email_body($changes) {
        $site_name = get_bloginfo('name');
        $intro = sprintf(
            __('Xin chào, %s vừa có cập nhật roadmap mới:', 'restaurant-booking'),
            esc_html($site_name)
        );

        $lines = array();
        foreach ($changes as $change) {
            $meta = array();
            if (!empty($change['phase_title'])) {
                $meta[] = sprintf(__('Phase: %s', 'restaurant-booking'), $change['phase_title']);
            }

            if (!empty($change['tags'])) {
                $meta[] = sprintf(__('Tags: %s', 'restaurant-booking'), implode(', ', $change['tags']));
            }

            $meta_display = !empty($meta) ? implode(' | ', $meta) : __('Không có thông tin bổ sung', 'restaurant-booking');

            $lines[] = sprintf(
                '<li><strong>%1$s</strong> – %2$s<br /><em>%3$s</em></li>',
                esc_html($change['feature_name']),
                esc_html($change['summary']),
                esc_html($meta_display)
            );
        }

        $footer = __('Bạn nhận được email này vì đã đăng ký theo dõi roadmap. Cập nhật tuỳ chọn trong trang quản trị Restaurant Booking.', 'restaurant-booking');

        return wp_kses_post('<p>' . esc_html($intro) . '</p><ul>' . implode('', $lines) . '</ul><p>' . esc_html($footer) . '</p>');
    }
}

