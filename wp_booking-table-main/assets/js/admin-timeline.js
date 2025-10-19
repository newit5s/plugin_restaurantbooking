(function($) {
    'use strict';

    const defaults = {
        ajaxUrl: '',
        nonce: '',
        locations: [],
        defaultLocation: '',
        today: '',
        statusColors: {},
        i18n: {},
        autoRefreshInterval: 60000,
        initialData: null
    };

    class RBTimeline {
        constructor($root, options) {
            this.$root = $root;
            this.settings = $.extend(true, {}, defaults, options || {});

            this.$status = $('#rb-timeline-status');
            this.$locationSelect = $('#rb-timeline-location');
            this.$dateInput = $('#rb-timeline-date');
            this.$autoRefreshToggle = $('#rb-timeline-auto-refresh');
            this.$refreshButton = $('.rb-timeline-refresh');
            this.$prevButton = $('.rb-timeline-prev');
            this.$nextButton = $('.rb-timeline-next');
            this.$todayButton = $('.rb-timeline-today');
            this.$modal = $('#rb-timeline-modal');
            this.$modalContent = this.$modal.find('.rb-timeline-modal__content');
            this.$modalClose = this.$modal.find('.rb-timeline-modal__close');

            this.currentDate = this.$dateInput.val() || this.settings.today;
            this.currentLocation = this.$locationSelect.val() || this.settings.defaultLocation;
            this.autoRefreshEnabled = true;
            this.autoRefreshTimer = null;
            this.tableMap = {};
            this.bookingMap = {};
            this.draggedBookingId = null;
            this.draggedBooking = null;
            this.isDragging = false;
        }

        init() {
            if (!this.settings.ajaxUrl) {
                return;
            }

            this.bindEvents();
            this.clearStatus();

            if (this.settings.initialData) {
                this.render(this.settings.initialData);
            } else {
                this.fetchData();
            }

            this.startAutoRefresh();
        }

        bindEvents() {
            this.$locationSelect.on('change', () => {
                this.currentLocation = this.$locationSelect.val();
                this.fetchData(true);
            });

            this.$dateInput.on('change', () => {
                const val = this.$dateInput.val();
                if (val) {
                    this.currentDate = val;
                    this.fetchData(true);
                }
            });

            this.$prevButton.on('click', () => this.shiftDate(-1));
            this.$nextButton.on('click', () => this.shiftDate(1));
            this.$todayButton.on('click', () => this.setDate(this.settings.today));

            this.$refreshButton.on('click', () => this.fetchData(true));

            this.$autoRefreshToggle.on('change', () => this.toggleAutoRefresh());

            this.$root.on('click', '.rb-booking-action--checkin', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const bookingId = $(event.currentTarget).closest('.rb-booking-block').data('bookingId');
                if (bookingId) {
                    this.handleCheckIn(bookingId);
                }
            });

            this.$root.on('click', '.rb-booking-action--checkout', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const bookingId = $(event.currentTarget).closest('.rb-booking-block').data('bookingId');
                if (bookingId) {
                    this.handleCheckOut(bookingId);
                }
            });

            this.$root.on('click', '.rb-table-toggle', (event) => {
                event.preventDefault();
                const $btn = $(event.currentTarget);
                const tableId = $btn.data('tableId');
                const status = Number($btn.data('status'));
                if (tableId) {
                    this.toggleTable(tableId, status ? 0 : 1);
                }
            });

            this.$root.on('click', '.rb-booking-block', (event) => {
                if ($(event.target).closest('.rb-booking-action').length) {
                    return;
                }

                const bookingId = $(event.currentTarget).data('bookingId');
                if (bookingId && this.bookingMap[bookingId]) {
                    this.openModal(this.bookingMap[bookingId]);
                }
            });

            this.$modalClose.on('click', () => this.closeModal());
            this.$modal.on('click', (event) => {
                if ($(event.target).is(this.$modal)) {
                    this.closeModal();
                }
            });

            $(document).on('keyup.rbTimeline', (event) => {
                if (event.key === 'Escape') {
                    this.closeModal();
                }
            });
        }

        shiftDate(offset) {
            const date = this.parseDate(this.currentDate);
            if (!date) {
                return;
            }

            date.setDate(date.getDate() + offset);
            this.setDate(this.formatDate(date));
        }

        setDate(dateStr) {
            if (!dateStr) {
                return;
            }

            this.currentDate = dateStr;
            this.$dateInput.val(dateStr);
            this.fetchData(true);
        }

        toggleAutoRefresh() {
            this.autoRefreshEnabled = this.$autoRefreshToggle.is(':checked');

            if (this.autoRefreshEnabled) {
                this.startAutoRefresh();
                this.setStatus(this.settings.i18n.autoRefreshOn, 'success');
            } else {
                this.stopAutoRefresh();
                this.setStatus(this.settings.i18n.autoRefreshOff, 'warning');
            }
        }

        startAutoRefresh() {
            this.stopAutoRefresh();

            if (!this.autoRefreshEnabled) {
                return;
            }

            const interval = parseInt(this.settings.autoRefreshInterval, 10) || 60000;
            this.autoRefreshTimer = window.setInterval(() => {
                if (!this.isDragging) {
                    this.fetchData();
                }
            }, interval);
        }

        stopAutoRefresh() {
            if (this.autoRefreshTimer) {
                window.clearInterval(this.autoRefreshTimer);
                this.autoRefreshTimer = null;
            }
        }

        showLoading() {
            if (!this.$root.find('.rb-timeline-loading').length) {
                this.$root.addClass('is-loading');
                this.$root.append('<div class="rb-timeline-loading"><span class="spinner is-active"></span></div>');
            }
        }

        hideLoading() {
            this.$root.removeClass('is-loading');
            this.$root.find('.rb-timeline-loading').remove();
        }

        fetchData(manual = false) {
            const date = this.currentDate || this.settings.today;
            const location = this.currentLocation || this.settings.defaultLocation;

            if (!date || !location) {
                return;
            }

            if (manual) {
                this.setStatus(this.settings.i18n.refresh, 'info');
            }

            this.showLoading();

            $.ajax({
                url: this.settings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'rb_get_timeline_data',
                    nonce: this.settings.nonce,
                    date: date,
                    location: location
                }
            }).done((response) => {
                if (response && response.success && response.data) {
                    this.render(response.data);
                    if (manual) {
                        this.setStatus(this.settings.i18n.updated, 'success');
                    } else {
                        this.clearStatus();
                    }
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : this.settings.i18n.error;
                    this.setStatus(message, 'error');
                }
            }).fail(() => {
                this.setStatus(this.settings.i18n.error, 'error');
            }).always(() => {
                this.hideLoading();
                if (manual && this.autoRefreshEnabled) {
                    this.startAutoRefresh();
                }
            });
        }

        render(data) {
            if (!data) {
                return;
            }

            this.currentDate = data.date || this.currentDate;
            this.currentLocation = data.location || this.currentLocation;

            this.$dateInput.val(this.currentDate);
            if (this.$locationSelect.val() !== this.currentLocation) {
                this.$locationSelect.val(this.currentLocation);
            }

            this.tableMap = {};
            this.bookingMap = {};
            this.timeSlots = data.timeSlots || [];
            this.interval = data.interval || 30;
            this.blockDuration = data.blockDuration || this.interval;
            this.statusLabels = data.statusLabels || {};

            this.$root.empty();

            if (!this.timeSlots.length || !data.tables || !data.tables.length) {
                this.$root.append('<div class="rb-timeline-empty-state"><p>' + this.settings.i18n.noData + '</p></div>');
                return;
            }

            const columnTemplate = this.getColumnTemplate();
            const $grid = $('<div class="rb-timeline-grid" />');
            const $headerRow = $('<div class="rb-timeline-row rb-timeline-row--header" />').css('grid-template-columns', columnTemplate);
            $headerRow.append('<div class="rb-timeline-cell rb-timeline-cell--label">' + this.settings.i18n.tableHeader + '</div>');
            this.timeSlots.forEach((slot) => {
                $headerRow.append('<div class="rb-timeline-cell rb-timeline-cell--time" data-time="' + slot + '">' + slot + '</div>');
            });
            $grid.append($headerRow);

            const $body = $('<div class="rb-timeline-body" />');
            data.tables.forEach((table) => {
                const $row = $('<div class="rb-timeline-row rb-timeline-row--body" />')
                    .css('grid-template-columns', columnTemplate)
                    .attr('data-table-id', table.id)
                    .attr('data-table-number', table.table_number);

                if (!parseInt(table.is_available, 10)) {
                    $row.addClass('is-disabled');
                }

                const statusClass = parseInt(table.is_available, 10) ? 'is-available' : 'is-unavailable';
                const statusText = parseInt(table.is_available, 10) ? this.settings.i18n.statusAvailable : this.settings.i18n.statusUnavailable;

                const $labelCell = $('<div class="rb-timeline-cell rb-timeline-cell--table" />');
                const headingHtml = '<div class="rb-table-heading">'
                    + '<span class="rb-table-name">' + this.settings.i18n.tableHeader + ' ' + table.table_number + '</span>'
                    + '<span class="rb-table-status ' + statusClass + '">' + statusText + '</span>'
                    + '</div>';
                const metaHtml = '<div class="rb-table-meta">' + this.settings.i18n.capacityLabel + ': ' + table.capacity + '</div>';
                const toggleHtml = '<button type="button" class="rb-table-toggle button-link" data-table-id="' + table.id + '" data-status="' + parseInt(table.is_available, 10) + '">' + this.settings.i18n.toggleAvailability + '</button>';
                $labelCell.append(headingHtml + metaHtml + toggleHtml);
                $row.append($labelCell);

                this.timeSlots.forEach((slot, index) => {
                    const $cell = $('<div class="rb-timeline-cell rb-timeline-cell--slot" />');
                    $cell.attr({
                        'data-time': slot,
                        'data-slot-index': index
                    });
                    $cell.data({
                        tableId: table.id,
                        tableNumber: table.table_number,
                        available: !!parseInt(table.is_available, 10)
                    });

                    $cell.on('dragover', (event) => this.handleDragOver(event));
                    $cell.on('dragenter', (event) => this.handleDragEnter(event));
                    $cell.on('dragleave', (event) => this.handleDragLeave(event));
                    $cell.on('drop', (event) => this.handleDrop(event));

                    $row.append($cell);
                });

                $body.append($row);
                this.tableMap[table.table_number] = {
                    table: table,
                    $row: $row
                };
            });

            const unassigned = (data.bookings || []).filter((booking) => !booking.table_number);
            if (unassigned.length) {
                const $row = $('<div class="rb-timeline-row rb-timeline-row--body rb-timeline-row--unassigned" />').css('grid-template-columns', columnTemplate);
                const $labelCell = $('<div class="rb-timeline-cell rb-timeline-cell--table" />');
                $labelCell.append('<div class="rb-table-heading"><span class="rb-table-name">' + this.settings.i18n.unassigned + '</span></div>');
                $labelCell.append('<div class="rb-table-meta">' + this.settings.i18n.statusLabel + ': ' + this.settings.i18n.noData + '</div>');
                $row.append($labelCell);

                this.timeSlots.forEach((slot, index) => {
                    const $cell = $('<div class="rb-timeline-cell rb-timeline-cell--slot is-readonly" />');
                    $cell.attr({
                        'data-time': slot,
                        'data-slot-index': index
                    });
                    $row.append($cell);
                });

                $body.append($row);
                this.tableMap.unassigned = {
                    table: null,
                    $row: $row
                };
            }

            $grid.append($body);
            this.$root.append($grid);

            this.renderBookings(data);
        }

        renderBookings(data) {
            this.$root.find('.rb-booking-block').remove();

            if (!data.bookings || !data.bookings.length) {
                return;
            }

            data.bookings.forEach((booking) => {
                this.bookingMap[booking.id] = booking;
                const rowKey = booking.table_number ? booking.table_number : 'unassigned';
                const rowData = this.tableMap[rowKey];
                if (!rowData || !rowData.$row) {
                    return;
                }

                const startIndex = this.getSlotIndex(booking.start);
                const span = this.getSpan(booking.start, booking.end);
                const columnStart = startIndex + 2;

                const statusClass = 'rb-booking--status-' + booking.status;
                const color = this.settings.statusColors[booking.status] || '#2271b1';
                const timeRange = this.formatTimeRange(booking.start, booking.end);
                const statusLabel = this.statusLabels[booking.status] || booking.status;

                const $block = $('<div class="rb-booking-block ' + statusClass + '" draggable="true" />')
                    .attr('data-booking-id', booking.id)
                    .attr('data-status', booking.status)
                    .css('grid-column', columnStart + ' / span ' + span);

                $block.css('background-color', color);

                $block.append('<div class="rb-booking-customer">' + booking.customer_name + '</div>');
                $block.append('<div class="rb-booking-meta">' + timeRange + ' • ' + this.settings.i18n.guestLabel + ': ' + booking.guest_count + '</div>');
                $block.append('<div class="rb-booking-status">' + statusLabel + '</div>');

                const $actions = this.buildBookingActions(booking);
                if ($actions) {
                    $block.append($actions);
                }

                $block.on('dragstart', (event) => this.handleDragStart(event, booking));
                $block.on('dragend', (event) => this.handleDragEnd(event));

                rowData.$row.append($block);
            });
        }

        buildBookingActions(booking) {
            const actions = [];

            if (booking.status === 'pending' || booking.status === 'confirmed') {
                actions.push('<button type="button" class="rb-booking-action rb-booking-action--checkin button-link">' + this.settings.i18n.checkIn + '</button>');
            }

            if (booking.status === 'checked_in') {
                actions.push('<button type="button" class="rb-booking-action rb-booking-action--checkout button-link">' + this.settings.i18n.checkOut + '</button>');
            }

            if (!actions.length) {
                return null;
            }

            return $('<div class="rb-booking-actions" />').html(actions.join(''));
        }

        handleDragStart(event, booking) {
            this.isDragging = true;
            this.draggedBookingId = booking.id;
            this.draggedBooking = booking;

            const originalEvent = event.originalEvent;
            if (originalEvent && originalEvent.dataTransfer) {
                originalEvent.dataTransfer.effectAllowed = 'move';
                originalEvent.dataTransfer.setData('text/plain', String(booking.id));
            }

            this.$root.addClass('is-dragging');
        }

        handleDragEnd() {
            this.isDragging = false;
            this.draggedBookingId = null;
            this.draggedBooking = null;
            this.$root.removeClass('is-dragging');
            this.$root.find('.rb-timeline-cell--slot.is-drop-target').removeClass('is-drop-target');
        }

        handleDragOver(event) {
            if (!this.draggedBookingId) {
                return;
            }

            event.preventDefault();
            event.originalEvent.dataTransfer.dropEffect = 'move';
        }

        handleDragEnter(event) {
            if (!this.draggedBookingId) {
                return;
            }

            const $cell = $(event.currentTarget);
            if ($cell.data('available')) {
                $cell.addClass('is-drop-target');
            }
        }

        handleDragLeave(event) {
            $(event.currentTarget).removeClass('is-drop-target');
        }

        handleDrop(event) {
            event.preventDefault();
            const $cell = $(event.currentTarget);
            $cell.removeClass('is-drop-target');

            if (!this.draggedBookingId) {
                return;
            }

            const data = $cell.data();
            if (!data || !data.available) {
                this.setStatus(this.settings.i18n.dragDisabled, 'warning');
                return;
            }

            this.moveBooking({
                bookingId: this.draggedBookingId,
                tableId: data.tableId,
                startTime: $cell.data('time')
            });
        }

        moveBooking(args) {
            if (!args.bookingId || !args.tableId || !args.startTime) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: this.settings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'rb_move_booking',
                    nonce: this.settings.nonce,
                    booking_id: args.bookingId,
                    table_id: args.tableId,
                    start_time: args.startTime,
                    date: this.currentDate,
                    location: this.currentLocation
                }
            }).done((response) => {
                if (response && response.success) {
                    const message = response.data && response.data.message ? response.data.message : this.settings.i18n.updated;
                    this.setStatus(message, 'success');
                    this.fetchData();
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : this.settings.i18n.error;
                    this.setStatus(message, 'error');
                }
            }).fail(() => {
                this.setStatus(this.settings.i18n.error, 'error');
            }).always(() => {
                this.hideLoading();
            });
        }

        toggleTable(tableId, status) {
            this.showLoading();

            $.ajax({
                url: this.settings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'rb_update_table_status',
                    nonce: this.settings.nonce,
                    table_id: tableId,
                    is_available: status
                }
            }).done((response) => {
                if (response && response.success) {
                    this.fetchData();
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : this.settings.i18n.error;
                    this.setStatus(message, 'error');
                }
            }).fail(() => {
                this.setStatus(this.settings.i18n.error, 'error');
            }).always(() => {
                this.hideLoading();
            });
        }

        handleCheckIn(bookingId) {
            if (!window.confirm(this.settings.i18n.checkInConfirm)) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: this.settings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'rb_timeline_check_in',
                    nonce: this.settings.nonce,
                    booking_id: bookingId
                }
            }).done((response) => {
                if (response && response.success) {
                    this.setStatus(response.data && response.data.message ? response.data.message : this.settings.i18n.updated, 'success');
                    this.fetchData();
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : this.settings.i18n.error;
                    this.setStatus(message, 'error');
                }
            }).fail(() => {
                this.setStatus(this.settings.i18n.error, 'error');
            }).always(() => {
                this.hideLoading();
            });
        }

        handleCheckOut(bookingId) {
            if (!window.confirm(this.settings.i18n.checkOutConfirm)) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: this.settings.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'rb_timeline_check_out',
                    nonce: this.settings.nonce,
                    booking_id: bookingId
                }
            }).done((response) => {
                if (response && response.success) {
                    this.setStatus(response.data && response.data.message ? response.data.message : this.settings.i18n.updated, 'success');
                    this.fetchData();
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : this.settings.i18n.error;
                    this.setStatus(message, 'error');
                }
            }).fail(() => {
                this.setStatus(this.settings.i18n.error, 'error');
            }).always(() => {
                this.hideLoading();
            });
        }

        openModal(booking) {
            const statusLabel = this.statusLabels[booking.status] || booking.status;
            const tableLabel = booking.table_number ? (this.settings.i18n.tableHeader + ' ' + booking.table_number) : this.settings.i18n.unassigned;
            const timeRange = this.formatTimeRange(booking.start, booking.end);

            const details = [
                '<li><strong>' + this.settings.i18n.tableHeader + ':</strong> ' + tableLabel + '</li>',
                '<li><strong>' + this.settings.i18n.guestLabel + ':</strong> ' + booking.guest_count + '</li>',
                '<li><strong>' + this.settings.i18n.timeLabel + ':</strong> ' + timeRange + '</li>',
                '<li><strong>' + this.settings.i18n.statusLabel + ':</strong> ' + statusLabel + '</li>'
            ];

            if (booking.booking_source) {
                details.push('<li><strong>' + this.settings.i18n.sourceLabel + ':</strong> ' + booking.booking_source + '</li>');
            }

            if (booking.special_requests) {
                details.push('<li><strong>' + this.settings.i18n.specialRequestLabel + ':</strong> ' + booking.special_requests + '</li>');
            }

            if (booking.admin_notes) {
                details.push('<li><strong>' + this.settings.i18n.notesLabel + ':</strong> ' + booking.admin_notes + '</li>');
            }

            const html = '<h2>' + this.settings.i18n.modalTitle + '</h2>'
                + '<p class="rb-modal-customer">' + booking.customer_name + '</p>'
                + '<ul class="rb-modal-details">' + details.join('') + '</ul>';

            this.$modalContent.html(html);
            this.$modal.attr('aria-hidden', 'false').addClass('is-visible');
        }

        closeModal() {
            this.$modal.attr('aria-hidden', 'true').removeClass('is-visible');
        }

        getSlotIndex(time) {
            const index = this.timeSlots.indexOf(time);
            if (index >= 0) {
                return index;
            }

            let fallback = 0;
            this.timeSlots.forEach((slot, i) => {
                if (slot <= time) {
                    fallback = i;
                }
            });
            return fallback;
        }

        getSpan(start, end) {
            const duration = this.getDurationMinutes(start, end);
            const interval = this.interval || 30;
            return Math.max(1, Math.round(duration / interval));
        }

        getDurationMinutes(start, end) {
            const startMinutes = this.timeToMinutes(start);
            const endMinutes = this.timeToMinutes(end);
            if (endMinutes <= startMinutes) {
                return this.blockDuration || this.interval || 30;
            }

            return endMinutes - startMinutes;
        }

        timeToMinutes(value) {
            const parts = (value || '').split(':');
            if (parts.length !== 2) {
                return 0;
            }

            const hours = parseInt(parts[0], 10);
            const minutes = parseInt(parts[1], 10);
            if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                return 0;
            }

            return (hours * 60) + minutes;
        }

        formatTimeRange(start, end) {
            return start + ' – ' + end;
        }

        getColumnTemplate() {
            return '200px repeat(' + this.timeSlots.length + ', minmax(110px, 1fr))';
        }

        parseDate(value) {
            if (!value) {
                return null;
            }

            const parts = value.split('-');
            if (parts.length !== 3) {
                return null;
            }

            const date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (Number.isNaN(date.getTime())) {
                return null;
            }

            return date;
        }

        formatDate(date) {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return date.getFullYear() + '-' + month + '-' + day;
        }

        setStatus(message, type = 'info') {
            if (!this.$status.length || !message) {
                return;
            }

            this.$status.removeClass('is-success is-error is-warning is-info');
            this.$status.text(message).addClass('is-' + type).show();
        }

        clearStatus() {
            if (!this.$status.length) {
                return;
            }

            this.$status.text('').removeClass('is-success is-error is-warning is-info').hide();
        }
    }

    $(function() {
        const $root = $('#rb-timeline-app');
        if (!$root.length || typeof window.rbTimelineData === 'undefined') {
            return;
        }

        const timeline = new RBTimeline($root, window.rbTimelineData);
        timeline.init();
    });
})(jQuery);
