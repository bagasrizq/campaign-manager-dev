<?php
/**
 * Campaign Form Handler
 */

class Campaign_Manager_Form_Handler {
    
    private $database;
    
    public function __construct() {
        $this->database = new Campaign_Manager_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_campaign_submit_form', array($this, 'ajax_submit_form'));
        add_action('wp_ajax_nopriv_campaign_submit_form', array($this, 'ajax_submit_form'));
        add_action('wp_ajax_campaign_update_payment', array($this, 'ajax_update_payment'));
        add_action('wp_ajax_nopriv_campaign_update_payment', array($this, 'ajax_update_payment'));
    }
    
    public function render_campaign_button($atts = array()) {
        global $post;
        
        $atts = shortcode_atts(array(
            'campaign_id' => $post ? $post->ID : 0,
            'show_progress' => 'true',
            'button_text' => __('Donate Now', 'campaign-manager'),
            'template' => 'default'
        ), $atts);
        
        $campaign_id = intval($atts['campaign_id']);
        
        if (!$campaign_id) {
            return '<div class="campaign-error">' . __('Invalid campaign ID', 'campaign-manager') . '</div>';
        }
        
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_type !== 'campaign') {
            return '<div class="campaign-error">' . __('Campaign not found', 'campaign-manager') . '</div>';
        }
        
        // Get campaign data
        $target = get_post_meta($campaign_id, '_campaign_target', true);
        $deadline = get_post_meta($campaign_id, '_campaign_deadline', true);
        $status = get_post_meta($campaign_id, '_campaign_status', true) ?: 'active';
        $currency = get_post_meta($campaign_id, '_campaign_currency', true) ?: 'IDR';
        
        // Get campaign stats
        $stats = $this->database->get_campaign_stats($campaign_id);
        
        // Check if campaign is active and not expired
        $is_expired = strtotime($deadline) < time();
        $is_active = ($status === 'active' && !$is_expired);
        
        // Load template
        ob_start();
        $this->load_template('single-campaign', array(
            'campaign' => $campaign,
            'target' => $target,
            'deadline' => $deadline,
            'status' => $status,
            'currency' => $currency,
            'stats' => $stats,
            'is_active' => $is_active,
            'is_expired' => $is_expired,
            'button_text' => $atts['button_text'],
            'show_progress' => $atts['show_progress'] === 'true'
        ));
        
        return ob_get_clean();
    }
    
    public function render_donation_form($atts = array()) {
        $atts = shortcode_atts(array(
            'campaign_id' => isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0,
            'redirect_url' => '',
            'show_amount_options' => 'true'
        ), $atts);
        
        $campaign_id = $atts['campaign_id'];
        
        if (!$campaign_id) {
            return '<div class="campaign-error">' . __('Invalid campaign access. Please use the donation button.', 'campaign-manager') . '</div>';
        }
        
        $campaign = get_post($campaign_id);
        if (!$campaign || $campaign->post_type !== 'campaign') {
            return '<div class="campaign-error">' . __('Campaign not found!', 'campaign-manager') . '</div>';
        }
        
        // Check if campaign is still active
        $deadline = get_post_meta($campaign_id, '_campaign_deadline', true);
        $status = get_post_meta($campaign_id, '_campaign_status', true) ?: 'active';
        
        if ($status !== 'active' || strtotime($deadline) < time()) {
            return '<div class="campaign-error">' . __('This campaign is no longer accepting donations.', 'campaign-manager') . '</div>';
        }
        
        // Process form submission
        $form_message = '';
        if (isset($_POST['submit_donation_form']) && wp_verify_nonce($_POST['donation_form_nonce'], 'submit_donation_' . $campaign_id)) {
            $result = $this->process_donation_form($campaign_id);
            if ($result['success']) {
                $redirect_url = add_query_arg(array(
                    'submission_id' => $result['submission_id'],
                    'campaign_id' => $campaign_id
                ), $atts['redirect_url'] ?: get_permalink());
                
                if ($atts['redirect_url']) {
                    wp_redirect($redirect_url);
                    exit;
                } else {
                    $form_message = '<div class="campaign-success">' . $result['message'] . '</div>';
                }
            } else {
                $form_message = '<div class="campaign-error">' . $result['message'] . '</div>';
            }
        }
        
        $currency = get_post_meta($campaign_id, '_campaign_currency', true) ?: 'IDR';
        
        ob_start();
        echo $form_message;
        $this->load_template('form-donation', array(
            'campaign' => $campaign,
            'campaign_id' => $campaign_id,
            'currency' => $currency,
            'show_amount_options' => $atts['show_amount_options'] === 'true'
        ));
        
        return ob_get_clean();
    }
    
    public function render_payment_summary($atts = array()) {
        $atts = shortcode_atts(array(
            'submission_id' => isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0,
            'campaign_id' => isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0
        ), $atts);
        
        if (!$atts['submission_id']) {
            return '<div class="campaign-error">' . __('Invalid submission ID', 'campaign-manager') . '</div>';
        }
        
        $submission = $this->database->get_submission($atts['submission_id']);
        if (!$submission) {
            return '<div class="campaign-error">' . __('Submission not found', 'campaign-manager') . '</div>';
        }
        
        $campaign = get_post($submission->campaign_id);
        if (!$campaign) {
            return '<div class="campaign-error">' . __('Campaign not found', 'campaign-manager') . '</div>';
        }
        
        ob_start();
        $this->load_template('payment-summary', array(
            'submission' => $submission,
            'campaign' => $campaign
        ));
        
        return ob_get_clean();
    }
    
    private function process_donation_form($campaign_id) {
        // Validate required fields
        $required_fields = array('nama', 'email', 'no_hp', 'amount');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Field %s is required', 'campaign-manager'), $field)
                );
            }
        }
        
        // Validate email
        if (!is_email($_POST['email'])) {
            return array(
                'success' => false,
                'message' => __('Please enter a valid email address', 'campaign-manager')
            );
        }
        
        // Validate phone number
        if (!preg_match('/^[0-9+\-\s()]+$/', $_POST['no_hp'])) {
            return array(
                'success' => false,
                'message' => __('Please enter a valid phone number', 'campaign-manager')
            );
        }
        
        // Validate amount
        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            return array(
                'success' => false,
                'message' => __('Please enter a valid donation amount', 'campaign-manager')
            );
        }
        
        // Prepare data for insertion
        $submission_data = array(
            'campaign_id' => $campaign_id,
            'nama' => $_POST['nama'],
            'email' => $_POST['email'],
            'no_hp' => $_POST['no_hp'],
            'amount' => $amount,
            'payment_method' => isset($_POST['payment_method']) ? $_POST['payment_method'] : 'manual',
            'notes' => isset($_POST['notes']) ? $_POST['notes'] : '',
            'status' => 'pending'
        );
        
        $submission_id = $this->database->insert_submission($submission_data);
        
        if ($submission_id) {
            // Send email notification (optional)
            $this->send_donation_confirmation($submission_id);
            
            return array(
                'success' => true,
                'message' => __('Thank you for your donation! Your submission has been recorded.', 'campaign-manager'),
                'submission_id' => $submission_id
            );
        } else {
            error_log('Campaign donation submission failed for campaign ID: ' . $campaign_id);
            return array(
                'success' => false,
                'message' => __('An error occurred while processing your donation. Please try again.', 'campaign-manager')
            );
        }
    }
    
    public function ajax_submit_form() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'campaign_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $result = $this->process_donation_form($campaign_id);
        
        wp_send_json($result);
    }
    
    public function ajax_update_payment() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'campaign_ajax_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        $submission_id = intval($_POST['submission_id']);
        $status = sanitize_text_field($_POST['status']);
        $payment_id = sanitize_text_field($_POST['payment_id']);
        
        $result = $this->database->update_submission($submission_id, array(
            'status' => $status,
            'payment_id' => $payment_id
        ));
        
        if ($result !== false) {
            wp_send_json_success('Payment updated successfully');
        } else {
            wp_send_json_error('Failed to update payment');
        }
    }
    
    private function send_donation_confirmation($submission_id) {
        $submission = $this->database->get_submission($submission_id);
        if (!$submission) return false;
        
        $campaign = get_post($submission->campaign_id);
        if (!$campaign) return false;
        
        $subject = sprintf(__('Donation Confirmation - %s', 'campaign-manager'), $campaign->post_title);
        
        $message = sprintf(
            __('Dear %s,

Thank you for your donation to "%s".

Donation Details:
- Amount: %s
- Campaign: %s
- Date: %s
- Transaction ID: %s

Your donation is currently being processed. You will receive another email once the payment is confirmed.

Thank you for your support!', 'campaign-manager'),
            $submission->nama,
            $campaign->post_title,
            'Rp ' . number_format($submission->amount, 0, ',', '.'),
            $campaign->post_title,
            date('d F Y H:i', strtotime($submission->submission_date)),
            $submission->id
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($submission->email, $subject, nl2br($message), $headers);
    }
    
    private function load_template($template_name, $vars = array()) {
        $template_path = CAMPAIGN_MANAGER_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            extract($vars);
            include $template_path;
        } else {
            echo '<div class="campaign-error">Template not found: ' . $template_name . '</div>';
        }
    }
}