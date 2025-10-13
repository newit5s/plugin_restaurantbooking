<?php
/**
 * Internationalization helper for Restaurant Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_I18n {
    /**
     * Supported languages metadata.
     *
     * @return array
     */
    public static function get_languages() {
        return array(
            'vi' => array(
                'code' => 'vi',
                'label' => 'Vietnamese',
                'native' => 'Tiếng Việt',
                'flag' => '🇻🇳',
            ),
            'en' => array(
                'code' => 'en',
                'label' => 'English',
                'native' => 'English',
                'flag' => '🇬🇧',
            ),
            'ja' => array(
                'code' => 'ja',
                'label' => 'Japanese',
                'native' => '日本語',
                'flag' => '🇯🇵',
            ),
        );
    }

    /**
     * Supported restaurant locations metadata.
     *
     * @return array
     */
    public static function get_locations() {
        return array(
            'vn' => array(
                'code' => 'vn',
                'flag' => '🇻🇳',
                'labels' => array(
                    'vi' => 'Việt Nam',
                    'en' => 'Vietnam',
                    'ja' => 'ベトナム',
                ),
            ),
            'jp' => array(
                'code' => 'jp',
                'flag' => '🇯🇵',
                'labels' => array(
                    'vi' => 'Nhật Bản',
                    'en' => 'Japan',
                    'ja' => '日本',
                ),
            ),
            'ph' => array(
                'code' => 'ph',
                'flag' => '🇵🇭',
                'labels' => array(
                    'vi' => 'Philippines',
                    'en' => 'Philippines',
                    'ja' => 'フィリピン',
                ),
            ),
        );
    }

    /**
     * Sanitize location value and fall back to default.
     *
     * @param string $location
     * @return string
     */
    public static function sanitize_location($location) {
        $location = strtolower(sanitize_text_field($location));
        $locations = self::get_locations();
        return isset($locations[$location]) ? $location : 'vn';
    }

    /**
     * Get localized label for a location.
     *
     * @param string $location
     * @param string $language
     * @return string
     */
    public static function get_location_label($location, $language = 'vi') {
        $locations = self::get_locations();
        $language = self::sanitize_language($language);

        if (!isset($locations[$location])) {
            $location = 'vn';
        }

        $labels = $locations[$location]['labels'];

        return isset($labels[$language]) ? $labels[$language] : (isset($labels['vi']) ? $labels['vi'] : '');
    }

    /**
     * Check if a language is supported.
     *
     * @param string $language
     * @return bool
     */
    public static function is_supported_language($language) {
        return array_key_exists($language, self::get_languages());
    }

    /**
     * Get frontend translations for all supported languages.
     *
     * @return array
     */
    public static function get_frontend_translations() {
        return array(
            'vi' => array(
                'location_selection_title' => 'Chọn khu vực nhà hàng',
                'location_selection_description' => 'Vui lòng chọn khu vực nhà hàng mà bạn muốn đặt bàn.',
                'location_option_vn' => '🇻🇳 Việt Nam',
                'location_option_jp' => '🇯🇵 Nhật Bản',
                'location_option_ph' => '🇵🇭 Philippines',
                'location_required' => 'Vui lòng chọn khu vực nhà hàng.',
                'language_selection_title' => 'Chọn ngôn ngữ của bạn',
                'language_selection_description' => 'Vui lòng chọn ngôn ngữ bạn muốn sử dụng.',
                'schedule_step_title' => 'Chọn khu vực & thời gian',
                'schedule_step_description' => 'Vui lòng chọn khu vực, ngày, giờ và số lượng khách trước khi tiếp tục.',
                'details_step_title' => 'Thông tin liên hệ',
                'details_step_description' => 'Điền đầy đủ thông tin liên hệ để hoàn tất đặt bàn.',
                'check_availability_button' => 'Kiểm tra tình trạng bàn',
                'availability_precheck_required' => 'Vui lòng kiểm tra tình trạng bàn trước khi điền thông tin.',
                'availability_ready' => 'Còn bàn trống! Bạn có thể tiếp tục điền thông tin.',
                'modal_title' => 'Đặt bàn nhà hàng',
                'button_text' => 'Đặt bàn ngay',
                'customer_name_label' => 'Họ và tên *',
                'customer_phone_label' => 'Số điện thoại *',
                'customer_email_label' => 'Email *',
                'guest_count_label' => 'Số lượng khách *',
                'guest_option' => '%d người',
                'booking_date_label' => 'Ngày đặt bàn *',
                'booking_time_label' => 'Giờ đặt bàn *',
                'special_requests_label' => 'Yêu cầu đặc biệt',
                'submit_button' => 'Xác nhận đặt bàn',
                'cancel_button' => 'Hủy',
                'back_button' => 'Quay lại',
                'continue_button' => 'Tiếp tục',
                'inline_title' => 'Đặt bàn nhà hàng',
                'inline_submit_button' => 'Đặt bàn',
                'inline_date_label' => 'Ngày *',
                'inline_time_label' => 'Giờ *',
                'select_time_placeholder' => 'Chọn giờ',
                'no_slots' => 'Không có giờ trống',
                'availability_fill_all' => 'Vui lòng chọn đầy đủ ngày, giờ và số khách',
                'availability_checking' => 'Đang kiểm tra...',
                'availability_error' => 'Có lỗi xảy ra. Vui lòng thử lại.',
                'phone_invalid' => 'Số điện thoại không hợp lệ. Vui lòng nhập số điện thoại hợp lệ.',
                'loading_text' => 'Đang xử lý...',
                'error_text' => 'Có lỗi xảy ra. Vui lòng thử lại.',
                'form_missing_fields' => 'Vui lòng điền đầy đủ thông tin bắt buộc',
                'invalid_email' => 'Email không hợp lệ',
                'invalid_phone' => 'Số điện thoại không hợp lệ',
                'invalid_date' => 'Ngày đặt bàn không hợp lệ',
                'no_availability_message' => 'Rất tiếc, không còn bàn trống cho %1$s khách vào lúc %2$s ngày %3$s. Vui lòng chọn thời gian khác.',
                'success_message' => 'Đặt bàn thành công! Chúng tôi sẽ liên hệ với bạn sớm để xác nhận.',
                'availability_success' => 'Có %1$s bàn trống phù hợp cho %2$s khách',
                'availability_fail' => 'Không có bàn trống vào thời gian này. Vui lòng chọn thời gian khác.',
                'security_failed' => 'Kiểm tra bảo mật thất bại',
            ),
            'en' => array(
                'location_selection_title' => 'Choose a restaurant location',
                'location_selection_description' => 'Please select the restaurant location where you would like to dine.',
                'location_option_vn' => '🇻🇳 Vietnam',
                'location_option_jp' => '🇯🇵 Japan',
                'location_option_ph' => '🇵🇭 Philippines',
                'location_required' => 'Please choose a restaurant location.',
                'language_selection_title' => 'Choose your language',
                'language_selection_description' => 'Please choose the language you prefer.',
                'schedule_step_title' => 'Choose location & time',
                'schedule_step_description' => 'Select the location, date, time, and number of guests before continuing.',
                'details_step_title' => 'Contact details',
                'details_step_description' => 'Provide your contact information to complete the reservation.',
                'check_availability_button' => 'Check availability',
                'availability_precheck_required' => 'Please check availability before entering your details.',
                'availability_ready' => 'Great news! A table is available—please complete your details.',
                'modal_title' => 'Restaurant Booking',
                'button_text' => 'Book a Table',
                'customer_name_label' => 'Full Name *',
                'customer_phone_label' => 'Phone Number *',
                'customer_email_label' => 'Email *',
                'guest_count_label' => 'Number of Guests *',
                'guest_option' => '%d guests',
                'booking_date_label' => 'Reservation Date *',
                'booking_time_label' => 'Reservation Time *',
                'special_requests_label' => 'Special Requests',
                'submit_button' => 'Confirm Reservation',
                'cancel_button' => 'Cancel',
                'back_button' => 'Back',
                'continue_button' => 'Continue',
                'inline_title' => 'Restaurant Booking',
                'inline_submit_button' => 'Book Now',
                'inline_date_label' => 'Date *',
                'inline_time_label' => 'Time *',
                'select_time_placeholder' => 'Select a time',
                'no_slots' => 'No times available',
                'availability_fill_all' => 'Please select a date, time, and number of guests',
                'availability_checking' => 'Checking availability...',
                'availability_error' => 'An error occurred. Please try again.',
                'phone_invalid' => 'The phone number is invalid. Please enter a valid number.',
                'loading_text' => 'Processing...',
                'error_text' => 'An error occurred. Please try again.',
                'form_missing_fields' => 'Please fill in all required information',
                'invalid_email' => 'Email is invalid',
                'invalid_phone' => 'The phone number is invalid',
                'invalid_date' => 'Reservation date is invalid',
                'no_availability_message' => 'Sorry, there are no tables available for %1$s guests at %2$s on %3$s. Please choose another time.',
                'success_message' => 'Reservation successful! We will contact you soon to confirm.',
                'availability_success' => 'There are %1$s tables available for %2$s guests',
                'availability_fail' => 'No tables are available at this time. Please select another time.',
                'security_failed' => 'Security check failed',
            ),
            'ja' => array(
                'location_selection_title' => '店舗エリアを選択してください',
                'location_selection_description' => 'ご利用になりたい店舗エリアをお選びください。',
                'location_option_vn' => '🇻🇳 ベトナム',
                'location_option_jp' => '🇯🇵 日本',
                'location_option_ph' => '🇵🇭 フィリピン',
                'location_required' => '店舗エリアを選択してください。',
                'language_selection_title' => '言語を選択してください',
                'language_selection_description' => 'ご利用になりたい言語を選択してください。',
                'schedule_step_title' => '店舗と時間を選択',
                'schedule_step_description' => '店舗エリア、日付、時間、人数を選択してください。',
                'details_step_title' => '連絡先情報',
                'details_step_description' => '予約を完了するために連絡先情報を入力してください。',
                'check_availability_button' => '空席を確認する',
                'availability_precheck_required' => '情報を入力する前に空席をご確認ください。',
                'availability_ready' => '空席があります。お客様情報をご入力ください。',
                'modal_title' => 'レストラン予約',
                'button_text' => '予約する',
                'customer_name_label' => '氏名 *',
                'customer_phone_label' => '電話番号 *',
                'customer_email_label' => 'メールアドレス *',
                'guest_count_label' => '人数 *',
                'guest_option' => '%d 名',
                'booking_date_label' => '予約日 *',
                'booking_time_label' => '予約時間 *',
                'special_requests_label' => '特別なご要望',
                'submit_button' => '予約を確定する',
                'cancel_button' => 'キャンセル',
                'back_button' => '戻る',
                'continue_button' => '次へ',
                'inline_title' => 'レストラン予約',
                'inline_submit_button' => '予約する',
                'inline_date_label' => '日付 *',
                'inline_time_label' => '時間 *',
                'select_time_placeholder' => '時間を選択',
                'no_slots' => '空き時間がありません',
                'availability_fill_all' => '日付・時間・人数を選択してください',
                'availability_checking' => '空き状況を確認中...',
                'availability_error' => 'エラーが発生しました。もう一度お試しください。',
                'phone_invalid' => '電話番号が正しくありません。有効な番号を入力してください。',
                'loading_text' => '処理中...',
                'error_text' => 'エラーが発生しました。もう一度お試しください。',
                'form_missing_fields' => '必須項目をすべて入力してください',
                'invalid_email' => 'メールアドレスが正しくありません',
                'invalid_phone' => '電話番号が正しくありません',
                'invalid_date' => '予約日が正しくありません',
                'no_availability_message' => '申し訳ございません。%3$sの%2$sに%1$s名様でご利用いただける席がありません。別の時間をお選びください。',
                'success_message' => 'ご予約ありがとうございます。確認のため担当者よりご連絡いたします。',
                'availability_success' => '現在、%2$s名様にご利用いただける席が%1$s卓ございます。',
                'availability_fail' => 'この時間帯に空席はありません。別の時間をお選びください。',
                'security_failed' => 'セキュリティチェックに失敗しました',
            ),
        );
    }

    /**
     * Get backend translations for all supported languages.
     *
     * @return array
     */
    public static function get_backend_translations() {
        return array(
            'vi' => array(
                'create_booking_title' => 'Tạo đặt bàn mới',
                'customer_name_label' => 'Tên khách hàng *',
                'customer_phone_label' => 'Số điện thoại *',
                'customer_email_label' => 'Email *',
                'guest_count_label' => 'Số lượng khách *',
                'booking_date_label' => 'Ngày đặt *',
                'booking_time_label' => 'Giờ đặt *',
                'booking_location_label' => 'Khu vực nhà hàng *',
                'booking_language_label' => 'Ngôn ngữ sử dụng *',
                'booking_language_auto_note' => 'Ngôn ngữ được cố định theo WordPress Admin: <strong>%s</strong>.',
                'booking_source_label' => 'Nguồn đặt bàn *',
                'booking_source_description' => 'Chọn nguồn khách hàng đặt bàn từ đâu',
                'special_requests_label' => 'Yêu cầu đặc biệt',
                'admin_notes_label' => 'Ghi chú nội bộ',
                'admin_notes_description' => 'Ghi chú này chỉ dành cho admin, khách hàng không nhìn thấy',
                'auto_confirm_label' => 'Tự động xác nhận',
                'auto_confirm_description' => 'Tự động xác nhận và gán bàn',
                'submit_button' => 'Tạo đặt bàn',
                'cancel_button' => 'Hủy',
                'availability_title' => 'Thông tin bàn trống',
                'availability_status' => 'Trạng thái',
                'availability_free' => '✓ Còn bàn trống',
                'availability_full' => '✗ Hết bàn',
                'source_phone' => '📞 Điện thoại',
                'source_facebook' => '📘 Facebook',
                'source_zalo' => '💬 Zalo',
                'source_instagram' => '📷 Instagram',
                'source_walk_in' => '🚶 Khách vãng lai',
                'source_email' => '✉️ Email',
                'source_other' => '❓ Khác',
                'location_option_vn' => '🇻🇳 Việt Nam',
                'location_option_jp' => '🇯🇵 Nhật Bản',
                'location_option_ph' => '🇵🇭 Philippines',
            ),
            'en' => array(
                'create_booking_title' => 'Create a new reservation',
                'customer_name_label' => 'Customer name *',
                'customer_phone_label' => 'Phone number *',
                'customer_email_label' => 'Email *',
                'guest_count_label' => 'Number of guests *',
                'booking_date_label' => 'Reservation date *',
                'booking_time_label' => 'Reservation time *',
                'booking_location_label' => 'Restaurant location *',
                'booking_language_label' => 'Working language *',
                'booking_language_auto_note' => 'The language follows the WordPress admin locale: <strong>%s</strong>.',
                'booking_source_label' => 'Reservation source *',
                'booking_source_description' => 'Choose where the reservation came from',
                'special_requests_label' => 'Special requests',
                'admin_notes_label' => 'Internal notes',
                'admin_notes_description' => 'Only administrators can see these notes',
                'auto_confirm_label' => 'Auto confirm',
                'auto_confirm_description' => 'Automatically confirm and assign a table',
                'submit_button' => 'Create reservation',
                'cancel_button' => 'Cancel',
                'availability_title' => 'Available tables',
                'availability_status' => 'Status',
                'availability_free' => '✓ Available',
                'availability_full' => '✗ Fully booked',
                'source_phone' => '📞 Phone',
                'source_facebook' => '📘 Facebook',
                'source_zalo' => '💬 Zalo',
                'source_instagram' => '📷 Instagram',
                'source_walk_in' => '🚶 Walk-in',
                'source_email' => '✉️ Email',
                'source_other' => '❓ Other',
                'location_option_vn' => '🇻🇳 Vietnam',
                'location_option_jp' => '🇯🇵 Japan',
                'location_option_ph' => '🇵🇭 Philippines',
            ),
            'ja' => array(
                'create_booking_title' => '新規予約を作成',
                'customer_name_label' => 'お客様名 *',
                'customer_phone_label' => '電話番号 *',
                'customer_email_label' => 'メールアドレス *',
                'guest_count_label' => '人数 *',
                'booking_date_label' => '予約日 *',
                'booking_time_label' => '予約時間 *',
                'booking_location_label' => '店舗エリア *',
                'booking_language_label' => '使用言語 *',
                'booking_language_auto_note' => '言語は WordPress 管理画面のロケールに合わせて設定されています: <strong>%s</strong>。',
                'booking_source_label' => '予約経路 *',
                'booking_source_description' => 'お客様がどこから予約されたかを選択してください',
                'special_requests_label' => '特別なご要望',
                'admin_notes_label' => '社内メモ',
                'admin_notes_description' => 'このメモは管理者にのみ表示されます',
                'auto_confirm_label' => '自動承認',
                'auto_confirm_description' => '自動的に承認し、席を割り当てます',
                'submit_button' => '予約を作成',
                'cancel_button' => 'キャンセル',
                'availability_title' => '空席情報',
                'availability_status' => 'ステータス',
                'availability_free' => '✓ 空席あり',
                'availability_full' => '✗ 空席なし',
                'source_phone' => '📞 電話',
                'source_facebook' => '📘 Facebook',
                'source_zalo' => '💬 Zalo',
                'source_instagram' => '📷 Instagram',
                'source_walk_in' => '🚶 来店',
                'source_email' => '✉️ メール',
                'source_other' => '❓ その他',
                'location_option_vn' => '🇻🇳 ベトナム',
                'location_option_jp' => '🇯🇵 日本',
                'location_option_ph' => '🇵🇭 フィリピン',
            ),
        );
    }

    /**
     * Get translations for a specific section and language.
     *
     * @param string $section
     * @param string $language
     * @return array
     */
    public static function get_section_translations($section, $language) {
        $language = self::sanitize_language($language);

        switch ($section) {
            case 'backend':
                $translations = self::get_backend_translations();
                break;
            case 'frontend':
            default:
                $translations = self::get_frontend_translations();
                break;
        }

        $fallback = isset($translations['vi']) ? $translations['vi'] : array();
        $current = isset($translations[$language]) ? $translations[$language] : array();

        return array_merge($fallback, $current);
    }

    /**
     * Translate a specific key.
     *
     * @param string $section
     * @param string $key
     * @param string $language
     * @return string
     */
    public static function translate($section, $key, $language) {
        $translations = self::get_section_translations($section, $language);
        return isset($translations[$key]) ? $translations[$key] : '';
    }

    /**
     * Determine supported language from a WordPress locale.
     *
     * @param string $locale
     * @param string $fallback
     * @return string
     */
    public static function get_language_from_locale($locale, $fallback = 'vi') {
        $fallback = self::sanitize_language($fallback);

        if (empty($locale)) {
            return $fallback;
        }

        $normalized = strtolower(str_replace('-', '_', $locale));

        foreach (self::get_languages() as $code => $language) {
            if ($normalized === $code || strpos($normalized, $code . '_') === 0) {
                return $code;
            }
        }

        return $fallback;
    }

    /**
     * Sanitize language code and ensure fallback.
     *
     * @param string $language
     * @return string
     */
    public static function sanitize_language($language) {
        $language = strtolower(sanitize_text_field($language));
        return self::is_supported_language($language) ? $language : 'vi';
    }
}
