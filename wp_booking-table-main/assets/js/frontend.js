/**
 * Restaurant Booking - Frontend JavaScript with location-aware flow
 */

(function($) {
    'use strict';

    $(function() {
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

        function getWidgetLocation(widget) {
            return widget.data('current-location') || widget.data('default-location') || 'vn';
        }

        function showAvailabilityMessage(form, state, message) {
            var $message = form.find('.rb-availability-message');
            if (!$message.length) {
                return;
            }

            if (!message) {
                $message.removeClass('success error').hide().empty();
                return;
            }

            $message
                .removeClass('success error')
                .toggleClass('success', state === 'success')
                .toggleClass('error', state === 'error')
                .html(message)
                .show();
        }

        function toggleContactFields(form, enabled) {
            form.find('.rb-contact-field').prop('disabled', !enabled);
        }

        function setSubmitEnabled(form, enabled) {
            form.find('[type="submit"]').prop('disabled', !enabled);
        }

        function invalidateAvailability(form, options) {
            var opts = options || {};
            form.data('availability-confirmed', false);
            toggleContactFields(form, false);
            setSubmitEnabled(form, false);

            if (!opts.keepAvailabilityMessage) {
                showAvailabilityMessage(form, '', '');
            }

            if (opts.messageKey && !opts.silent) {
                var language = getWidgetLanguage(form.closest('.rb-booking-widget'));
                var message = getTranslation(language, opts.messageKey);
                if (message) {
                    showAvailabilityMessage(form, 'error', message);
                }
            }
        }

        function markAvailabilityConfirmed(form, message) {
            var widget = form.closest('.rb-booking-widget');
            var language = getWidgetLanguage(widget);
            var fallbackMessage = getTranslation(language, 'availability_ready');

            form.data('availability-confirmed', true);
            toggleContactFields(form, true);
            setSubmitEnabled(form, true);
            showAvailabilityMessage(form, 'success', message || fallbackMessage || '');
            form.find('.rb-form-message').removeClass('error').hide();
        }

        function updateWidgetLocation(widget, location, options) {
            var opts = options || {};
            var loc = location || widget.data('default-location') || 'vn';

            widget.data('current-location', loc);
            widget.find('input[name="booking_location"]').val(loc);
            widget.find('.rb-location-option').removeClass('active');
            widget.find('.rb-location-option[data-location="' + loc + '"]').addClass('active');

            widget.find('form.rb-form').each(function() {
                invalidateAvailability($(this), {
                    silent: opts.silent,
                    keepAvailabilityMessage: opts.keepAvailabilityMessage,
                    messageKey: opts.messageKey
                });
            });
        }

        function sanitizePhone($input) {
            var digits = ($input.val() || '').replace(/[^0-9]/g, '');
            $input.val(digits);
        }

        function runAvailabilityCheck(button) {
            var $button = $(button);
            var form = $button.closest('form');
            var widget = form.closest('.rb-booking-widget');
            var language = getWidgetLanguage(widget);
            var location = getWidgetLocation(widget);
            var date = form.find('input[name="booking_date"]').val();
            var time = form.find('select[name="booking_time"]').val();
            var guests = form.find('select[name="guest_count"]').val();
            var fillAll = getTranslation(language, 'availability_fill_all');
            var checking = getTranslation(language, 'availability_checking');
            var errorText = getTranslation(language, 'availability_error') || rb_ajax.error_text;
            var locationRequired = getTranslation(language, 'location_required');

            form.find('.rb-form-message').removeClass('success error').hide().empty();
            invalidateAvailability(form, {keepAvailabilityMessage: true, silent: true});

            if (!location) {
                showAvailabilityMessage(form, 'error', locationRequired || errorText);
                return;
            }

            if (!date || !time || !guests) {
                showAvailabilityMessage(form, 'error', fillAll || errorText);
                return;
            }

            showAvailabilityMessage(form, '', checking || '');
            $button.prop('disabled', true);

            $.ajax({
                url: rb_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rb_check_availability',
                    nonce: rb_ajax.nonce,
                    date: date,
                    time: time,
                    guests: guests,
                    language: language,
                    location: location
                }
            }).done(function(response) {
                if (response && response.success && response.data) {
                    if (response.data.available) {
                        markAvailabilityConfirmed(form, response.data.message);
                    } else {
                        invalidateAvailability(form, {keepAvailabilityMessage: true});
                        showAvailabilityMessage(form, 'error', response.data.message || errorText);
                    }
                } else if (response && response.data && response.data.message) {
                    invalidateAvailability(form, {keepAvailabilityMessage: true});
                    showAvailabilityMessage(form, 'error', response.data.message);
                } else {
                    invalidateAvailability(form, {keepAvailabilityMessage: true});
                    showAvailabilityMessage(form, 'error', errorText);
                }
            }).fail(function() {
                invalidateAvailability(form, {keepAvailabilityMessage: true});
                showAvailabilityMessage(form, 'error', errorText);
            }).always(function() {
                $button.prop('disabled', false);
            });
        }

        function submitBookingForm(form) {
            var widget = form.closest('.rb-booking-widget');
            var language = getWidgetLanguage(widget);
            var location = getWidgetLocation(widget);
            var loadingText = getTranslation(language, 'loading_text') || rb_ajax.loading_text;
            var errorText = getTranslation(language, 'error_text') || rb_ajax.error_text;
            var phoneInvalidText = getTranslation(language, 'phone_invalid');
            var $message = form.find('.rb-form-message');
            var nonceField = form.find('[name="rb_nonce"], [name="rb_nonce_inline"]');
            var nonce = nonceField.val();

            if (!location) {
                $message.removeClass('success').addClass('error').html(getTranslation(language, 'location_required') || errorText).show();
                return;
            }

            var phoneField = form.find('input[type="tel"]');
            var phone = phoneField.val();
            if (phone && !/^[0-9]{6,15}$/.test(phone)) {
                $message.removeClass('success').addClass('error').html(phoneInvalidText || errorText).show();
                return;
            }

            var formData = form.serialize();
            if (nonce) {
                formData += '&rb_nonce=' + encodeURIComponent(nonce);
            }

            var submitBtn = form.find('[type="submit"]');
            var originalText = submitBtn.text();

            submitBtn.text(loadingText).prop('disabled', true);
            form.addClass('rb-loading');
            $message.removeClass('success error').hide().empty();

            $.ajax({
                url: rb_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=rb_submit_booking'
            }).done(function(response) {
                if (response && response.success && response.data) {
                    $message.removeClass('error').addClass('success').html(response.data.message || '').show();
                    if (form.length && form[0]) {
                        form[0].reset();
                    }
                    form.find('input[name="booking_location"]').val(location);
                    form.find('input[name="booking_language"]').val(language);
                    invalidateAvailability(form, {silent: true});
                    form.data('availability-confirmed', false);
                } else if (response && response.data && response.data.message) {
                    $message.removeClass('success').addClass('error').html(response.data.message).show();
                } else {
                    $message.removeClass('success').addClass('error').html(errorText).show();
                }
            }).fail(function() {
                $message.removeClass('success').addClass('error').html(errorText).show();
            }).always(function() {
                submitBtn.text(originalText).prop('disabled', false);
                form.removeClass('rb-loading');
            });
        }

        function initializeForm(widget, form, options) {
            var opts = options || {};
            form.find('.rb-form-message').removeClass('success error').hide().empty();
            invalidateAvailability(form, {silent: opts.silent, keepAvailabilityMessage: opts.keepAvailabilityMessage});
            toggleContactFields(form, false);
            setSubmitEnabled(form, false);
            form.find('input[name="booking_language"]').val(getWidgetLanguage(widget));
            form.find('input[name="booking_location"]').val(getWidgetLocation(widget));
        }

        function resetWidget(widget) {
            widget.find('form.rb-form').each(function() {
                if (this.reset) {
                    this.reset();
                }
                initializeForm(widget, $(this), {silent: true});
            });

            updateWidgetLanguage(widget, widget.data('default-language') || defaultLanguage);
            updateWidgetLocation(widget, widget.data('default-location') || 'vn', {silent: true});
        }

        $('.rb-booking-widget').each(function() {
            var widget = $(this);
            var modal = widget.find('#rb-booking-modal');
            var openBtn = widget.find('.rb-open-modal-btn');
            var closeBtn = modal.find('.rb-close, .rb-close-modal');
            var defaultLang = widget.data('default-language') || defaultLanguage;
            var defaultLocation = widget.data('default-location') || 'vn';

            widget.data('default-language', defaultLang);
            widget.data('default-location', defaultLocation);

            updateWidgetLanguage(widget, defaultLang);
            updateWidgetLocation(widget, defaultLocation, {silent: true});

            widget.find('form.rb-form').each(function() {
                initializeForm(widget, $(this), {silent: true});
            });

            widget.on('click', '.rb-language-option', function(e) {
                e.preventDefault();
                var lang = $(this).data('lang');
                if (lang) {
                    updateWidgetLanguage(widget, lang);
                    widget.find('form.rb-form').each(function() {
                        $(this).find('input[name="booking_language"]').val(lang);
                    });
                }
            });

            widget.on('click', '.rb-location-option', function(e) {
                e.preventDefault();
                var loc = $(this).data('location');
                if (loc) {
                    updateWidgetLocation(widget, loc, {messageKey: 'availability_precheck_required'});
                }
            });

            widget.on('click', '.rb-check-availability', function(e) {
                e.preventDefault();
                runAvailabilityCheck(this);
            });

            widget.on('change input', '.rb-schedule-field', function() {
                invalidateAvailability($(this).closest('form'), {messageKey: 'availability_precheck_required'});
            });

            widget.on('submit', 'form.rb-form', function(e) {
                e.preventDefault();
                var form = $(this);
                if (!form.data('availability-confirmed')) {
                    var language = getWidgetLanguage(widget);
                    var warn = getTranslation(language, 'availability_precheck_required');
                    if (warn) {
                        form.find('.rb-form-message').removeClass('success').addClass('error').html(warn).show();
                    }
                    return;
                }
                submitBookingForm(form);
            });

            widget.on('input', 'input[type="tel"]', function() {
                sanitizePhone($(this));
            });

            if (openBtn.length) {
                openBtn.on('click', function(e) {
                    e.preventDefault();
                    modal.addClass('show');
                    $('body').css('overflow', 'hidden');
                });
            }

            if (closeBtn.length) {
                closeBtn.on('click', function(e) {
                    e.preventDefault();
                    modal.removeClass('show');
                    $('body').css('overflow', 'auto');
                    resetWidget(widget);
                });
            }

            $(window).on('click', function(e) {
                if ($(e.target).is(modal)) {
                    modal.removeClass('show');
                    $('body').css('overflow', 'auto');
                    resetWidget(widget);
                }
            });
        });

        $(document).on('rb_booking_success', function() {
            setTimeout(function() {
                $('.rb-form-message').fadeOut();
            }, 10000);
        });

    });
})(jQuery);
