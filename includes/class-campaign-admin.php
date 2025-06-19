<?php
/**
 * Campaign Admin Handler
 */

class Campaign_Manager_Admin {
    
    private $database;
    
    public function __construct() {
        $this->database = new Campaign_Manager_Database();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_head', array($this, 'admin_css'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_campaign_export_data', array($this, 'export_submissions'));
        add_action('wp_ajax_campaign_update_submission_status', array($this, 'update_submission_status'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=campaign',
            __('Campaign Dashboard', 'campaign-manager'),
            __('Dashboard', 'campaign-manager'),
            'manage_options',
            'campaign-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=campaign',
            __('Donations', 'campaign-manager'),
            __('Donations', 'campaign-manager'),
            'manage_options',
            'campaign-donations',
            array($this, 'donations_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=campaign',
            __('Settings', 'campaign-manager'),
            __('Settings', 'campaign-manager'),
            'manage_options',
            'campaign-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_init() {
        register_setting('campaign_manager_settings', 'campaign_manager_options');
        
        add_settings_section(
            'campaign_general_settings',
            __('General Settings', 'campaign-manager'),
            null,
            'campaign_manager_settings'
        );
        
        add_settings_field(
            'default_currency',
            __('Default Currency', 'campaign-manager'),
            array($this, 'currency_field_callback'),
            'campaign_manager_settings',
            'campaign_general_settings'
        );
        
        add_settings_field(
            'email_notifications',
            __('Email Notifications', 'campaign-manager'),
            array($this, 'email_notifications_callback'),
            'campaign_manager_settings',
            'campaign_general_settings'
        );
    }
    
    public function dashboard_page() {
        $dashboard = new Campaign_Manager_Admin_Dashboard();
        $dashboard->render();
    }
    
    public function donations_page() {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;
        
        $filter_args = array(
            'limit' => $per_page,
            'offset' => $offset
        );
        
        // Apply filters
        if (isset($_GET['campaign_id']) && $_GET['campaign_id']) {
            $filter_args['campaign_id'] = intval($_GET['campaign_id']);
        }
        
        if (isset($_GET['status']) && $_GET['status']) {
            $filter_args['status'] = sanitize_text_field($_GET['status']);
        }
        
        $submissions = $this->database->get_submissions($filter_args);
        $campaigns = get_posts(array('post_type' => 'campaign', 'numberposts' => -1));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Donations Management', 'campaign-manager'); ?></h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="post_type" value="campaign">
                    <input type="hidden" name="page" value="campaign-donations">
                    
                    <select name="campaign_id">
                        <option value=""><?php _e('All Campaigns', 'campaign-manager'); ?></option>
                        <?php foreach ($campaigns as $campaign): ?>
                            <option value="<?php echo $campaign->ID; ?>" <?php selected(isset($_GET['campaign_id']) ? $_GET['campaign_id'] : '', $campaign->ID); ?>>
                                <?php echo esc_html($campaign->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status">
                        <option value=""><?php _e('All Status', 'campaign-manager'); ?></option>
                        <option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>><?php _e('Pending', 'campaign-manager'); ?></option>
                        <option value="completed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'completed'); ?>><?php _e('Completed', 'campaign-manager'); ?></option>
                        <option value="failed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'failed'); ?>><?php _e('Failed', 'campaign-manager'); ?></option>
                        <option value="cancelled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'cancelled'); ?>><?php _e('Cancelled', 'campaign-manager'); ?></option>
                    </select>
                    
                    <input type="submit" class="button" value="<?php _e('Filter', 'campaign-manager'); ?>">
                    <a href="<?php echo admin_url('admin.php?post_type=campaign&page=campaign-donations'); ?>" class="button"><?php _e('Reset', 'campaign-manager'); ?></a>
                </form>
                
                <div class="alignright">
                    <button type="button" class="button button-primary" id="export-donations">
                        <?php _e('Export CSV', 'campaign-manager'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Donations Table -->
            <?php $this->render_donations_table($submissions); ?>
            
            <!-- Pagination -->
            <?php $this->render_pagination($current_page, $per_page); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#export-donations').click(function() {
                var params = new URLSearchParams(window.location.search);
                params.set('action', 'campaign_export_data');
                window.location.href = ajaxurl + '?' + params.toString();
            });
            
            $('.status-select').change(function() {
                var submissionId = $(this).data('submission-id');
                var newStatus = $(this).val();
                
                $.post(ajaxurl, {
                    action: 'campaign_update_submission_status',
                    submission_id: submissionId,
                    status: newStatus,
                    nonce: '<?php echo wp_create_nonce('campaign_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error updating status');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    private function render_donations_table($submissions) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'campaign-manager'); ?></th>
                    <th><?php _e('Name', 'campaign-manager'); ?></th>
                    <th><?php _e('Email', 'campaign-manager'); ?></th>
                    <th><?php _e('Phone', 'campaign-manager'); ?></th>
                    <th><?php _e('Amount', 'campaign-manager'); ?></th>
                    <th><?php _e('Campaign', 'campaign-manager'); ?></th>
                    <th><?php _e('Status', 'campaign-manager'); ?></th>
                    <th><?php _e('Date', 'campaign-manager'); ?></th>
                    <th><?php _e('Actions', 'campaign-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;"><?php _e('No donations found', 'campaign-manager'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?php echo $submission->id; ?></td>
                            <td><?php echo esc_html($submission->nama); ?></td>
                            <td><?php echo esc_html($submission->email); ?></td>
                            <td><?php echo esc_html($submission->no_hp); ?></td>
                            <td>Rp <?php echo isset($submission->amount) ? number_format($submission->amount, 0, ',', '.') : '0'; ?></td>
                            <td>
                                <?php 
                                $campaign = get_post($submission->campaign_id);
                                echo $campaign ? esc_html($campaign->post_title) : __('Deleted Campaign', 'campaign-manager');
                                ?>
                            </td>
                            <td>
                                <select class="status-select" data-submission-id="<?php echo $submission->id; ?>">
                                    <option value="pending" <?php selected($submission->status, 'pending'); ?>><?php _e('Pending', 'campaign-manager'); ?></option>
                                    <option value="completed" <?php selected($submission->status, 'completed'); ?>><?php _e('Completed', 'campaign-manager'); ?></option>
                                    <option value="failed" <?php selected($submission->status, 'failed'); ?>><?php _e('Failed', 'campaign-manager'); ?></option>
                                    <option value="cancelled" <?php selected($submission->status, 'cancelled'); ?>><?php _e('Cancelled', 'campaign-manager'); ?></option>
                                </select>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($submission->submission_date)); ?></td>
                            <td>
                                <a href="#" class="button button-small view-details" data-submission-id="<?php echo $submission->id; ?>">
                                    <?php _e('View', 'campaign-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Campaign Manager Settings', 'campaign-manager'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('campaign_manager_settings');
                do_settings_sections('campaign_manager_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function currency_field_callback() {
        $options = get_option('campaign_manager_options');
        $currency = isset($options['default_currency']) ? $options['default_currency'] : 'IDR';
        ?>
        <select name="campaign_manager_options[default_currency]">
            <option value="IDR" <?php selected($currency, 'IDR'); ?>>Indonesian Rupiah (IDR)</option>
            <option value="USD" <?php selected($currency, 'USD'); ?>>US Dollar (USD)</option>
        </select>
        <?php
    }
    
    public function email_notifications_callback() {
        $options = get_option('campaign_manager_options');
        $enabled = isset($options['email_notifications']) ? $options['email_notifications'] : 1;
        ?>
        <input type="checkbox" name="campaign_manager_options[email_notifications]" value="1" <?php checked($enabled, 1); ?>>
        <label><?php _e('Send email notifications to donors', 'campaign-manager'); ?></label>
        <?php
    }
    
    public function export_submissions() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $submissions = $this->database->get_submissions(array('limit' => -1));
        
        $filename = 'campaign-donations-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array(
            'ID', 'Name', 'Email', 'Phone', 'Amount', 'Campaign', 'Status', 'Date', 'Payment Method', 'Payment ID'
        ));
        
        // CSV Data
        foreach ($submissions as $submission) {
            $campaign = get_post($submission->campaign_id);
            fputcsv($output, array(
                $submission->id,
                $submission->nama,
                $submission->email,
                $submission->no_hp,
                $submission->amount,
                $campaign ? $campaign->post_title : 'Deleted Campaign',
                $submission->status,
                $submission->submission_date,
                $submission->payment_method,
                $submission->payment_id
            ));
        }
        
        fclose($output);
        exit;
    }
    
    public function update_submission_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'campaign_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Security check failed');
        }
        
        $submission_id = intval($_POST['submission_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $result = $this->database->update_submission($submission_id, array('status' => $status));
        
        if ($result !== false) {
            wp_send_json_success('Status updated successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    private function render_pagination($current_page, $per_page) {
        // Implement pagination logic here
        // This is a simplified version
    }
    
    public function admin_css() {
        ?>
        <style>
            .campaign-stats { padding: 15px; background: #f9f9f9; margin: 10px 0; }
            .progress-bar { width: 100%; height: 20px; background: #f1f1f1; border-radius: 10px; overflow: hidden; margin-top: 10px; }
            .progress-fill { height: 100%; background: linear-gradient(90deg, #4CAF50, #45a049); transition: width 0.3s ease; }
            .status-select { width: 100px; }
            .wp-list-table th, .wp-list-table td { padding: 8px; }
            .tablenav { margin: 10px 0; }
            .tablenav .alignright { float: right; }
        </style>
        <?php
    }
}