# Restaurant Booking Manager Plugin

Plugin WordPress quản lý đặt bàn nhà hàng hoàn chỉnh với CRM khách hàng, giao diện thân thiện và quản lý admin chuyên nghiệp.

## 📁 Cấu trúc thư mục

```
restaurant-booking-manager/
├── restaurant-booking-manager.php          # File plugin chính
├── includes/
│   ├── class-database.php                  # Quản lý cơ sở dữ liệu
│   ├── class-booking.php                   # Logic nghiệp vụ đặt bàn  
│   ├── class-customer.php                  # ⭐ Quản lý khách hàng CRM
│   ├── class-ajax.php                      # Xử lý AJAX requests
│   └── class-email.php                     # Gửi email tự động
├── admin/
│   └── class-admin.php                     # Giao diện admin
├── public/
│   └── class-frontend.php                  # Giao diện frontend
└── assets/
    ├── css/
    │   ├── frontend.css                    # CSS cho frontend
    │   └── admin.css                       # CSS cho admin
    └── js/
        ├── frontend.js                     # JavaScript frontend
        └── admin.js                        # JavaScript admin
```

## 🚀 Cài đặt

### Bước 1: Tạo thư mục plugin
```bash
wp-content/plugins/restaurant-booking-manager/
```

### Bước 2: Copy các file
- Tạo tất cả các file theo cấu trúc thư mục ở trên
- Copy code từ các artifacts vào đúng file tương ứng

### Bước 3: Kích hoạt plugin
1. Vào WordPress Admin > Plugins  
2. Tìm "Restaurant Booking Manager"
3. Click "Activate"

### Bước 4: Cấu hình cơ bản
1. Vào **Admin > Đặt bàn > Cài đặt**
2. Thiết lập:
   - Chế độ giờ làm việc (Simple/Advanced)
   - Giờ mở cửa/đóng cửa hoặc 2 ca (sáng/tối)
   - Thời gian đặt bàn tối thiểu/tối đa
   - Email thông báo

## 📝 Sử dụng

### Hiển thị form đặt bàn

**Shortcode cơ bản:**
```
[restaurant_booking]
```

**Shortcode tùy chỉnh:**
```
[restaurant_booking title="Đặt bàn ngay" button_text="Book Now" show_button="yes"]
```

**Form inline (không có modal):**
```
[restaurant_booking show_button="no"]
```

### Quản lý đặt bàn

1. **Dashboard:** Admin > Đặt bàn > Dashboard
   - 📊 Thống kê tổng quan (tổng, pending, confirmed, completed, cancelled, hôm nay)
   - 📈 KPI nguồn khách nhanh
   - 🔗 Lối tắt hành động chính (tạo đặt bàn, mở lịch phục vụ, xem báo cáo)

2. **Lịch phục vụ:** Admin > Đặt bàn > Lịch phục vụ
   - Theo dõi booking theo dòng thời gian trong ngày
   - Bộ lọc nhanh theo ca, khu vực, trạng thái
   - Thao tác nhanh: xác nhận, check-in, gán bàn, đánh dấu hoàn thành

3. **Danh sách đặt bàn:** Admin > Đặt bàn > Danh sách đặt bàn
   - Bảng toàn bộ booking với bộ lọc đa tiêu chí
   - Sắp xếp linh hoạt theo ngày tạo, ngày phục vụ, trạng thái, nguồn khách
   - Thống kê mini cho tổng, pending, confirmed, completed, cancelled

4. **Tạo đặt bàn (Admin):** Admin > Đặt bàn > Tạo đặt bàn
   - Tạo booking thủ công từ admin
   - Chọn nguồn booking (📞 Phone, 📘 Facebook, 💬 Zalo, 🚶 Walk-in...)
   - Ghi chú nội bộ (không hiển thị cho khách)
   - Tự động xác nhận hoặc để pending

5. **Quản lý bàn:** Admin > Đặt bàn > Quản lý bàn
   - Xem tình trạng tất cả bàn
   - Thêm/xóa/tạm ngưng bàn
   - Kích hoạt/vô hiệu hóa bàn

6. **⭐ Quản lý khách hàng (CRM):** Admin > Đặt bàn > Khách hàng
   - **Dashboard thống kê:**
     - Tổng khách hàng
     - Khách VIP
     - Blacklisted
     - Mới tháng này
   
   - **Gợi ý VIP tự động:**
     - Khách có ≥5 lượt hoàn thành → Gợi ý nâng VIP
   
   - **Cảnh báo khách có vấn đề:**
     - Khách có >30% tỷ lệ cancel/no-show
   
   - **Tính năng:**
     - Xem lịch sử đặt bàn chi tiết
     - Set VIP thủ công
     - Blacklist/Unblacklist
     - Tìm kiếm (tên/SĐT/email)
     - Lọc VIP, Blacklist
     - Sắp xếp theo bookings, completed, last visit...

7. **Báo cáo:** Admin > Đặt bàn > Báo cáo
   - Biểu đồ nguồn khách, hiệu suất ca, tỷ lệ hủy
   - Xuất nhanh dữ liệu theo bộ lọc
   - Báo cáo hành vi khách (VIP, khách quay lại)

8. **Cài đặt nâng cao:** Admin > Cấu hình Đặt bàn > Cài đặt (và các submenu)
   - **Submenu Giờ hoạt động:**
     - Simple mode: 1 ca (có thể có giờ nghỉ trưa)
     - Advanced mode: 2 ca riêng (sáng + tối)
     - Mở cửa cuối tuần

   - **Tab Đặt bàn:**
     - Khoảng thời gian slot (15/30/45/60 phút)
     - Buffer time giữa các booking
     - Đặt trước tối thiểu/tối đa
     - Số khách tối đa
     - Tự động xác nhận

   - **Submenu Thông báo:**
     - Email admin/khách hàng
     - Email nhắc lịch
     - SMS (cần API)

   - **Submenu Chính sách:**
     - Yêu cầu đặt cọc (cho booking ≥X khách)
     - Hủy miễn phí trước X giờ
     - Auto-blacklist sau X lần no-show
     - Ngày nghỉ đặc biệt (Tết, lễ...)

   - **Tab Nâng cao:**
     - Cleanup bookings cũ (>6 tháng)
     - Export CSV
     - Reset plugin (XÓA TẤT CẢ)

## 💻 Tính năng chính

### Frontend (Khách hàng)
- ✅ Modal đặt bàn responsive
- ✅ Kiểm tra bàn trống realtime  
- ✅ Form validation đầy đủ
- ✅ Thông báo trạng thái đặt bàn
- ✅ Tối ưu mobile/desktop
- ✅ Inline form (không modal)

### Backend (Admin)
- ✅ Dashboard quản lý trực quan với stats đầy đủ và shortcut thao tác
- ✅ Lịch phục vụ realtime với thao tác check-in/assign nhanh
- ✅ Danh sách đặt bàn có bộ lọc & sắp xếp nâng cao
- ✅ Báo cáo chuyên sâu về nguồn khách, hiệu suất ca
- ✅ Quản lý trạng thái bàn
- ✅ Email tự động HTML đẹp
- ✅ **Tạo booking từ admin** (Phone, Facebook, Zalo, Walk-in...)
- ✅ **Export CSV**

### 👥 Hệ thống CRM Khách hàng
- ✅ **Tự động cập nhật thông tin** khi có booking
- ✅ **Auto upgrade VIP** (≥5 lần hoàn thành)
- ✅ **Blacklist system** (khách có vấn đề)
- ✅ **Lịch sử chi tiết** từng khách hàng
- ✅ **Gợi ý VIP** dựa trên completed bookings
- ✅ **Cảnh báo problematic customers** (>30% cancel/no-show)
- ✅ **Thống kê toàn diện:** Total/Completed/Cancelled/No-shows
- ✅ **Search & Filter:** VIP, Blacklist, tên, SĐT, email

### Hệ thống Email
- ✅ Email thông báo admin khi có đặt bàn mới
- ✅ Email xác nhận cho khách hàng  
- ✅ Template HTML responsive đẹp mắt
- ✅ Thông tin đầy đủ (mã booking, ngày giờ, bàn số, yêu cầu đặc biệt)

### 📊 Booking Source Tracking
- 🌐 Website
- 📞 Điện thoại
- 📘 Facebook
- 💬 Zalo
- 📷 Instagram
- 🚶 Khách vãng lai
- ✉️ Email
- ❓ Khác

## 🔧 Customization

### Thay đổi giao diện
**CSS Frontend:**
```css
.rb-booking-widget {
    /* Tùy chỉnh widget đặt bàn */
}

.rb-modal {
    /* Tùy chỉnh modal */
}
```

**CSS Admin:**
```css
.rb-status {
    /* Tùy chỉnh trạng thái đặt bàn */
}
```

### Hooks và Filters

**Actions:**
```php
// Sau khi tạo đặt bàn thành công
do_action('rb_booking_created', $booking_id);

// Sau khi xác nhận đặt bàn
do_action('rb_booking_confirmed', $booking_id);

// Sau khi hủy đặt bàn
do_action('rb_booking_cancelled', $booking_id);

// ⭐ Khi khách được nâng cấp VIP
do_action('rb_customer_upgraded_vip', $customer);

// ⚠️ Khi phát hiện khách có vấn đề
do_action('rb_problematic_customer_detected', $customer);
```

**Filters:**
```php
// Tùy chỉnh email template
add_filter('rb_email_template', 'custom_email_template', 10, 2);

// Tùy chỉnh validation
add_filter('rb_booking_validation', 'custom_validation', 10, 2);
```

## 📊 Database Schema

### Bảng `wp_rb_bookings`
```sql
- id: ID đặt bàn
- customer_name: Tên khách hàng  
- customer_phone: Số điện thoại
- customer_email: Email
- guest_count: Số lượng khách
- booking_date: Ngày đặt
- booking_time: Giờ đặt  
- table_number: Số bàn
- status: Trạng thái (pending/confirmed/cancelled/completed/no-show)
- booking_source: Nguồn (website/phone/facebook/zalo/instagram/walk-in/email/other)
- special_requests: Yêu cầu đặc biệt
- admin_notes: Ghi chú nội bộ (admin only)
- created_at: Thời gian tạo
- confirmed_at: Thời gian xác nhận
- created_by: User ID (nếu tạo từ admin)
```

### Bảng `wp_rb_tables`  
```sql
- id: ID bàn
- table_number: Số bàn
- capacity: Sức chứa
- is_available: Có hoạt động không
- created_at: Thời gian tạo
```

### ⭐ Bảng `wp_rb_customers` (CRM)
```sql
- id: ID khách hàng
- phone: Số điện thoại (UNIQUE)
- email: Email
- name: Tên khách
- total_bookings: Tổng số lần đặt
- completed_bookings: Số lần hoàn thành
- cancelled_bookings: Số lần hủy
- no_shows: Số lần không đến
- vip_status: Trạng thái VIP (0/1)
- blacklisted: Bị cấm (0/1)
- first_visit: Lần đầu đến
- last_visit: Lần cuối đến
- preferred_source: Nguồn ưa thích
- customer_notes: Ghi chú về khách
- created_at: Ngày tạo
- updated_at: Cập nhật cuối
```

## 🔒 Bảo mật

- ✅ **Nonce verification** cho mọi AJAX request
- ✅ **Data sanitization** cho input
- ✅ **Permission checks** cho admin functions
- ✅ **SQL injection prevention** với prepared statements
- ✅ **XSS protection** với proper escaping

## 📱 Responsive Design

Plugin được thiết kế mobile-first:
- Modal tự động điều chỉnh kích thước
- Form layout responsive 
- Touch-friendly buttons
- Optimized cho mọi screen size

## 🚀 Tối ưu Performance  

- ✅ **AJAX loading** - Không reload trang
- ✅ **Lazy loading** - Load content khi cần
- ✅ **Caching friendly** - Tương thích cache plugins
- ✅ **Optimized queries** - Database queries hiệu quả
- ✅ **Auto cleanup** - Xóa bookings cũ >6 tháng

## 🔄 Tính năng mở rộng

### Đã có sẵn:
- 👥 **Customer CRM** - Quản lý khách hàng đầy đủ
- ⭐ **VIP Management** - Auto upgrade & thủ công
- 🚫 **Blacklist System** - Cấm khách có vấn đề
- 📊 **Source Tracking** - Theo dõi nguồn đặt bàn
- 📧 **Email Templates** - HTML responsive
- 🔧 **Advanced Settings** - 2 chế độ giờ làm việc, chính sách linh hoạt

### Tính năng có thể thêm sau:
- 💳 **Payment Integration** - Thanh toán online  
- 📱 **SMS Notifications** - Gửi SMS (đã có khung)
- 🎫 **QR Code Booking** - Mã QR cho đặt bàn
- 🔄 **Multi-location** - Nhiều chi nhánh
- 📅 **Google Calendar Sync** - Tích hợp lịch
- ⭐ **Reviews System** - Hệ thống đánh giá
- 🎯 **Loyalty Program** - Tích điểm khách hàng thân thiết

## ⚙️ Cấu hình nâng cao

### Working Hours Modes

**Simple Mode:**
- 1 ca làm việc liên tục
- Có thể bật giờ nghỉ trưa
- Thích hợp cho nhà hàng mở cửa suốt

**Advanced Mode:**
- 2 ca riêng biệt (sáng + tối)
- Tự động skip giờ nghỉ
- Thích hợp cho nhà hàng đóng cửa giữa trưa

### Booking Policies
- Đặt trước tối thiểu: X giờ
- Đặt trước tối đa: Y ngày
- Hủy miễn phí trước: Z giờ
- Đặt cọc cho booking ≥ N khách
- Auto-blacklist sau M lần no-show
- Ngày nghỉ đặc biệt (nhập theo dạng YYYY-MM-DD)

## 📞 Support & Troubleshooting

### Debug
1. Bật WordPress debug: `define('WP_DEBUG', true);`
2. Kiểm tra browser console cho lỗi JavaScript
3. Verify database tables đã được tạo:
   - `wp_rb_bookings`
   - `wp_rb_tables`
   - `wp_rb_customers`

### Common Issues
- **Không có bàn trống:** Kiểm tra trong "Quản lý bàn" xem có bàn nào `is_available = 1`
- **Email không gửi:** Kiểm tra SMTP settings của WordPress
- **Lỗi AJAX:** Verify nonce trong browser console

## 📄 License

GPL v2 or later

---

**Made with ❤️ for Vietnamese Restaurants**

**Version:** 1.0.0  
**Author:** [NewIT5S](https://github.com/newit5s)  
**Plugin URI:** https://github.com/newit5s/wp_booking-table
