# Phase 1-2 Verification Checklist

Use this document to verify database migrations, backend logic, and data integrity before proceeding to phases 3-4. Each item links to the recommended manual or automated validation approach.

## Database Migration
- [ ] **Check `wp_rb_bookings` columns added**  
  Run `DESCRIBE wp_rb_bookings;` and confirm the presence of the new fields (`booking_source`, `language`, `location`, `admin_notes`, `created_by`).  
  _Tooling:_ `wp db query` or MySQL client.
- [ ] **Check `wp_rb_tables` columns added**  
  Run `DESCRIBE wp_rb_tables;` and confirm the `location` column exists with the expected default value.  
  _Tooling:_ `wp db query` or MySQL client.
- [ ] **Check indexes created**  
  Inspect the output of `SHOW INDEX FROM wp_rb_bookings;` to ensure indexes on `booking_date`, `status`, `booking_source`, and `location` exist.  
  _Tooling:_ `wp db query` or MySQL client.
- [ ] **Verify existing data migrated**  
  Query `SELECT COUNT(*) FROM wp_rb_bookings WHERE location IS NULL;` and ensure the result is `0`. Repeat for other backfilled columns as needed.

## Backend Logic
- [ ] **`check_time_overlap()` returns correct boolean**  
  Create unit tests (e.g., using [Codeception](https://codeception.com/for/wordpress)) that simulate overlapping and non-overlapping bookings and assert the return value.
- [ ] **`is_time_slot_available()` validates correctly**  
  Seed test data in `wp_rb_tables` and `wp_rb_bookings`, then invoke the method via a WordPress unit test or by temporarily exposing it through WP-CLI to ensure capacity checks respect `guest_count`, `status`, and `location`.
- [ ] **`get_timeline_data()` returns complete structure**  
  Execute the function and confirm the payload includes bookings grouped by time with associated table assignments and statuses for the requested date range.
- [ ] **`update_table_status()` updates DB correctly**  
  Trigger the related AJAX action (`rb_admin_toggle_table`) and verify `is_available` updates in `wp_rb_tables`. Check for proper sanitization of inputs.
- [ ] **`create_booking()` with checkin/checkout works**  
  Submit a backend booking form with check-in/out times (if applicable) and confirm database entries include the relevant timestamps and default values.
- [ ] **`confirm_booking()` updates table status**  
  Confirming a booking should assign the smallest available table and set the status to `confirmed`. Use database assertions to verify both the booking row and table availability.
- [ ] **`mark_checkin()` & `mark_checkout()` work**  
  Invoke these actions (via AJAX or admin UI) and assert that booking status and timestamps change appropriately without breaking existing flows.

## Data Integrity
- [ ] **Existing bookings still accessible**  
  Load historical bookings in the admin dashboard and ensure pagination, filters, and detail views work.
- [ ] **Existing functionality not broken**  
  Perform a smoke test of customer management, email notifications, and settings pages.
- [ ] **No SQL errors**  
  Enable `WP_DEBUG_LOG` and monitor the log while running the above checks.
- [ ] **No PHP warnings/notices**  
  Keep `WP_DEBUG` enabled and observe the debug log and browser console for warnings during manual tests.

> Tip: Capture screenshots or logs for each completed item to simplify reporting in subsequent phases.
