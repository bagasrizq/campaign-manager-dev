<?php
/**
 * Campaign Manager Uninstall Script
 * 
 * This file is called when the plugin is uninstalled via WordPress admin.
 * It removes all plugin data, tables, and options from the database.
 * 
 * @package Campaign_Manager
 * @version 2.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - make sure this is a legitimate uninstall request
if (!current_user_can('activate_plugins')) {
    return;
}

// Get the plugin file path
$plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
if ($plugin !== plugin_basename(__FILE__)) {
    return;
}

/**
 * Remove all plugin data and clean up
 */
class Campaign_Manager_Uninstaller {
    
    /**
     * Run the uninstall process
     */
    public static function uninstall() {
        global $wpdb;
        
        // Delete all campaign posts
        self::delete_campaign_posts();
        
        // Drop custom tables
        self::drop_custom_tables();
        
        // Delete plugin options
        self::delete_plugin_options();
        
        // Delete user meta
        self::delete_user_meta();
        
        // Delete transients
        self::delete_transients();
        
        // Delete uploaded files
        self::delete_uploaded_files();
        
        // Clear any cached data
        self::clear_cache();
        
        // Log the uninstall (optional)
        self::log_uninstall();
    }
    
    /**
     * Delete all campaign posts and their meta data
     */
    private static function delete_campaign_posts() {
        global $wpdb;
        
        // Get all campaign posts
        $campaign_posts = get_posts(array(
            'post_type' => 'campaign',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        if (!empty($campaign_posts)) {
            foreach ($campaign_posts as $post) {
                // Delete post meta
                $wpdb->delete(
                    $wpdb->postmeta,
                    array('post_id' => $post->ID),
                    array('%d')
                );
                
                // Delete the post
                wp_delete_post($post->ID, true);
            }
        }
        
        // Delete any orphaned meta data
        $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.ID IS NULL
        ");
    }
    
    /**
     * Drop all custom database tables
     */
    private static function drop_custom_tables() {
        global $wpdb;
        
        $tables_to_drop = array(
            $wpdb->prefix . 'campaign_submissions',
            $wpdb->prefix . 'campaign_payment_logs'
        );
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
    
    /**
     * Delete all plugin options
     */
    private static function delete_plugin_options() {
        $options_to_delete = array(
            'campaign_manager_options',
            'campaign_manager_version',
            'campaign_manager_db_version',
            'campaign_manager_install_date',
            'campaign_manager_activation_redirect'
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
            // Also delete from site options in case of multisite
            delete_site_option($option);
        }
        
        // Delete any options that start with our prefix
        global $wpdb;
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE 'campaign_manager_%'
        ");
        
        // For multisite installations
        if (is_multisite()) {
            $wpdb->query("
                DELETE FROM {$wpdb->sitemeta} 
                WHERE meta_key LIKE 'campaign_manager_%'
            ");
        }
    }
    
    /**
     * Delete user meta data related to the plugin
     */
    private static function delete_user_meta() {
        global $wpdb;
        
        $user_meta_keys = array(
            'campaign_manager_dashboard_dismissed_notices',
            'campaign_manager_user_preferences',
            'campaign_manager_last_seen'
        );
        
        foreach ($user_meta_keys as $meta_key) {
            delete_metadata('user', 0, $meta_key, '', true);
        }
        
        // Delete any user meta that starts with our prefix
        $wpdb->query("
            DELETE FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE 'campaign_manager_%'
        ");
    }
    
    /**
     * Delete all plugin transients
     */
    private static function delete_transients() {
        global $wpdb;
        
        // Delete transients with our prefix
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_campaign_manager_%' 
            OR option_name LIKE '_transient_timeout_campaign_manager_%'
        ");
        
        // For multisite installations
        if (is_multisite()) {
            $wpdb->query("
                DELETE FROM {$wpdb->sitemeta} 
                WHERE meta_key LIKE '_transient_campaign_manager_%' 
                OR meta_key LIKE '_transient_timeout_campaign_manager_%'
            ");
        }
    }
    
    /**
     * Delete uploaded files and directories
     */
    private static function delete_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $campaign_dir = $upload_dir['basedir'] . '/campaign-manager/';
        
        if (is_dir($campaign_dir)) {
            self::recursive_rmdir($campaign_dir);
        }
        
        // Also check for files in the general uploads directory
        $pattern = $upload_dir['basedir'] . '/campaign-*';
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    self::recursive_rmdir($file);
                }
            }
        }
    }
    
    /**
     * Recursively remove directory and its contents
     */
    private static function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $file_path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file_path)) {
                self::recursive_rmdir($file_path);
            } else {
                unlink($file_path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Clear any cached data
     */
    private static function clear_cache() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear any plugin-specific cache
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group('campaign_manager');
        }
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Clear any external cache plugins
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }
    
    /**
     * Log the uninstall event
     */
    private static function log_uninstall() {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_email' => wp_get_current_user()->user_email,
            'site_url' => get_site_url(),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
        
        // Write to error log for debugging purposes
        error_log('Campaign Manager Plugin Uninstalled: ' . json_encode($log_data));
        
        // Optional: Send analytics data (if user opted in)
        // This should only be done if the user previously consented
        $send_analytics = get_option('campaign_manager_send_analytics', false);
        if ($send_analytics) {
            wp_remote_post('https://api.yoursite.com/plugin-uninstall', array(
                'body' => $log_data,
                'timeout' => 10,
                'blocking' => false
            ));
        }
    }
    
    /**
     * Additional cleanup for multisite installations
     */
    private static function multisite_cleanup() {
        if (!is_multisite()) {
            return;
        }
        
        global $wpdb;
        
        // Get all sites in the network
        $sites = get_sites(array('number' => 0));
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            // Perform cleanup for each site
            self::delete_campaign_posts();
            self::delete_plugin_options();
            self::delete_user_meta();
            self::delete_transients();
            self::delete_uploaded_files();
            
            restore_current_blog();
        }
        
        // Clean up network-wide options
        delete_site_option('campaign_manager_network_settings');
    }
    
    /**
     * Create uninstall feedback opportunity
     */
    private static function create_feedback_opportunity() {
        // Set a transient that will show feedback form on next admin visit
        // This should only be shown to users who can manage plugins
        if (current_user_can('manage_options')) {
            set_transient('campaign_manager_show_feedback', true, DAY_IN_SECONDS);
        }
    }
}

// Run the uninstall process
try {
    Campaign_Manager_Uninstaller::uninstall();
    
    // Handle multisite installations
    if (is_multisite()) {
        Campaign_Manager_Uninstaller::multisite_cleanup();
    }
    
    // Optional: Create feedback opportunity
    // Campaign_Manager_Uninstaller::create_feedback_opportunity();
    
} catch (Exception $e) {
    // Log any errors during uninstall
    error_log('Campaign Manager Uninstall Error: ' . $e->getMessage());
    
    // Don't let uninstall fail completely, but note the error
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_die('Campaign Manager uninstall encountered an error: ' . $e->getMessage());
    }
}

// Final cleanup - remove any remaining traces
wp_cache_flush();

// Optional: Add a small delay to ensure all operations complete
// usleep(100000); // 100ms delay