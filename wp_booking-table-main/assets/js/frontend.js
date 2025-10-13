/**
 * Restaurant Booking - Frontend JavaScript with language support
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var translations = rb_ajax.translations || {};
        var fallbackLanguage = rb_ajax.fallback_language || 'vi';
        var defaultLanguage = rb_ajax.default_language || fallbackLanguage;

        function getTranslationsFor(language) {
            var lang = language || defaultLanguage;
            var fallback = translations[fallbackLanguage] || {};
            var current = translations[lang] || {};
            return $.extend({}, fallback, current);
        }

        function getTranslation(language, key) {
            var map = getTranslationsFor(language);
            return map[key] || '';
        }

        function applyTranslations(language, context) {
            var langMap = getTranslationsFor(language);
            var $context = context || $(document);

            $context.find('[data-lang-key]').each(function() {
                var $el = $(this);
                var key = $el.data('lang-key');
                var attr = $el.data('lang-attr') || 'text';
                var value = langMap[key];

                if (value === undefined) {
                    return;
                }

                if (key === 'guest_option') {
                    var count = $el.data('count');
                    if (typeof count !== 'undefined') {
                        value = value.replace('%d', count);
                    }
                }

                switch (attr) {
                    case 'text':
                        $el.text(value);
                        break;
                    case 'html':
                        $el.html(value);
                        break;
                    case 'value':
                        $el.val(value);
                        break;
                    case 'placeholder':
                        $el.attr('placeholder', value);
                        break;
                    case 'aria-label':
                        $el.attr('aria-label', value);
                        break;
                    default:
                        $el.text(value);
                        break;
                }
            });
        }

        function updateWidgetLanguage(widget, language) {
            var lang = language || defaultLanguage;
            widget.data('current-language', lang);
            applyTranslations(lang, widget);
            widget.find('input[name="booking_language"]').val(lang);
            widget.find('.rb-language-option').removeClass('active');
            widget.find('.rb-language-option[data-lang="' + lang + '"]').addClass('active');
        }

        function getWidgetLanguage(widget) {
            return widget.data('current-language') || widget.data('default-language') || defaultLanguage;
        }

        function resetForm(widget) {
            if (!widget || !widget.length) {
                return;
            }

            var modalForm = widget.find('#rb-booking-form');
            if (modalForm.length && modalForm[0]) {
                modalForm[0].reset();
            }

            var inlineForm = widget.find('#rb-booking-form-inline');
            if (inlineForm.length && inlineForm[0]) {
                inlineForm[0].reset();
            }

            widget.find('.rb-form-message').removeClass('success error').hide().empty();
            widget.find('#rb-availability-result').removeClass('success error').hide().empty();

            updateWidgetLanguage(widget, widget.data('default-language') || defaultLanguage);
        }

        function submitBookingForm(form, messageContainer) {
            var widget = form.closest('.rb-booking-widget');
            var language = getWidgetLanguage(widget);
            var loadingText = getTranslation(language, 'loading_text') || rb_ajax.loading_text;
            var errorText = getTranslation(language, 'error_text') || rb_ajax.error_text;
            var phoneInvalidText = getTranslation(language, 'phone_invalid');

            var phoneField = form.find('input[type="tel"]');
            var phone = phoneField.val();
            if (phone && !/^[0-9]{6,15}$/.test(phone)) {
                $(messageContainer)
                    .removeClass('success')
                    .addClass('error')
                    .html(phoneInvalidText || errorText)
                    .show();
                return;
            }

            var formData = form.serialize();
            var submitBtn = form.find('[type="submit"]');
            var originalText = submitBtn.text();

            submitBtn.text(loadingText).prop('disabled', true);
            form.addClass('rb-loading');

            $(messageContainer).removeClass('success error').hide();

            $.ajax({
                url: rb_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=rb_submit_booking&rb_nonce=' + form.find('[name="rb_nonce"], [name="rb_nonce_inline"]').val(),
                success: function(response) {
                    if (response.success) {
                        $(messageContainer)
                            .removeClass('error')
                            .addClass('success')
                            .html(response.data.message)
                            .show();

                        if (form.length && form[0]) {
                            form[0].reset();
                        }

                        var modal = widget.find('#rb-booking-modal');
                        if (modal.hasClass('show')) {
                            setTimeout(function() {
                                modal.removeClass('show');
                                $('body').css('overflow', 'auto');
                                resetForm(widget);
                            }, 3000);
                        }

                        $(document).trigger('rb_booking_success', [response.data]);
                    } else {
                        $(messageContainer)
                            .removeClass('success')
                            .addClass('error')
                            .html(response.data.message)
                            .show();
                    }
                },
                error: function() {
                    $(messageContainer)
                        .removeClass('success')
                        .addClass('error')
                        .html(errorText)
                        .show();
                },
                complete: function() {
                    submitBtn.text(originalText).prop('disabled', false);
                    form.removeClass('rb-loading');
                }
            });
        }

        function updateAvailableTimeSlots(date, guestCount, timeSelect) {
            var form = timeSelect.closest('form');
            var widget = form.closest('.rb-booking-widget');
            var language = getWidgetLanguage(widget);
            var placeholder = getTranslation(language, 'select_time_placeholder');
            var noSlots = getTranslation(language, 'no_slots');

            $.ajax({
                url: rb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rb_get_time_slots',
                    date: date,
                    guest_count: guestCount,
                    nonce: rb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var slots = response.data.slots || [];
                        var currentValue = timeSelect.val();

                        timeSelect.empty();
                        timeSelect.append('<option value="">' + (placeholder || '') + '</option>');

                        if (slots.length > 0) {
                            $.each(slots, function(i, slot) {
                                var selected = (slot === currentValue) ? ' selected' : '';
                                timeSelect.append('<option value="' + slot + '"' + selected + '>' + slot + '</option>');
                            });
                        } else {
                            timeSelect.append('<option value="">' + (noSlots || '') + '</option>');
                        }
                    }
                }
            });
        }

        function checkAvailability() {
            var date = $('#rb_booking_date').val();
            var time = $('#rb_booking_time').val();
            var guests = $('#rb_guest_count').val();
            var resultDiv = $('#rb-availability-result');
            var language = $('#rb_booking_language').val() || defaultLanguage;
            var fillAll = getTranslation(language, 'availability_fill_all');
            var checking = getTranslation(language, 'availability_checking');
            var errorText = getTranslation(language, 'availability_error');

            if (!resultDiv.length) {
                return;
            }

            if (!date || !time || !guests) {
                resultDiv
                    .removeClass('success')
                    .addClass('error')
                    .html(fillAll || '')
                    .show();
                return;
            }

            resultDiv
                .removeClass('success error')
                .html(checking || '')
                .show();

            $.ajax({
                url: rb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rb_check_availability',
                    date: date,
                    time: time,
                    guests: guests,
                    language: language,
                    nonce: rb_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            resultDiv
                                .removeClass('error')
                                .addClass('success')
                                .html(response.data.message);
                        } else {
                            resultDiv
                                .removeClass('success')
                                .addClass('error')
                                .html(response.data.message);
                        }
                    } else {
                        resultDiv
                            .removeClass('success')
                            .addClass('error')
                            .html(response.data.message);
                    }
                },
                error: function() {
                    resultDiv
                        .removeClass('success')
                        .addClass('error')
                        .html(errorText || '')
                        .show();
                }
            });
        }

        $('.rb-booking-widget').each(function() {
            var widget = $(this);
            var modal = widget.find('#rb-booking-modal');
            var openBtn = widget.find('.rb-open-modal-btn');
            var closeBtn = modal.find('.rb-close, .rb-close-modal');
            var defaultLang = widget.data('default-language') || defaultLanguage;

            widget.data('default-language', defaultLang);
            updateWidgetLanguage(widget, defaultLang);

            widget.on('click', '.rb-language-option', function(e) {
                e.preventDefault();
                var lang = $(this).data('lang');
                if (lang) {
                    updateWidgetLanguage(widget, lang);
                }
            });

            openBtn.on('click', function(e) {
                e.preventDefault();
                modal.addClass('show');
                $('body').css('overflow', 'hidden');
            });

            closeBtn.on('click', function(e) {
                e.preventDefault();
                modal.removeClass('show');
                $('body').css('overflow', 'auto');
                resetForm(widget);
            });

            $(window).on('click', function(e) {
                if ($(e.target).is(modal)) {
                    modal.removeClass('show');
                    $('body').css('overflow', 'auto');
                    resetForm(widget);
                }
            });
        });

        $(document).on('submit', '#rb-booking-form', function(e) {
            e.preventDefault();
            submitBookingForm($(this), '#rb-form-message');
        });

        $(document).on('submit', '#rb-booking-form-inline', function(e) {
            e.preventDefault();
            submitBookingForm($(this), '#rb-form-message-inline');
        });

        $(document).on('change', '#rb_booking_date, #rb_date_inline', function() {
            var date = $(this).val();
            var guestCount = $(this).closest('form').find('[name="guest_count"]').val();
            var timeSelect = $(this).closest('form').find('[name="booking_time"]');

            if (date && guestCount) {
                updateAvailableTimeSlots(date, guestCount, timeSelect);
            }
        });

        $(document).on('change', '#rb_guest_count, #rb_guests_inline', function() {
            var guestCount = $(this).val();
            var date = $(this).closest('form').find('[name="booking_date"]').val();
            var timeSelect = $(this).closest('form').find('[name="booking_time"]');

            if (date && guestCount) {
                updateAvailableTimeSlots(date, guestCount, timeSelect);
            }
        });

        $('#rb-check-availability').on('click', function(e) {
            e.preventDefault();
            checkAvailability();
        });

        $(document).on('input', '#rb-booking-form input[type="tel"], #rb-booking-form-inline input[type="tel"]', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0');
        var yyyy = today.getFullYear();
        today = yyyy + '-' + mm + '-' + dd;

        $('#rb_booking_date, #rb_date_inline').attr('min', today);

        var maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 30);
        var maxDd = String(maxDate.getDate()).padStart(2, '0');
        var maxMm = String(maxDate.getMonth() + 1).padStart(2, '0');
        var maxYyyy = maxDate.getFullYear();
        maxDate = maxYyyy + '-' + maxMm + '-' + maxDd;

        $('#rb_booking_date, #rb_date_inline').attr('max', maxDate);

        $(document).on('rb_booking_success', function() {
            setTimeout(function() {
                $('.rb-form-message').fadeOut();
            }, 10000);
        });
    });

})(jQuery);
