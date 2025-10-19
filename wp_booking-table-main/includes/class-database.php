<?php
/**
 * Database Class - Tạo và quản lý database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class RB_Database {
    
    private $wpdb;
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $this->create_bookings_table();
        $this->create_tables_table();
        $this->create_customers_table();
        $this->insert_default_tables();
        $this->add_booking_source_column();
    }
    
    private function create_bookings_table() {
        $table_name = $this->wpdb->prefix . 'rb_bookings';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            customer_name varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_email varchar(100) NOT NULL,
            guest_count int(11) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            table_number int(11) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            booking_source varchar(50) DEFAULT 'website',
            language varchar(10) DEFAULT 'vi',
            location varchar(50) DEFAULT 'vn',
            special_requests text DEFAULT NULL,
            admin_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            confirmed_at datetime DEFAULT NULL,
            created_by int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY booking_date (booking_date),
            KEY status (status),
            KEY booking_source (booking_source),
            KEY location (location)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_tables_table() {
        $table_name = $this->wpdb->prefix . 'rb_tables';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            table_number int(11) NOT NULL,
            capacity int(11) NOT NULL,
            location varchar(50) DEFAULT 'vn',
            is_available tinyint(1) DEFAULT 1,
            current_status varchar(20) DEFAULT 'available',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY table_number (table_number)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_customers_table() {
        $table_name = $this->wpdb->prefix . 'rb_customers';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            phone varchar(20) UNIQUE NOT NULL,
            email varchar(100),
            name varchar(100),
            total_bookings int DEFAULT 0,
            completed_bookings int DEFAULT 0,
            cancelled_bookings int DEFAULT 0,
            no_shows int DEFAULT 0,
            last_visit date,
            first_visit date,
            customer_notes text,
            vip_status tinyint(1) DEFAULT 0,
            blacklisted tinyint(1) DEFAULT 0,
            preferred_source varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY email (email)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    public function add_booking_source_column() {
        $table_name = $this->wpdb->prefix . 'rb_bookings';

        if (!$this->column_exists($table_name, 'booking_source')) {
            $this->wpdb->query(
                "ALTER TABLE $table_name
                ADD COLUMN booking_source varchar(50) DEFAULT 'website' AFTER status"
            );
        }

        if (!$this->column_exists($table_name, 'language')) {
            $this->wpdb->query(
                "ALTER TABLE $table_name
                ADD COLUMN language varchar(10) DEFAULT 'vi' AFTER booking_source"
            );
        }

        if (!$this->column_exists($table_name, 'location')) {
            $this->wpdb->query(
                "ALTER TABLE $table_name
                ADD COLUMN location varchar(50) DEFAULT 'vn' AFTER language"
            );
            $this->wpdb->query(
                "UPDATE $table_name SET location = 'vn' WHERE location IS NULL OR location = ''"
            );
        }

        if (!$this->column_exists($table_name, 'admin_notes')) {
            $this->wpdb->query(
                "ALTER TABLE $table_name
                ADD COLUMN admin_notes text DEFAULT NULL AFTER special_requests"
            );
        }

        if (!$this->column_exists($table_name, 'created_by')) {
            $this->wpdb->query(
                "ALTER TABLE $table_name
                ADD COLUMN created_by int(11) DEFAULT NULL AFTER confirmed_at"
            );
        }

        $tables_table = $this->wpdb->prefix . 'rb_tables';

        if (!$this->column_exists($tables_table, 'location')) {
            $this->wpdb->query(
                "ALTER TABLE $tables_table
                ADD COLUMN location varchar(50) DEFAULT 'vn' AFTER capacity"
            );
            $this->wpdb->query(
                "UPDATE $tables_table SET location = 'vn' WHERE location IS NULL OR location = ''"
            );
        }

        if (!$this->column_exists($tables_table, 'current_status')) {
            $this->wpdb->query(
                "ALTER TABLE $tables_table"
                . " ADD COLUMN current_status varchar(20) DEFAULT 'available' AFTER is_available"
            );
            $this->wpdb->query(
                "UPDATE $tables_table SET current_status = 'available' WHERE current_status IS NULL OR current_status = ''"
            );
        }
    }

    private function column_exists($table, $column) {
        $column = sanitize_key($column);
        $table = esc_sql($table);
        $query = $this->wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column);
        $result = $this->wpdb->get_results($query);
        return !empty($result);
    }
    
    private function insert_default_tables() {
        $table_name = $this->wpdb->prefix . 'rb_tables';
        
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            for ($i = 1; $i <= 10; $i++) {
                $this->wpdb->insert(
                    $table_name,
                    array(
                        'table_number' => $i,
                        'capacity' => ($i <= 4) ? 2 : (($i <= 8) ? 4 : 6),
                        'location' => 'vn',
                        'is_available' => 1,
                        'current_status' => 'available',
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%d', '%s', '%s')
                );
            }
        }
    }
    
    public function drop_tables() {
        $tables = array(
            $this->wpdb->prefix . 'rb_bookings',
            $this->wpdb->prefix . 'rb_tables',
            $this->wpdb->prefix . 'rb_customers'
        );
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}