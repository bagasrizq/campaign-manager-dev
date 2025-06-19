<?php
/**
 * Plugin Name: Campaign Manager
 * Description: Advanced campaign management system with donation forms and payment tracking
 * Version: 2.0
 * Author: Your Name
 * Text Domain: campaign-manager
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

// Define plugin constants
define('CAMPAIGN_MANAGER_VERSION', '2.0');
define('CAMPAIGN_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAMPAIGN_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CAMPAIGN_MANAGER_PLUGIN_FILE', __FILE__);

// Autoload classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'Campaign_Manager') === 0) {
        $class_file = str_replace('_', '-', strtolower($class));
        $file_path = CAMPAIGN_MANAGER_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

// Include required files
require_once CAMPAIGN_MANAGER_PLUGIN_DIR . 'includes/class-campaign-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-campaign-database.php';

// Initialize plugin
function campaign_manager_init() {
    $campaign_manager = new Campaign_Manager();
    $campaign_manager->init();
}
add_action('plugins_loaded', 'campaign_manager_init');

// Activation hook
register_activation_hook(__FILE__, array('Campaign_Manager_Database', 'create_tables'));

// Deactivation hook
register_deactivation_hook(__FILE__, 'campaign_manager_deactivate');
function campaign_manager_deactivate() {
    // Clean up if needed
}