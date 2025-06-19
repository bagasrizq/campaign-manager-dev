<?php
/**
 * Dashboard View Template
 * This file can be used for additional dashboard customizations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
global $wpdb;
$table_name = $wpdb->prefix . 'campaign_submissions';

// Quick stats for widget-style display
$quick_stats = array(
    'today_donations' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE DATE(submission_date) = %s",
        date('Y-m-d')
    )),
    'today_amount' => $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $table_name WHERE status = 'completed' AND DATE(submission_date) = %s",
        date('Y-m-d')
    )) ?: 0,
    'week_donations' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE submission_date >= %s",
        date('Y-m-d', strtotime('-7 days'))
    )),
    'active_campaigns' => $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'campaign' 
        AND p.post_status = 'publish'
        AND pm.meta_key = '_campaign_status'
        AND pm.meta_value = 'active'
    ")
);

?>

<div class="campaign-dashboard-widgets">
    <div class="dashboard-widget-container">
        
        <!-- Quick Stats Widget -->
        <div class="dashboard-widget">
            <div class="widget-header">
                <h3><?php _e('Today\'s Activity', 'campaign-manager'); ?></h3>
            </div>
            <div class="widget-content">
                <div class="quick-stats">
                    <div class="quick-stat-item">
                        <span class="stat-number"><?php echo number_format($quick_stats['today_donations']); ?></span>
                        <span class="stat-label"><?php _e('Donations Today', 'campaign-manager'); ?></span>
                    </div>
                    <div class="quick-stat-item">
                        <span class="stat-number">Rp <?php echo number_format($quick_stats['today_amount'], 0, ',', '.'); ?></span>
                        <span class="stat-label"><?php _e('Amount Today', 'campaign-manager'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Widget -->
        <div class="dashboard-widget">
            <div class="widget-header">
                <h3><?php _e('Quick Overview', 'campaign-manager'); ?></h3>
            </div>
            <div class="widget-content">
                <div class="overview-items">
                    <div class="overview-item">
                        <span class="overview-icon">📊</span>
                        <div class="overview-details">
                            <strong><?php echo number_format($quick_stats['active_campaigns']); ?></strong>
                            <span><?php _e('Active Campaigns', 'campaign-manager'); ?></span>
                        </div>
                    </div>
                    <div class="overview-item">
                        <span class="overview-icon">📈</span>
                        <div class="overview-details">
                            <strong><?php echo number_format($quick_stats['week_donations']); ?></strong>
                            <span><?php _e('This Week', 'campaign-manager'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Widget -->
        <div class="dashboard-widget">
            <div class="widget-header">
                <h3><?php _e('Quick Actions', 'campaign-manager'); ?></h3>
            </div>
            <div class="widget-content">
                <div class="quick-actions">
                    <a href="<?php echo admin_url('post-new.php?post_type=campaign'); ?>" class="quick-action-btn">
                        <span class="action-icon">➕</span>
                        <span><?php _e('New Campaign', 'campaign-manager'); ?></span>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=campaign&page=campaign-donations'); ?>" class="quick-action-btn">
                        <span class="action-icon">💰</span>
                        <span><?php _e('View Donations', 'campaign-manager'); ?></span>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=campaign&page=campaign-settings'); ?>" class="quick-action-btn">
                        <span class="action-icon">⚙️</span>
                        <span><?php _e('Settings', 'campaign-manager'); ?></span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Performance Tips Widget -->
<div class="dashboard-widget full-width">
    <div class="widget-header">
        <h3><?php _e('Campaign Tips', 'campaign-manager'); ?></h3>
    </div>
    <div class="widget-content">
        <div class="tips-grid">
            <div class="tip-item">
                <h4><?php _e('Optimize Your Campaign Title', 'campaign-manager'); ?></h4>
                <p><?php _e('Use clear, compelling titles that explain your cause in 60 characters or less.', 'campaign-manager'); ?></p>
            </div>
            <div class="tip-item">
                <h4><?php _e('Set Realistic Goals', 'campaign-manager'); ?></h4>
                <p><?php _e('Break large goals into smaller milestones to maintain momentum.', 'campaign-manager'); ?></p>
            </div>
            <div class="tip-item">
                <h4><?php _e('Share Regular Updates', 'campaign-manager'); ?></h4>
                <p><?php _e('Keep supporters engaged with progress updates and thank you messages.', 'campaign-manager'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.campaign-dashboard-widgets {
    margin: 20px 0;
}

.dashboard-widget-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.dashboard-widget {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.dashboard-widget.full-width {
    grid-column: 1 / -1;
}

.widget-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.widget-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.widget-content {
    padding: 20px;
}

/* Quick Stats Styles */
.quick-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.quick-stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
    line-height: 1;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

/* Overview Styles */
.overview-items {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.overview-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.overview-icon {
    font-size: 20px;
}

.overview-details {
    display: flex;
    flex-direction: column;
}

.overview-details strong {
    font-size: 18px;
    color: #0073aa;
}

.overview-details span {
    font-size: 12px;
    color: #666;
}

/* Quick Actions Styles */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: #0073aa;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s ease;
}

.quick-action-btn:hover {
    background: #005a87;
    color: white;
}

.action-icon {
    font-size: 16px;
}

/* Tips Styles */
.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.tip-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.tip-item h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.tip-item p {
    margin: 0;
    font-size: 13px;
    color: #666;
    line-height: 1.4;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-widget-container {
        grid-template-columns: 1fr;
    }
    
    .quick-stats {
        flex-direction: row;
        justify-content: space-around;
    }
    
    .tips-grid {
        grid-template-columns: 1fr;
    }
}
</style>