<?php
/**
 * Template for displaying single campaign posts
 * Modern layout with tabs and enhanced UI
 */

get_header(); ?>

<div class="page-container">
    <?php while (have_posts()) : the_post(); ?>
        <?php
        // START: Campaign Data Collection - Fetching campaign metadata and statistics
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
        
        // Processing and calculating campaign metrics for display
        $total_raised = $stats->total_raised ?: 0;
        $total_donations = $stats->completed_donations ?: 0;
        $percentage = $target ? ($total_raised / $target) * 100 : 0;
        
        // Currency symbol for display formatting
        $currency_symbol = $currency === 'IDR' ? 'Rp ' : '$';
        
        // Campaign status and deadline validation for frontend controls
        $is_expired = $deadline && strtotime($deadline) < time();
        $is_active = $status === 'active' && !$is_expired;
        
        // Calculate days left for display
        $days_left = 0;
        if ($deadline && !$is_expired) {
            $days_left = ceil((strtotime($deadline) - time()) / (60 * 60 * 24));
        }
        // END: Campaign Data Collection
        ?>

        <main class="article-main">
            <!-- START: Featured Image Display - Campaign header image -->
            <header class="featured-image">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('large', array('class' => 'campaign-featured-img')); ?>
                <?php else: ?>
                    <!-- Fallback placeholder image with campaign title -->
                    <img src="https://placehold.co/635x357/2c5aa0/ffffff?text=<?php echo urlencode(get_the_title()); ?>" 
                         alt="<?php the_title(); ?>" class="campaign-featured-img">
                <?php endif; ?>
                <div class="campaign-status-badge status-<?php echo esc_attr($status); ?>">
                    <?php echo ucfirst($status); ?>
                </div>
            </header>
            <!-- END: Featured Image Display -->
            
            <!-- Main Content -->
            <article class="content">
                <div class="container-tabs">
                    <!-- START: Tab Navigation - Interactive tab buttons for content sections -->
                    <nav class="tabs" role="tablist">
                        <button class="tab-button active" onclick="openTab(event, 'program-detil')" role="tab" aria-selected="true" aria-controls="program-detil">
                            Detail Program
                        </button>
                        <button class="tab-button" onclick="openTab(event, 'donatur')" role="tab" aria-selected="false" aria-controls="donatur">
                            Donatur
                        </button>
                        <button class="tab-button" onclick="openTab(event, 'recent-news')" role="tab" aria-selected="false" aria-controls="recent-news">
                            Update Terkini
                        </button>
                    </nav>
                    <!-- END: Tab Navigation -->
                    
                    <div class="tab-contents">
                        <!-- START: Program Detail Tab Content - Campaign description and details -->
                        <section id="program-detil" class="tab-content active" role="tabpanel">
                            <h1><?php the_title(); ?></h1>
                            <div class="campaign-content">
                                <?php the_content(); ?>
                                
                               
                            </div>
                        </section>
                        <!-- END: Program Detail Tab Content -->
                        
                        <!-- START: Donors Tab Content - List of recent donors and their donations -->
                        <section id="donatur" class="tab-content" role="tabpanel">
                            <h2>Donatur Terkini</h2>
                            <div class="donatur-list">
                                <?php
                                // Fetch recent donations from database for display
                                $recent_donations = $wpdb->get_results($wpdb->prepare(
                                    "SELECT nama, amount, submission_date, notes 
                                     FROM $table_name 
                                     WHERE campaign_id = %d AND status = 'completed' 
                                     ORDER BY submission_date DESC 
                                     LIMIT 10",
                                    $campaign_id
                                ));
                                
                                if ($recent_donations): ?>
                                    <?php foreach ($recent_donations as $donation): ?>
                                        <!-- Individual donor card display -->
                                        <article class="donatur-card">
                                            <div class="donatur-info">
                                                <h3 class="donatur-name"><?php echo esc_html($donation->nama); ?></h3>
                                                <p class="donatur-amount"><?php echo $currency_symbol . number_format($donation->amount, 0, ',', '.'); ?></p>
                                                <?php if ($donation->notes): ?>
                                                    <blockquote class="donatur-message">"<?php echo esc_html($donation->notes); ?>"</blockquote>
                                                <?php endif; ?>
                                            </div>
                                            <time class="donatur-date" datetime="<?php echo date('c', strtotime($donation->submission_date)); ?>">
                                                <?php echo date('M j, Y', strtotime($donation->submission_date)); ?>
                                            </time>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Empty state when no donations exist -->
                                    <p class="no-donations">Belum ada donasi untuk campaign ini.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                        <!-- END: Donors Tab Content -->
                        
                        <!-- START: Recent News Tab Content - Campaign updates and related news -->
                        <section id="recent-news" class="tab-content" role="tabpanel">
                            <h2>Update Terkini</h2>
                            <div class="news-grid">
                                <?php
                                // Fetch campaign-related posts/updates for display
                                $updates = new WP_Query(array(
                                    'post_type' => 'post',
                                    'meta_query' => array(
                                        array(
                                            'key' => '_campaign_related',
                                            'value' => $campaign_id,
                                            'compare' => '='
                                        )
                                    ),
                                    'posts_per_page' => 3,
                                    'orderby' => 'date',
                                    'order' => 'DESC'
                                ));
                                
                                if ($updates->have_posts()): ?>
                                    <?php while ($updates->have_posts()): $updates->the_post(); ?>
                                        <!-- Individual news card with clickable modal functionality -->
                                        <article class="news-card">
                                            <?php if (has_post_thumbnail()): ?>
                                                <?php 
                                                // Prepare data attributes for modal display
                                                $thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
                                                $title = get_the_title();
                                                $excerpt = get_the_excerpt() ?: wp_trim_words(get_the_content(), 20);
                                                $date = get_the_date('j M Y');
                                                ?>
                                                <img src="<?php echo esc_url($thumbnail_url); ?>" 
                                                     alt="<?php echo esc_attr($title); ?>" 
                                                     class="news-image"
                                                     data-title="<?php echo esc_attr($title); ?>"
                                                     data-description="<?php echo esc_attr($excerpt); ?>"
                                                     data-date="<?php echo esc_attr($date); ?>">
                                            <?php else: ?>
                                                <!-- Fallback placeholder for news without featured image -->
                                                <img src="https://placehold.co/400x300/2c5aa0/ffffff?text=<?php echo urlencode(get_the_title()); ?>" 
                                                     alt="<?php the_title(); ?>" 
                                                     class="news-image"
                                                     data-title="<?php echo esc_attr(get_the_title()); ?>"
                                                     data-description="<?php echo esc_attr(get_the_excerpt() ?: wp_trim_words(get_the_content(), 20)); ?>"
                                                     data-date="<?php echo esc_attr(get_the_date('j M Y')); ?>">
                                            <?php endif; ?>
                                            <div class="news-content">
                                                <h3 class="news-title"><?php the_title(); ?></h3>
                                                <p class="news-description">
                                                    <?php echo wp_trim_words(get_the_excerpt() ?: get_the_content(), 15); ?>
                                                </p>
                                            </div>
                                        </article>
                                    <?php endwhile; ?>
                                    <?php wp_reset_postdata(); ?>
                                <?php else: ?>
                                    <!-- Default placeholder news when no updates exist -->
                                    <article class="news-card">
                                        <img src="https://placehold.co/400x300/2c5aa0/ffffff?text=Update+Terbaru" 
                                             alt="Update Campaign" 
                                             class="news-image"
                                             data-title="Campaign Telah Dimulai"
                                             data-description="Alhamdulillah, campaign <?php the_title(); ?> telah resmi dimulai. Mari bersama-sama berpartisipasi untuk mencapai target yang telah ditetapkan."
                                             data-date="<?php echo get_the_date('j M Y'); ?>">
                                        <div class="news-content">
                                            <h3 class="news-title">Campaign Telah Dimulai</h3>
                                            <p class="news-description">
                                                Mari bersama-sama berpartisipasi untuk mencapai target yang telah ditetapkan.
                                            </p>
                                        </div>
                                    </article>
                                <?php endif; ?>
                            </div>
                        </section>
                        <!-- END: Recent News Tab Content -->
                    </div>
                </div>
            </article>
        </main>

        <!-- START: Donation Sidebar - Campaign statistics and donation interface -->
        <aside class="donation-sidebar">
            <div class="sticky-container">
                <div class="donation-card">
                    <!-- Campaign status and target display -->
                    <header class="donation-header">
                        <h2>Detail Donasi</h2>
                        <!-- Toggle button untuk mobile/tablet -->
                        <div class="sidebar-toggle" aria-label="Toggle sidebar">
                            <span>&gt;</span>
                        </div>
                    </header>
                    
                    <!-- Wrapper untuk content yang bisa di-toggle -->
                    <div class="donation-content">
                        <div class="donation-info">
                            <!-- Target amount display -->
                            <div class="donation-goal">
                                <span class="goal-amount"><?php echo $currency_symbol . number_format($target, 0, ',', '.'); ?></span>
                            </div>
                            
                            <!-- Progress bar with calculated percentage -->
                            <progress id="donation-progress" value="<?php echo round($percentage); ?>" max="100" class="progress-bar" aria-label="Progress donasi <?php echo round($percentage); ?>%"></progress>
                            
                            <!-- Donation statistics display -->
                            <div class="donation-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Donasi Terkumpul</span>
                                    <span class="stat-value"><?php echo $currency_symbol . number_format($total_raised, 0, ',', '.'); ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Kekurangan</span>
                                    <span class="stat-value deficit">- <?php echo $currency_symbol . number_format(max(0, $target - $total_raised), 0, ',', '.'); ?></span>
                                </div>
                            </div>
                            
                            <!-- Campaign metrics display (donations, donors, days left) -->
                            <div class="donation-metrics">
                                <div class="metric-item">
                                    <strong class="metric-number"><?php echo $stats->total_submissions; ?></strong>
                                    <span class="metric-label">DONASI</span>
                                </div>
                                <div class="metric-item">
                                    <strong class="metric-number"><?php echo $total_donations; ?></strong>
                                    <span class="metric-label">DONATUR</span>
                                </div>
                                <div class="metric-item">
                                    <strong class="metric-number"><?php echo $is_expired ? 0 : $days_left; ?></strong>
                                    <span class="metric-label">SISA HARI</span>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    <!-- Action buttons (Share and Donate) -->
                        <div class="donation-actions">
                            <button type="button" class="btn-share" onclick="toggleShareOptions()" aria-label="Bagikan campaign ini">Share</button>
                            <?php if ($is_active): ?>
                                <!-- Active donation button with dynamic link -->
                                <a href="<?php echo esc_url(add_query_arg('campaign_id', get_the_ID(), home_url('/donation-form/'))); ?>" 
                                class="btn-donate" aria-label="Berdonasi sekarang">
                                    Donasi Sekarang
                                </a>
                            <?php else: ?>
                                <!-- Disabled button with status-based text -->
                                <button type="button" class="btn-donate disabled" disabled>
                                    <?php 
                                    if ($is_expired) {
                                        echo 'Campaign Berakhir';
                                    } elseif ($status === 'completed') {
                                        echo 'Campaign Selesai';
                                    } else {
                                        echo 'Campaign Dijeda';
                                    }
                                    ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- START: Share Options Display - Social media sharing buttons -->
                        <div id="share-options" class="share-options" style="display: none;">
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
                        <!-- END: Share Options Display -->
                </div>
            </div>
        </aside>
        <!-- END: Donation Sidebar -->
    <?php endwhile; ?>
</div>

<!-- START: News Modal Display - Popup modal for news article details -->
<div id="news-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-description">
    <div class="modal-content">
        <header class="modal-header">
            <img id="modal-image" class="modal-image" src="" alt="">
            <button class="modal-close" onclick="closeModal()" aria-label="Tutup modal">&times;</button>
        </header>
        <div class="modal-body">
            <h2 id="modal-title" class="modal-title"></h2>
            <p id="modal-description" class="modal-description"></p>
            <time id="modal-date" class="modal-date"></time>
        </div>
    </div>
</div>
<!-- END: News Modal Display -->

<!-- START: External Fonts Loading -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- END: External Fonts Loading -->

<style>
    /* Reset and CSS Variables */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary-green: #13723b;
    --primary-green-dark: #134823;
    --primary-orange: #f7921f;
    --text-primary: #333;
    --text-secondary: #555;
    --text-muted: #6c757d;
    --border-color: #e9ecef;
    --background-light: #f8f9fa;
    --background-white: #fff;
    --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.1);
    --shadow-medium: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-heavy: 0 10px 30px rgba(0, 0, 0, 0.3);
    --border-radius: 4px;
    --border-radius-lg: 8px;
    --transition: all 0.3s ease;
}

/* Base Styles */
body {
    font-family: "Poppins", sans-serif;
    color: var(--text-primary);
    background: var(--background-light);
    line-height: 1.6;
}

/* Layout */
.page-container {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.article-main {
    background: var(--background-white);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-medium);
}

/* Featured Image */
.featured-image {
    width: 100%;
    height: 300px;
    overflow: hidden;
    position: relative;
}

.campaign-featured-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.featured-image:hover .campaign-featured-img {
    transform: scale(1.02);
}

/* Content Area */
.content {
    padding: 2rem;
}

/* Tabs */
.container-tabs {
    width: 100%;
}

.tabs {
    display: flex;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 1.5rem;
    background: var(--background-white);
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.tab-button {
    background: none;
    border: none;
    padding: 1rem 1.5rem;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-muted);
    border-bottom: 2px solid transparent;
    transition: var(--transition);
    flex: 1;
    text-align: center;
}

.tab-button.active {
    color: var(--primary-green);
    border-bottom-color: var(--primary-green);
    font-weight: 600;
}

.tab-button:hover {
    color: var(--primary-green);
    background: rgba(44, 90, 160, 0.05);
}

/* Tab Content */
.tab-content {
    display: none;
    animation: fadeIn 0.3s ease-in-out;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.tab-content h1 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--primary-green);
    line-height: 1.2;
}

.tab-content h2 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.campaign-content {
    line-height: 1.7;
    color: var(--text-secondary);
}

.campaign-content p {
    margin-bottom: 1rem;
}

/* Donor List */
.donatur-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.donatur-card {
    background: var(--background-light);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-light);
    transition: var(--transition);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.donatur-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}

.donatur-info {
    flex: 1;
}

.donatur-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
}

.donatur-amount {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary-green);
    margin: 0 0 0.5rem 0;
}

.donatur-message {
    font-style: italic;
    color: var(--text-muted);
    margin: 0.5rem 0;
    padding: 0.5rem 0;
    font-size: 0.9rem;
    line-height: 1.4;
}

.donatur-date {
    font-size: 0.85rem;
    color: var(--text-muted);
    white-space: nowrap;
    margin-top: 0.25rem;
}

/* News Grid */
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.news-card {
    background: var(--background-white);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-light);
    cursor: pointer;
    transition: var(--transition);
}

.news-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-medium);
}

.news-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.news-card:hover .news-image {
    transform: scale(1.05);
}

.news-content {
    padding: 1rem;
}

.news-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
    line-height: 1.3;
}

.news-description {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Donation Sidebar - Desktop */
.donation-sidebar {
    position: relative;
}

.sticky-container {
    position: sticky;
    top: 2rem;
    z-index: 10;
}

.donation-card {
    background: var(--background-white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-medium);
    overflow: hidden;
}

/* Donation Header */
.donation-header {
    background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
    color: white;
    padding: 1.5rem;
    text-align: center;
    position: relative;
}

.donation-header h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

/* Toggle Button - Hidden on Desktop */
.sidebar-toggle {
    display: none;
    position: absolute;
    top: 50%;
    right: 1.5rem;
    transform: translateY(-50%) rotate(-90deg);
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    align-items: center;
    justify-content: center;
}

.sidebar-toggle:hover {
    color: rgba(255, 255, 255, 0.3);
}

.sidebar-toggle.expanded {
    transform: translateY(-50%) rotate(90deg);
}

/* Campaign Status Badge */
.campaign-status-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.status-active { background: rgba(40, 167, 69, 0.9); }
.status-completed { background: rgba(0, 123, 255, 0.9); }
.status-paused { background: rgba(255, 193, 7, 0.9); }

/* Donation Content */
.donation-content {
    transition: all 0.3s ease;
}

.donation-info {
    padding: 1.5rem;
}

.donation-goal {
    text-align: center;
    margin-bottom: 1rem;
}

.goal-amount {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-green);
    display: block;
}

/* Progress Bar */
.progress-bar {
    width: 100%;
    height: 12px;
    border-radius: 6px;
    margin-bottom: 1rem;
    appearance: none;
    border: none;
}

.progress-bar::-webkit-progress-bar {
    background: var(--border-color);
    border-radius: 6px;
}

.progress-bar::-webkit-progress-value,
.progress-bar::-moz-progress-bar {
    background: linear-gradient(90deg, var(--primary-green), var(--primary-orange));
    border-radius: 6px;
    transition: width 1s ease-in-out;
}

/* Donation Statistics */
.donation-stats {
    margin-bottom: 1.5rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    padding: 0.25rem 0;
}

.stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-value {
    font-weight: 600;
    color: var(--text-primary);
}

.stat-value.deficit {
    color: #dc3545;
}

/* Donation Metrics */
.donation-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.metric-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 0;
}

.metric-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-green);
    display: block;
    line-height: 1;
}

.metric-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
    word-wrap: break-word;
    text-align: center;
}

/* Donation Actions */
.donation-actions {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 0.75rem;
    padding: 1.5rem;
    background: var(--background-light);
}

/* Buttons */
.btn-share {
    background: var(--text-muted);
    color: white;
    border: none;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-weight: 500;
    transition: var(--transition);
    font-size: 0.9rem;
}

.btn-share:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.btn-donate {
    background: var(--primary-green);
    color: white;
    text-decoration: none;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    text-align: center;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

.btn-donate:hover {
    background: var(--primary-green-dark);
    color: white;
    transform: translateY(-1px);
}

.btn-donate.disabled {
    background: var(--text-muted);
    cursor: not-allowed;
    transform: none;
    opacity: 0.7;
}

.btn-donate.disabled:hover {
    transform: none;
    box-shadow: none;
}

/* Share Options */
.share-options {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--background-white);
}

.share-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.share-btn {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    text-decoration: none;
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    transition: var(--transition);
    color: white;
}

.share-btn i {
    margin-right: 0.5rem;
}

.share-btn.facebook { background: #1877f2; }
.share-btn.twitter { background: #1da1f2; }
.share-btn.whatsapp { background: #25d366; }

.share-btn:hover {
    opacity: 0.9;
    transform: translateX(5px);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: var(--transition);
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: var(--background-white);
    border-radius: var(--border-radius-lg);
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: var(--shadow-heavy);
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.show .modal-content {
    transform: scale(1);
}

.modal-header {
    position: relative;
}

.modal-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    min-width: 32px;
    min-height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    background: rgba(0, 0, 0, 0.9);
    transform: scale(1.1);
}

.modal-body {
    padding: 1.5rem;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0 0 1rem 0;
    color: var(--text-primary);
}

.modal-description {
    line-height: 1.6;
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
}

.modal-date {
    font-size: 0.9rem;
    color: var(--text-muted);
    font-style: italic;
}

/* Empty State */
.no-donations {
    text-align: center;
    color: var(--text-muted);
    font-style: italic;
    padding: 2rem;
    background: var(--background-light);
    border-radius: var(--border-radius);
    margin: 1rem 0;
}

/* RESPONSIVE STYLES */

/* Tablet and Mobile - Sticky Sidebar */
@media (max-width: 1024px) {
    .page-container {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .donation-sidebar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999;
        margin: 0;
        padding: 0;
    }
    
    .sticky-container {
        position: static;
        top: auto;
    }
    
    .donation-card {
        border-radius: 0;
        border-top-left-radius: var(--border-radius-lg);
        border-top-right-radius: var(--border-radius-lg);
        margin: 0;
    }
    
    /* Show toggle button */
    .sidebar-toggle {
        display: flex;
    }
    
    /* Collapsible content */
    .donation-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .donation-content.expanded {
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .donation-header {
        cursor: pointer;
    }
    
    /* Always visible header and actions */
    .donation-actions {
        position: sticky;
        bottom: 0;
        background: var(--background-light);
        border-top: 1px solid var(--border-color);
        z-index: 1;
    }
}

/* RESPONSIVE STYLES */

/* Tablet and Mobile - Sticky Sidebar */
@media (max-width: 1023px) {
    .page-container {
        grid-template-columns: 1fr;
        gap: 1rem;
        /* Add bottom padding to prevent content being hidden behind sticky sidebar */
        padding-bottom: 120px;
    }
    
    .donation-sidebar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999;
        margin: 0;
        padding: 0;
        /* Add subtle shadow to separate from content */
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
    }
    
    .sticky-container {
        position: static;
        top: auto;
    }
    
    .donation-card {
        border-radius: 0;
        border-top-left-radius: var(--border-radius-lg);
        border-top-right-radius: var(--border-radius-lg);
        margin: 0;
        /* Remove bottom shadow since we're at screen bottom */
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    }
    
    /* Show toggle button */
    .sidebar-toggle {
        display: flex;
    }
    
    /* Collapsible content */
    .donation-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .donation-content.expanded {
        max-height: 50vh;
        overflow-y: auto;
        /* Add scrollbar styling */
        scrollbar-width: thin;
        scrollbar-color: var(--primary-green) var(--border-color);
    }
    
    .donation-content.expanded::-webkit-scrollbar {
        width: 4px;
    }
    
    .donation-content.expanded::-webkit-scrollbar-track {
        background: var(--border-color);
    }
    
    .donation-content.expanded::-webkit-scrollbar-thumb {
        background: var(--primary-green);
        border-radius: 2px;
    }
    
    .donation-header {
        cursor: pointer;
        /* Add hover effect */
        transition: background-color 0.2s ease;
    }
    
    .donation-header:hover {
        background: linear-gradient(135deg, var(--primary-green-dark), #0f3a1f);
    }
    
    /* ALWAYS VISIBLE STICKY ACTIONS */
    .donation-actions {
        position: sticky;
        bottom: 0;
        background: var(--background-light);
        border-top: 1px solid var(--border-color);
        z-index: 1000;
        /* Ensure it stays on top of scrollable content */
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
        
        /* Make sure it doesn't collapse */
        flex-shrink: 0;
        
        /* Add subtle animation */
        transition: transform 0.2s ease;
    }
    
    /* Share options positioning */
    .share-options {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        background: var(--background-white);
        border-top: 1px solid var(--border-color);
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
        /* Smooth slide animation */
        transform: translateY(100%);
        transition: transform 0.3s ease;
        z-index: 999;
    }
    
    .share-options[style*="block"] {
        transform: translateY(0);
    }
    
    /* Button enhancements for touch devices */
    .btn-share, .btn-donate {
        min-height: 44px; /* iOS recommended touch target */
        font-weight: 600;
        letter-spacing: 0.5px;
        /* Add tap highlight */
        -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
    }
    
    .btn-share:active, .btn-donate:active {
        transform: translateY(1px);
    }
    
    /* Enhance share buttons for mobile */
    .share-btn {
        min-height: 44px;
        font-weight: 500;
        /* Better touch feedback */
        -webkit-tap-highlight-color: rgba(255, 255, 255, 0.2);
    }
    
    .share-btn:active {
        transform: translateX(2px);
        opacity: 0.8;
    }
}

/* Tablet Specific Adjustments */
@media (max-width: 1023px) and (min-width: 768px) {
    .page-container {
        padding: 2rem;
        padding-bottom: 140px; /* Slightly more space for tablet */
    }
    
    .donation-header {
        padding: 1.2rem 1.5rem;
    }
    
    .donation-header h2 {
        font-size: 1.2rem;
    }
    
    .donation-info {
        padding: 1.2rem 1.5rem;
    }
    
    .goal-amount {
        font-size: 1.6rem;
    }
    
    .donation-metrics {
        gap: 0.8rem;
        margin-bottom: 1.2rem;
    }
    
    .metric-number {
        font-size: 1.4rem;
    }
    
    .metric-label {
        font-size: 0.75rem;
    }
    
    .donation-actions {
        padding: 1.2rem 1.5rem;
        gap: 0.8rem;
    }
    
    .btn-share, .btn-donate {
        padding: 0.8rem 1rem;
        font-size: 0.9rem;
    }
    
    .share-options {
        padding: 1rem 1.5rem;
    }
    
    .share-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }
}

/* Mobile Specific Adjustments */
@media (max-width: 767px) {
    .page-container {
        padding: 1rem;
        padding-bottom: 100px;
    }
    
    .content {
        padding: 1.5rem;
    }
    
    .tabs {
        flex-wrap: wrap;
    }
    
    .tab-button {
        flex: 1;
        min-width: 100px;
        padding: 0.75rem;
        font-size: 0.9rem;
    }
    
    .tab-content h1 {
        font-size: 1.5rem;
    }
    
    .news-grid {
        grid-template-columns: 1fr;
    }
    
    .donatur-card {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .donatur-date {
        align-self: flex-end;
    }
    
    .featured-image {
        height: 200px;
    }
    
    /* Modal adjustments */
    .modal-content {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
    
    .modal-image {
        height: 200px;
    }
    
    /* Mobile sidebar specific */
    .sidebar-toggle {
        width: 28px;
        height: 28px;
        font-size: 1rem;
    }
    
    .donation-header {
        padding: 0.8rem 1rem;
    }
    
    .donation-header h2 {
        font-size: 1rem;
        margin-right: 2.5rem;
    }
    
    .campaign-status-badge {
        top: 1rem;
        right: 1rem;
        padding: 0.2rem 0.5rem;
        font-size: 0.65rem;
    }
    
    .donation-info {
        padding: 1rem;
    }
    
    .goal-amount {
        font-size: 1.4rem;
    }
    
    .donation-stats {
        margin-bottom: 1rem;
    }
    
    .stat-item {
        margin-bottom: 0.4rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
    }
    
    .stat-value {
        font-size: 0.9rem;
    }
    
    .donation-metrics {
        gap: 0.4rem;
        margin-bottom: 1rem;
        padding-top: 0.75rem;
    }
    
    .metric-item {
        padding: 0.2rem;
    }
    
    .metric-number {
        font-size: 1.2rem;
        line-height: 1.1;
    }
    
    .metric-label {
        font-size: 0.7rem;
        line-height: 1.1;
        margin-top: 0.2rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .donation-actions {
        padding: 1rem;
        gap: 0.6rem;
    }
    
    .btn-share, .btn-donate {
        padding: 0.7rem 0.9rem;
        font-size: 0.85rem;
        border-radius: 6px;
    }
    
    .share-options {
        padding: 0.8rem 1rem;
    }
    
    .share-btn {
        padding: 0.5rem 0.8rem;
        font-size: 0.8rem;
        border-radius: 4px;
    }
}

/* Very Small Mobile */
@media (max-width: 360px) {
    .page-container {
        padding-bottom: 90px;
    }
    
    .donation-metrics {
        gap: 0.2rem;
    }
    
    .metric-number {
        font-size: 1.1rem;
    }
    
    .metric-label {
        font-size: 0.65rem;
    }
    
    .donation-actions {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .btn-donate {
        order: 1;
    }
    
    .btn-share {
        order: 2;
    }
    
    .btn-share, .btn-donate {
        padding: 0.6rem;
        font-size: 0.8rem;
    }
}

/* Add safe area for devices with notch/home indicator */
@supports (bottom: env(safe-area-inset-bottom)) {
    @media (max-width: 1023px) {
        .donation-actions {
            padding-bottom: calc(1rem + env(safe-area-inset-bottom));
        }
        
        .page-container {
            padding-bottom: calc(120px + env(safe-area-inset-bottom));
        }
    }
    
    @media (max-width: 767px) {
        .page-container {
            padding-bottom: calc(100px + env(safe-area-inset-bottom));
        }
    }
}
</style>


<script>
// Tab functionality
function openTab(evt, tabId) {
  // Hide all tab contents
  const contents = document.querySelectorAll(".tab-content");
  const buttons = document.querySelectorAll(".tab-button");

  contents.forEach(content => {
    content.classList.remove("active");
    content.setAttribute("aria-hidden", "true");
  });
  
  buttons.forEach(button => {
    button.classList.remove("active");
    button.setAttribute("aria-selected", "false");
  });

  // Show selected tab
  const activeTab = document.getElementById(tabId);
  const activeButton = evt.currentTarget;
  
  if (activeTab && activeButton) {
    activeTab.classList.add("active");
    activeTab.setAttribute("aria-hidden", "false");
    activeButton.classList.add("active");
    activeButton.setAttribute("aria-selected", "true");
  }
}

// Progress bar animation
function animateProgress(elementId, targetValue, duration = 2000) {
  const progressBar = document.getElementById(elementId);
  if (!progressBar) return;
  
  let startTime = null;

  function animate(currentTime) {
    if (!startTime) startTime = currentTime;
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const currentValue = Math.floor(progress * targetValue);
    
    progressBar.value = currentValue;
    progressBar.style.setProperty('--value', currentValue);

    if (progress < 1) {
      requestAnimationFrame(animate);
    }
  }

  requestAnimationFrame(animate);
}

// Share functionality
function toggleShareOptions() {
  const shareOptions = document.getElementById('share-options');
  if (!shareOptions) return;
  
  const isVisible = shareOptions.style.display !== 'none';
  shareOptions.style.display = isVisible ? 'none' : 'block';
  
  // Add smooth animation
  if (!isVisible) {
    shareOptions.style.opacity = '0';
    shareOptions.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
      shareOptions.style.transition = 'all 0.3s ease';
      shareOptions.style.opacity = '1';
      shareOptions.style.transform = 'translateY(0)';
    }, 10);
  }
}

// News modal functionality
function setupNewsModal() {
  const newsImages = document.querySelectorAll('.news-image');
  const modal = document.getElementById('news-modal');
  const modalImage = document.getElementById('modal-image');
  const modalTitle = document.getElementById('modal-title');
  const modalDescription = document.getElementById('modal-description');
  const modalDate = document.getElementById('modal-date');

  if (!modal) return;

  newsImages.forEach(image => {
    image.addEventListener('click', function() {
      openNewsModal(this);
    });
    
    // Prevent drag
    image.addEventListener('dragstart', e => e.preventDefault());
    
    // Add hover effect
    image.style.cursor = 'pointer';
  });

  function openNewsModal(imageElement) {
    const { src, alt } = imageElement;
    const title = imageElement.dataset.title || alt;
    const description = imageElement.dataset.description || '';
    const date = imageElement.dataset.date || '';

    if (modalImage) {
      modalImage.src = src;
      modalImage.alt = alt;
    }
    if (modalTitle) modalTitle.textContent = title;
    if (modalDescription) modalDescription.textContent = description;
    if (modalDate) {
      modalDate.textContent = date ? `Dipublikasikan: ${date}` : '';
      modalDate.setAttribute('datetime', date);
    }

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus management for accessibility
    const closeButton = modal.querySelector('.modal-close');
    if (closeButton) closeButton.focus();
  }

  // Close modal events
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('show')) {
      closeModal();
    }
  });
}

// Close modal function
function closeModal() {
  const modal = document.getElementById('news-modal');
  if (!modal) return;
  
  modal.classList.remove('show');
  document.body.style.overflow = 'auto';
}

// Smooth scroll for anchor links
function initSmoothScroll() {
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach(link => {
    link.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
}

// Donation button click tracking
function setupDonationTracking() {
  const donateButton = document.querySelector('.btn-donate:not(.disabled)');
  if (donateButton) {
    donateButton.addEventListener('click', function(e) {
      // Add loading state
      const originalText = this.textContent;
      this.textContent = 'Memproses...';
      this.style.opacity = '0.7';
      
      // Restore after a short delay (in case of slow navigation)
      setTimeout(() => {
        this.textContent = originalText;
        this.style.opacity = '1';
      }, 3000);
    });
  }
}

// Copy to clipboard functionality
function copyToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  } else {
    // Fallback for older browsers
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    return new Promise((resolve, reject) => {
      document.execCommand('copy') ? resolve() : reject();
      textArea.remove();
    });
  }
}

// Add copy link functionality to share options
function addCopyLinkOption() {
  const shareButtons = document.querySelector('.share-buttons');
  if (!shareButtons) return;
  
  const copyButton = document.createElement('button');
  copyButton.className = 'share-btn copy-link';
  copyButton.innerHTML = '<i class="dashicons dashicons-admin-links"></i> Salin Link';
  copyButton.style.background = '#6c757d';
  copyButton.style.color = 'white';
  copyButton.style.border = 'none';
  copyButton.style.cursor = 'pointer';
  
  copyButton.addEventListener('click', function() {
    const currentUrl = window.location.href;
    copyToClipboard(currentUrl).then(() => {
      // Show success feedback
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="dashicons dashicons-yes"></i> Tersalin!';
      this.style.background = '#28a745';
      
      setTimeout(() => {
        this.innerHTML = originalText;
        this.style.background = '#6c757d';
      }, 2000);
    }).catch(() => {
      // Show error feedback
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="dashicons dashicons-no"></i> Gagal';
      this.style.background = '#dc3545';
      
      setTimeout(() => {
        this.innerHTML = originalText;
        this.style.background = '#6c757d';
      }, 2000);
    });
  });
  
  shareButtons.appendChild(copyButton);
}

// Lazy loading for images
function setupLazyLoading() {
  const images = document.querySelectorAll('img[data-src]');
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        img.classList.remove('lazy');
        observer.unobserve(img);
      }
    });
  });

  images.forEach(img => imageObserver.observe(img));
}

// Animate elements on scroll
function setupScrollAnimations() {
  const animateElements = document.querySelectorAll('.donatur-card, .news-card');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  animateElements.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
  });
}

// Handle form submissions (if any)
function setupFormHandling() {
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', function(e) {
      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        const originalText = submitButton.textContent || submitButton.value;
        submitButton.textContent = 'Memproses...';
        
        // Re-enable after timeout (in case of errors)
        setTimeout(() => {
          submitButton.disabled = false;
          submitButton.textContent = originalText;
        }, 10000);
      }
    });
  });
}

// Handle responsive navigation
function setupResponsiveHandling() {
  // Close share options when clicking outside
  document.addEventListener('click', function(e) {
    const shareOptions = document.getElementById('share-options');
    const shareButton = document.querySelector('.btn-share');
    
    if (shareOptions && shareButton && 
        !shareOptions.contains(e.target) && 
        !shareButton.contains(e.target)) {
      shareOptions.style.display = 'none';
    }
  });
}

// Get dynamic progress value from PHP
function getDynamicProgressValue() {
  const progressBar = document.getElementById('donation-progress');
  if (progressBar) {
    return parseInt(progressBar.getAttribute('value')) || 0;
  }
  return 0;
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // Get the actual progress value from the PHP-generated progress bar
  const progressValue = getDynamicProgressValue();
  
  // Initialize all functionality
  animateProgress('donation-progress', progressValue);
  setupNewsModal();
  setupDonationTracking();
  addCopyLinkOption();
  setupLazyLoading();
  setupScrollAnimations();
  setupFormHandling();
  setupResponsiveHandling();
  initSmoothScroll();
  
  // Add loading states to external links
  const externalLinks = document.querySelectorAll('a[href^="http"]:not([href*="' + window.location.hostname + '"])');
  externalLinks.forEach(link => {
    link.addEventListener('click', function() {
      this.style.opacity = '0.7';
    });
  });
  
  console.log('Campaign page initialized successfully');
});

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
  if (document.hidden) {
    // Pause any running animations
    const modal = document.getElementById('news-modal');
    if (modal && modal.classList.contains('show')) {
      // Keep modal open but pause any animations
    }
  } else {
    // Resume animations if needed
  }
});

// Error handling for images
document.addEventListener('DOMContentLoaded', function() {
  const images = document.querySelectorAll('img');
  images.forEach(img => {
    img.addEventListener('error', function() {
      // Replace with placeholder if image fails to load
      if (!this.dataset.errorHandled) {
        this.dataset.errorHandled = 'true';
        const title = this.alt || 'Image';
        this.src = `https://placehold.co/400x300/e9ecef/6c757d?text=${encodeURIComponent(title)}`;
      }
    });
  });
});

// JavaScript untuk toggle sidebar
document.addEventListener('DOMContentLoaded', function() {
    // Hanya jalankan pada mobile/tablet
    if (window.innerWidth <= 1023) {
        const header = document.querySelector('.donation-header');
        const content = document.querySelector('.donation-content');
        const toggle = document.querySelector('.sidebar-toggle');
        
        // Pastikan content ada class untuk toggle
        if (content && !content.classList.contains('expanded')) {
            content.classList.remove('expanded');
        }
        
        // Event listener untuk header click
        if (header) {
            header.addEventListener('click', function() {
                if (content) {
                    content.classList.toggle('expanded');
                }
                if (toggle) {
                    toggle.classList.toggle('expanded');
                }
            });
        }
        
        // Event listener untuk toggle button click
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent header click
                if (content) {
                    content.classList.toggle('expanded');
                }
                toggle.classList.toggle('expanded');
            });
        }
    }
});

// Handle resize
window.addEventListener('resize', function() {
    const content = document.querySelector('.donation-content');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth > 1023) {
        // Desktop: always show content
        if (content) {
            content.classList.remove('expanded');
            content.style.maxHeight = '';
        }
        if (toggle) {
            toggle.classList.remove('expanded');
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    function initSidebarToggle() {
        if (window.innerWidth <= 1023) {
            const header = document.querySelector('.donation-header');
            const content = document.querySelector('.donation-content');
            const toggle = document.querySelector('.sidebar-toggle');
            
            // Reset state
            if (content) {
                content.classList.remove('expanded');
            }
            if (toggle) {
                toggle.classList.remove('expanded');
            }
            
            // Header click handler
            if (header && content && toggle) {
                header.addEventListener('click', function(e) {
                    e.preventDefault();
                    content.classList.toggle('expanded');
                    toggle.classList.toggle('expanded');
                });
            }
        }
    }
    
    // Initialize
    initSidebarToggle();
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const content = document.querySelector('.donation-content');
        const toggle = document.querySelector('.sidebar-toggle');
        
        if (window.innerWidth > 1023) {
            // Desktop: remove mobile classes
            if (content) {
                content.classList.remove('expanded');
                content.style.maxHeight = '';
            }
            if (toggle) {
                toggle.classList.remove('expanded');
            }
        } else {
            // Mobile: reinitialize
            initSidebarToggle();
        }
    });
});

</script>