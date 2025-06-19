<?php
/**
 * Template for displaying single campaign posts
 * This template is for the 'campaign' custom post type
 */

get_header(); ?>

<div class="campaign-container">
    <?php while (have_posts()) : the_post(); ?>
        <?php
        // Get campaign meta data
        $campaign_id = get_the_ID();
        $target = get_post_meta($campaign_id, '_campaign_target', true);
        $deadline = get_post_meta($campaign_id, '_campaign_deadline', true);
        $status = get_post_meta($campaign_id, '_campaign_status', true) ?: 'active';
        $currency = get_post_meta($campaign_id, '_campaign_currency', true) ?: 'IDR';
        
        // Get campaign statistics from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'campaign_submissions';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_donations,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_raised
            FROM $table_name WHERE campaign_id = %d",
            $campaign_id
        ));
        
        $total_raised = $stats->total_raised ?: 0;
        $total_donations = $stats->completed_donations ?: 0;
        $percentage = $target ? ($total_raised / $target) * 100 : 0;
        
        // Currency symbol
        $currency_symbol = $currency === 'IDR' ? 'Rp ' : '$';
        
        // Check if campaign is expired
        $is_expired = $deadline && strtotime($deadline) < time();
        $is_active = $status === 'active' && !$is_expired;
        ?>
        
        <div class="campaign-header">
            <div class="campaign-meta-info">
                <span class="campaign-status status-<?php echo esc_attr($status); ?>">
                    <?php echo ucfirst($status); ?>
                </span>
                <?php if ($deadline): ?>
                    <span class="campaign-deadline <?php echo $is_expired ? 'expired' : ''; ?>">
                        <i class="dashicons dashicons-calendar-alt"></i>
                        <?php 
                        if ($is_expired) {
                            echo 'Berakhir: ' . date('d F Y', strtotime($deadline));
                        } else {
                            $days_left = ceil((strtotime($deadline) - time()) / (60 * 60 * 24));
                            echo $days_left . ' hari tersisa';
                        }
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <h1 class="campaign-title"><?php the_title(); ?></h1>
            
            <?php if (has_post_thumbnail()): ?>
                <div class="campaign-featured-image">
                    <?php the_post_thumbnail('large', array('class' => 'campaign-image')); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="campaign-content-wrapper">
            <div class="campaign-main-content">
                <div class="campaign-description">
                    <h2>Tentang Campaign Ini</h2>
                    <?php the_content(); ?>
                    
                    <?php if (get_the_excerpt()): ?>
                        <div class="campaign-excerpt">
                            <strong>Ringkasan:</strong>
                            <p><?php echo get_the_excerpt(); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="campaign-updates">
                    <h3>Donatur Terbaru</h3>
                    <?php
                    // Get recent donations
                    $recent_donations = $wpdb->get_results($wpdb->prepare(
                        "SELECT nama, amount, submission_date, notes 
                         FROM $table_name 
                         WHERE campaign_id = %d AND status = 'completed' 
                         ORDER BY submission_date DESC 
                         LIMIT 5",
                        $campaign_id
                    ));
                    
                    if ($recent_donations): ?>
                        <div class="recent-donations">
                            <?php foreach ($recent_donations as $donation): ?>
                                <div class="donation-item">
                                    <div class="donation-info">
                                        <strong><?php echo esc_html($donation->nama); ?></strong>
                                        <span class="donation-amount"><?php echo $currency_symbol . number_format($donation->amount, 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="donation-date">
                                        <?php echo date('d M Y, H:i', strtotime($donation->submission_date)); ?>
                                    </div>
                                    <?php if ($donation->notes): ?>
                                        <div class="donation-notes">
                                            <em>"<?php echo esc_html($donation->notes); ?>"</em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-donations">Belum ada donasi untuk campaign ini.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="campaign-sidebar">
                <div class="campaign-stats-card">
                    <div class="stats-header">
                        <h3>Progress Donasi</h3>
                    </div>
                    
                    <div class="progress-section">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                        </div>
                        <div class="progress-text">
                            <?php echo round($percentage, 1); ?>% tercapai
                        </div>
                    </div>
                    
                    <div class="stats-numbers">
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php echo $currency_symbol . number_format($total_raised, 0, ',', '.'); ?>
                            </div>
                            <div class="stat-label">Terkumpul</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php echo $currency_symbol . number_format($target, 0, ',', '.'); ?>
                            </div>
                            <div class="stat-label">Target</div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $total_donations; ?></div>
                            <div class="stat-label">Donatur</div>
                        </div>
                    </div>
                    
                    <?php if ($is_active): ?>
                        <div class="donation-action">
                            <a href="<?php echo esc_url(add_query_arg('campaign_id', $campaign_id, home_url('/donation-form/'))); ?>" 
                               class="btn-donate">
                                <i class="dashicons dashicons-heart"></i>
                                Donasi Sekarang
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="campaign-closed">
                            <p class="closed-message">
                                <?php 
                                if ($is_expired) {
                                    echo 'Campaign telah berakhir';
                                } elseif ($status === 'completed') {
                                    echo 'Campaign telah selesai';
                                } else {
                                    echo 'Campaign sedang dijeda';
                                }
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="campaign-share">
                    <h4>Bagikan Campaign</h4>
                    <div class="share-buttons">
                        <a href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . urlencode(get_permalink())); ?>" 
                           target="_blank" class="share-btn facebook">
                            <i class="dashicons dashicons-facebook"></i> Facebook
                        </a>
                        <a href="<?php echo esc_url('https://twitter.com/intent/tweet?url=' . urlencode(get_permalink()) . '&text=' . urlencode(get_the_title())); ?>" 
                           target="_blank" class="share-btn twitter">
                            <i class="dashicons dashicons-twitter"></i> Twitter
                        </a>
                        <a href="<?php echo esc_url('https://wa.me/?text=' . urlencode(get_the_title() . ' - ' . get_permalink())); ?>" 
                           target="_blank" class="share-btn whatsapp">
                            <i class="dashicons dashicons-whatsapp"></i> WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
    <?php endwhile; ?>
</div>

<style>
.campaign-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
}

.campaign-header {
    margin-bottom: 30px;
}

.campaign-meta-info {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.campaign-status {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-active { background: #e8f5e8; color: #4caf50; }
.status-paused { background: #fff3e0; color: #ff9800; }
.status-completed { background: #e3f2fd; color: #2196f3; }

.campaign-deadline {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    color: #666;
}

.campaign-deadline.expired {
    color: #f44336;
    font-weight: bold;
}

.campaign-title {
    font-size: 2.5em;
    margin: 10px 0;
    color: #333;
    line-height: 1.2;
}

.campaign-featured-image {
    margin: 20px 0;
}

.campaign-image {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.campaign-content-wrapper {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 40px;
    margin-top: 30px;
}

.campaign-description h2 {
    color: #333;
    border-bottom: 2px solid #4caf50;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.campaign-excerpt {
    background: #f9f9f9;
    padding: 15px;
    border-left: 4px solid #4caf50;
    margin: 20px 0;
}

.campaign-updates h3 {
    color: #333;
    margin: 30px 0 15px 0;
}

.recent-donations {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.donation-item {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.donation-item:last-child {
    border-bottom: none;
}

.donation-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.donation-amount {
    color: #4caf50;
    font-weight: bold;
}

.donation-date {
    font-size: 12px;
    color: #888;
}

.donation-notes {
    margin-top: 8px;
    font-size: 14px;
    color: #666;
}

.no-donations {
    text-align: center;
    color: #888;
    font-style: italic;
    padding: 20px;
}

.campaign-stats-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.stats-header h3 {
    margin: 0 0 20px 0;
    color: #333;
    text-align: center;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #45a049);
    transition: width 0.3s ease;
}

.progress-text {
    text-align: center;
    font-weight: bold;
    color: #4caf50;
    margin-bottom: 20px;
}

.stats-numbers {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
}

.btn-donate {
    display: block;
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #4caf50, #45a049);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-donate:hover {
    background: linear-gradient(135deg, #45a049, #4caf50);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
}

.btn-donate i {
    margin-right: 8px;
}

.campaign-closed {
    text-align: center;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 8px;
}

.closed-message {
    color: #666;
    font-weight: bold;
    margin: 0;
}

.campaign-share {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.campaign-share h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.share-buttons {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.share-btn {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.share-btn i {
    margin-right: 8px;
}

.share-btn.facebook {
    background: #1877f2;
    color: white;
}

.share-btn.twitter {
    background: #1da1f2;
    color: white;
}

.share-btn.whatsapp {
    background: #25d366;
    color: white;
}

.share-btn:hover {
    opacity: 0.8;
    transform: translateX(5px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .campaign-content-wrapper {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .campaign-title {
        font-size: 1.8em;
    }
    
    .campaign-image {
        height: 250px;
    }
    
    .stats-numbers {
        flex-direction: column;
        gap: 10px;
    }
    
    .campaign-meta-info {
        flex-direction: column;
        gap: 8px;
    }
    
    .share-buttons {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .share-btn {
        flex: 1;
        min-width: 80px;
        justify-content: center;
    }
}
</style>

<?php get_footer(); ?>