<?php
/**
 * Main Campaign Manager class
 */

class Campaign_Manager {
    
    private $cpt;
    private $database;
    private $form_handler;
    private $admin;
    
    public function __construct() {
        $this->load_dependencies();
    }
    
    public function init() {
        $this->cpt = new Campaign_Manager_CPT();
        $this->database = new Campaign_Manager_Database();
        $this->form_handler = new Campaign_Manager_Form_Handler();
        $this->admin = new Campaign_Manager_Admin();
        
        $this->init_hooks();
        $this->enqueue_assets();
    }
    
    private function load_dependencies() {
        require_once CAMPAIGN_MANAGER_PLUGIN_DIR . 'includes/class-campaign-cpt.php';
        require_once CAMPAIGN_MANAGER_PLUGIN_DIR . 'includes/class-campaign-database.php';
        require_once CAMPAIGN_MANAGER_PLUGIN_DIR . 'includes/class-campaign-form-handler.php';
        require_once CAMPAIGN_MANAGER_PLUGIN_DIR . 'includes/class-campaign-admin.php';
        require_once CAMPAIGN_MANAGER_PLUGIN_DIR . 'admin/class-admin-dashboard.php';
    }
    
    private function init_hooks() {
        // Template hooks
        add_filter('single_template', array($this, 'load_campaign_template'));
        add_shortcode('campaign_form', array($this->form_handler, 'render_donation_form'));
        add_shortcode('campaign_button', array($this->form_handler, 'render_campaign_button'));
        add_shortcode('payment_summary', array($this->form_handler, 'render_payment_summary'));
        
        // Auto add button to campaign content
        add_filter('the_content', array($this, 'auto_add_campaign_button'));
    }
    
    public function enqueue_assets() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'campaign-manager-styles',
            CAMPAIGN_MANAGER_PLUGIN_URL . 'assets/css/campaign-styles.css',
            array(),
            CAMPAIGN_MANAGER_VERSION
        );
        
        wp_enqueue_script(
            'campaign-manager-scripts',
            CAMPAIGN_MANAGER_PLUGIN_URL . 'assets/js/campaign-scripts.js',
            array('jquery'),
            CAMPAIGN_MANAGER_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('campaign-manager-scripts', 'campaign_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('campaign_ajax_nonce')
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'campaign') !== false) {
            wp_enqueue_style(
                'campaign-manager-admin',
                CAMPAIGN_MANAGER_PLUGIN_URL . 'assets/css/campaign-admin.css',
                array(),
                CAMPAIGN_MANAGER_VERSION
            );
        }
    }
    
    public function load_campaign_template($template) {
        global $post;
        
        if ($post->post_type == 'campaign') {
            $plugin_template = CAMPAIGN_MANAGER_PLUGIN_DIR . 'templates/single-campaign.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    public function auto_add_campaign_button($content) {
        global $post;
        
        if (is_singular('campaign') && in_the_loop() && is_main_query()) {
            $button = do_shortcode('[campaign_button]');
            $content .= $button;
        }
        
        return $content;
    }
}