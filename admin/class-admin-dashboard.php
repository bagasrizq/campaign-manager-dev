<?php
/**
 * Admin Dashboard Handler
 */

class Campaign_Manager_Admin_Dashboard {
    
    private $database;
    
    public function __construct() {
        $this->database = new Campaign_Manager_Database();
    }
    
    public function render() {
        $this->render_stats_overview();
        $this->render_recent_donations();
        $this->render_campaign_overview();
    }
    
    private function render_stats_overview() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'campaign_submissions';
        
        // Get overall stats
        $total_campaigns = wp_count_posts('campaign')->publish;
        $total_donations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $completed_donations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $total_raised = $wpdb->get_var("SELECT SUM(amount) FROM $table_name WHERE status = 'completed'") ?: 0;
        $pending_donations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        
        // This month stats
        $this_month_donations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE MONTH(submission_date) = %d AND YEAR(submission_date) = %d",
            date('n'), date('Y')
        ));
        
        $this_month_raised = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_name WHERE status = 'completed' AND MONTH(submission_date) = %d AND YEAR(submission_date) = %d",
            date('n'), date('Y')
        )) ?: 0;
        ?>
        
        <div class="wrap">
            <h1><?php _e('Campaign Dashboard', 'campaign-manager'); ?></h1>
            
            <div class="campaign-dashboard-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($total_campaigns); ?></h3>
                            <p><?php _e('Total Campaigns', 'campaign-manager'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">💝</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($total_donations); ?></h3>
                            <p><?php _e('Total Donations', 'campaign-manager'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($completed_donations); ?></h3>
                            <p><?php _e('Completed', 'campaign-manager'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <h3>Rp <?php echo number_format($total_raised, 0, ',', '.'); ?></h3>
                            <p><?php _e('Total Raised', 'campaign-manager'); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-content">
                            <h3><?php echo number_format($pending_donations); ?></h3>
                            <p><?php _e('Pending', 'campaign-manager'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="monthly-stats">
                    <h3><?php _e('This Month', 'campaign-manager'); ?></h3>
                    <div class="monthly-grid">
                        <div class="monthly-item">
                            <strong><?php echo number_format($this_month_donations); ?></strong>
                            <span><?php _e('Donations', 'campaign-manager'); ?></span>
                        </div>
                        <div class="monthly-item">
                            <strong>Rp <?php echo number_format($this_month_raised, 0, ',', '.'); ?></strong>
                            <span><?php _e('Raised', 'campaign-manager'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .campaign-dashboard-stats {
                margin: 20px 0;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .stat-card {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border-left: 4px solid #0073aa;
                display: flex;
                align-items: center;
            }
            .stat-icon {
                font-size: 24px;
                margin-right: 15px;
            }
            .stat-content h3 {
                margin: 0;
                font-size: 24px;
                color: #0073aa;
            }
            .stat-content p {
                margin: 5px 0 0 0;
                color: #666;
                font-size: 14px;
            }
            .monthly-stats {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
            }
            .monthly-grid {
                display: flex;
                gap: 30px;
                margin-top: 10px;
            }
            .monthly-item {
                display: flex;
                flex-direction: column;
            }
            .monthly-item strong {
                font-size: 18px;
                color: #0073aa;
            }
            .monthly-item span {
                font-size: 12px;
                color: #666;
            }
        </style>
        <?php
    }
    
    private function render_recent_donations() {
        $recent_donations = $this->database->get_submissions(array(
            'limit' => 10,
            'orderby' => 'submission_date',
            'order' => 'DESC'
        ));
        ?>
        
        <div class="campaign-recent-donations">
            <h2><?php _e('Recent Donations', 'campaign-manager'); ?></h2>
            
            <?php if (empty($recent_donations)): ?>
                <p><?php _e('No donations yet.', 'campaign-manager'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Donor', 'campaign-manager'); ?></th>
                            <th><?php _e('Campaign', 'campaign-manager'); ?></th>
                            <th><?php _e('Amount', 'campaign-manager'); ?></th>
                            <th><?php _e('Status', 'campaign-manager'); ?></th>
                            <th><?php _e('Date', 'campaign-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_donations as $donation): ?>
                            <tr>
                                <td><?php echo esc_html($donation->nama); ?></td>
                                <td>
                                    <?php 
                                    $campaign = get_post($donation->campaign_id);
                                    echo $campaign ? esc_html($campaign->post_title) : __('Deleted Campaign', 'campaign-manager');
                                    ?>
                                </td>
                                <td>Rp <?php echo number_format($donation->amount, 0, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $donation->status; ?>">
                                        <?php echo ucfirst($donation->status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($donation->submission_date)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=campaign&page=campaign-donations'); ?>" class="button">
                        <?php _e('View All Donations', 'campaign-manager'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        
        <style>
            .status-badge {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .status-pending { background: #ffecb3; color: #f57f17; }
            .status-completed { background: #c8e6c9; color: #2e7d32; }
            .status-failed { background: #ffcdd2; color: #c62828; }
            .status-cancelled { background: #e0e0e0; color: #424242; }
        </style>
        <?php
    }
    
    private function render_campaign_overview() {
        // Get active campaigns with stats
        $campaigns = get_posts(array(
            'post_type' => 'campaign',
            'numberposts' => 5,
            'post_status' => 'publish'
        ));
        ?>
        
        <div class="campaign-overview">
            <h2><?php _e('Campaign Overview', 'campaign-manager'); ?></h2>
            
            <?php if (empty($campaigns)): ?>
                <p><?php _e('No campaigns found.', 'campaign-manager'); ?></p>
                <p>
                    <a href="<?php echo admin_url('post-new.php?post_type=campaign'); ?>" class="button button-primary">
                        <?php _e('Create Your First Campaign', 'campaign-manager'); ?>
                    </a>
                </p>
            <?php else: ?>
                <div class="campaigns-grid">
                    <?php foreach ($campaigns as $campaign): ?>
                        <?php
                        $target = get_post_meta($campaign->ID, '_campaign_target', true);
                        $deadline = get_post_meta($campaign->ID, '_campaign_deadline', true);
                        $status = get_post_meta($campaign->ID, '_campaign_status', true) ?: 'active';
                        $stats = $this->database->get_campaign_stats($campaign->ID);
                        $percentage = $target ? ($stats->total_raised / $target) * 100 : 0;
                        ?>
                        <div class="campaign-card">
                            <h4><?php echo esc_html($campaign->post_title); ?></h4>
                            <div class="campaign-meta">
                                <p><strong><?php _e('Target:', 'campaign-manager'); ?></strong> Rp <?php echo number_format($target, 0, ',', '.'); ?></p>
                                <p><strong><?php _e('Raised:', 'campaign-manager'); ?></strong> Rp <?php echo number_format($stats->total_raised, 0, ',', '.'); ?></p>
                                <p><strong><?php _e('Donations:', 'campaign-manager'); ?></strong> <?php echo $stats->completed_donations; ?></p>
                                <p><strong><?php _e('Deadline:', 'campaign-manager'); ?></strong> <?php echo date('d/m/Y', strtotime($deadline)); ?></p>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                            </div>
                            <p class="progress-text"><?php echo round($percentage, 1); ?>% <?php _e('of target reached', 'campaign-manager'); ?></p>
                            <div class="campaign-actions">
                                <a href="<?php echo get_edit_post_link($campaign->ID); ?>" class="button button-small">
                                    <?php _e('Edit', 'campaign-manager'); ?>
                                </a>
                                <a href="<?php echo get_permalink($campaign->ID); ?>" class="button button-small" target="_blank">
                                    <?php _e('View', 'campaign-manager'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=campaign'); ?>" class="button">
                        <?php _e('View All Campaigns', 'campaign-manager'); ?>
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=campaign'); ?>" class="button button-primary">
                        <?php _e('Create New Campaign', 'campaign-manager'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        
        <style>
            .campaigns-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .campaign-card {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border: 1px solid #e0e0e0;
            }
            .campaign-card h4 {
                margin-top: 0;
                color: #0073aa;
            }
            .campaign-meta p {
                margin: 5px 0;
                font-size: 13px;
            }
            .progress-bar {
                width: 100%;
                height: 15px;
                background: #f1f1f1;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0 5px 0;
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                transition: width 0.3s ease;
            }
            .progress-text {
                font-size: 12px;
                color: #666;
                margin: 0 0 15px 0;
            }
            .campaign-actions {
                display: flex;
                gap: 10px;
            }
        </style>
        <?php
    }
}