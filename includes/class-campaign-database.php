<?php
/**
 * Campaign Database Handler
 */

class Campaign_Manager_Database {
    
    private static $table_name;
    
    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'campaign_submissions';
    }
    
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'campaign_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            campaign_id mediumint(9) NOT NULL,
            nama varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            no_hp varchar(20) NOT NULL,
            amount decimal(12,2) DEFAULT 0.00,
            payment_method varchar(50) DEFAULT 'manual',
            payment_id varchar(100) DEFAULT '',
            status enum('pending','completed','failed','cancelled') DEFAULT 'pending',
            notes text,
            submission_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY status (status),
            KEY submission_date (submission_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create payment logs table
        self::create_payment_logs_table();
    }
    
    private static function create_payment_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'campaign_payment_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            submission_id mediumint(9) NOT NULL,
            action varchar(50) NOT NULL,
            message text,
            log_data longtext,
            created_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY submission_id (submission_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function insert_submission($data) {
        global $wpdb;
        
        $default_data = array(
            'campaign_id' => 0,
            'nama' => '',
            'email' => '',
            'no_hp' => '',
            'amount' => 0,
            'payment_method' => 'manual',
            'payment_id' => '',
            'status' => 'pending',
            'notes' => ''
        );
        
        $data = wp_parse_args($data, $default_data);
        
        // Sanitize data
        $sanitized_data = array(
            'campaign_id' => intval($data['campaign_id']),
            'nama' => sanitize_text_field($data['nama']),
            'email' => sanitize_email($data['email']),
            'no_hp' => sanitize_text_field($data['no_hp']),
            'amount' => floatval($data['amount']),
            'payment_method' => sanitize_text_field($data['payment_method']),
            'payment_id' => sanitize_text_field($data['payment_id']),
            'status' => sanitize_text_field($data['status']),
            'notes' => sanitize_textarea_field($data['notes'])
        );
        
        $format = array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s');
        
        $result = $wpdb->insert(self::$table_name, $sanitized_data, $format);
        
        if ($result) {
            $submission_id = $wpdb->insert_id;
            $this->log_payment_action($submission_id, 'created', 'Submission created', $sanitized_data);
            return $submission_id;
        }
        
        return false;
    }
    
    public function update_submission($id, $data) {
        global $wpdb;
        
        $result = $wpdb->update(
            self::$table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            $this->log_payment_action($id, 'updated', 'Submission updated', $data);
        }
        
        return $result;
    }
    
    public function get_submission($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE id = %d",
            $id
        ));
    }
    
    public function get_submissions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'campaign_id' => null,
            'status' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'submission_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        if ($args['campaign_id']) {
            $where_clauses[] = "campaign_id = %d";
            $where_values[] = $args['campaign_id'];
        }
        
        if ($args['status']) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT * FROM " . self::$table_name . $where_sql . 
               " ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }
    
    public function get_campaign_stats($campaign_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_donations,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_donations,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_raised,
                AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_donation
            FROM " . self::$table_name . " WHERE campaign_id = %d",
            $campaign_id
        ));
        
        return $stats;
    }
    
    private function log_payment_action($submission_id, $action, $message, $data = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'campaign_payment_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'submission_id' => $submission_id,
                'action' => $action,
                'message' => $message,
                'log_data' => $data ? json_encode($data) : null
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'campaign_submissions';
    }
}