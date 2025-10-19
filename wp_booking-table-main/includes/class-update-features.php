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

        foreach ($phases as $phase => $phase_data) {
            if (empty($phase_data['features'])) {
                continue;
            }

            foreach ($phase_data['features'] as $feature) {
                $id = $feature['id'];
                $default_status = $feature['default_status'];
                $submitted_status = isset($submitted[$phase][$id]) ? $submitted[$phase][$id] : $default_status;
                $normalized = self::normalize_status($submitted_status, $default_status);

                if ($normalized !== $default_status) {
                    if (!isset($clean[$phase])) {
                        $clean[$phase] = array();
                    }
                    $clean[$phase][$id] = $normalized;
                }
            }
        }

        $current = self::get_saved_statuses();
        if ($current === $clean) {
            return false;
        }

        self::save_statuses($clean);
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
}

