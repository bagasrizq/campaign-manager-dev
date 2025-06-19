<?php
/**
 * Campaign Custom Post Type Handler
 */

class Campaign_Manager_CPT {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_filter('manage_campaign_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_campaign_posts_custom_column', array($this, 'fill_admin_columns'), 10, 2);
    }
    
    public function register_post_type() {
        $labels = array(
            'name'                  => __('Campaigns', 'campaign-manager'),
            'singular_name'         => __('Campaign', 'campaign-manager'),
            'menu_name'             => __('Campaign Manager', 'campaign-manager'),
            'name_admin_bar'        => __('Campaign', 'campaign-manager'),
            'archives'              => __('Campaign Archives', 'campaign-manager'),
            'attributes'            => __('Campaign Attributes', 'campaign-manager'),
            'parent_item_colon'     => __('Parent Campaign:', 'campaign-manager'),
            'all_items'             => __('All Campaigns', 'campaign-manager'),
            'add_new_item'          => __('Add New Campaign', 'campaign-manager'),
            'add_new'              => __('Add New', 'campaign-manager'),
            'new_item'             => __('New Campaign', 'campaign-manager'),
            'edit_item'             => __('Edit Campaign', 'campaign-manager'),
            'update_item'           => __('Update Campaign', 'campaign-manager'),
            'view_item'             => __('View Campaign', 'campaign-manager'),
            'view_items'            => __('View Campaigns', 'campaign-manager'),
            'search_items'          => __('Search Campaign', 'campaign-manager'),
            'not_found'             => __('Not found', 'campaign-manager'),
            'not_found_in_trash'    => __('Not found in Trash', 'campaign-manager'),
        );
        
        $args = array(
            'label'                 => __('Campaign', 'campaign-manager'),
            'description'           => __('Campaign manager for donations', 'campaign-manager'),
            'labels'                => $labels,
            'supports'              => array('title', 'editor', 'thumbnail', 'excerpt'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20,
            'menu_icon'             => 'dashicons-heart',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'           => true,
            'has_archive'          => true,
            'exclude_from_search'  => false,
            'publicly_queryable'    => true,
            'capability_type'      => 'post',
            'show_in_rest'          => true,
        );
        
        register_post_type('campaign', $args);
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'campaign_settings',
            __('Campaign Settings', 'campaign-manager'),
            array($this, 'meta_box_callback'),
            'campaign',
            'normal',
            'high'
        );
        
        add_meta_box(
            'campaign_stats',
            __('Campaign Statistics', 'campaign-manager'),
            array($this, 'stats_meta_box_callback'),
            'campaign',
            'side',
            'default'
        );
    }
    
    public function meta_box_callback($post) {
        wp_nonce_field('campaign_manager_save_meta', 'campaign_manager_nonce');
        
        $target = get_post_meta($post->ID, '_campaign_target', true);
        $deadline = get_post_meta($post->ID, '_campaign_deadline', true);
        $status = get_post_meta($post->ID, '_campaign_status', true);
        $currency = get_post_meta($post->ID, '_campaign_currency', true) ?: 'IDR';
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="campaign_target"><?php _e('Target Amount', 'campaign-manager'); ?></label>
                </th>
                <td>
                    <input type="number" name="campaign_target" id="campaign_target" 
                           value="<?php echo esc_attr($target); ?>" class="regular-text" required>
                    <select name="campaign_currency" id="campaign_currency">
                        <option value="IDR" <?php selected($currency, 'IDR'); ?>>IDR (Rp)</option>
                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="campaign_deadline"><?php _e('Deadline', 'campaign-manager'); ?></label>
                </th>
                <td>
                    <input type="date" name="campaign_deadline" id="campaign_deadline" 
                           value="<?php echo esc_attr($deadline); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="campaign_status"><?php _e('Status', 'campaign-manager'); ?></label>
                </th>
                <td>
                    <select name="campaign_status" id="campaign_status">
                        <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'campaign-manager'); ?></option>
                        <option value="paused" <?php selected($status, 'paused'); ?>><?php _e('Paused', 'campaign-manager'); ?></option>
                        <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('Completed', 'campaign-manager'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function stats_meta_box_callback($post) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'campaign_submissions';
        
        $total_donations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE campaign_id = %d AND status = 'completed'",
            $post->ID
        ));
        
        $total_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_name WHERE campaign_id = %d AND status = 'completed'",
            $post->ID
        ));
        
        $target = get_post_meta($post->ID, '_campaign_target', true);
        $percentage = $target ? ($total_amount / $target) * 100 : 0;
        ?>
        <div class="campaign-stats">
            <p><strong><?php _e('Total Donations:', 'campaign-manager'); ?></strong> <?php echo $total_donations; ?></p>
            <p><strong><?php _e('Amount Raised:', 'campaign-manager'); ?></strong> Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></p>
            <p><strong><?php _e('Progress:', 'campaign-manager'); ?></strong> <?php echo round($percentage, 1); ?>%</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
            </div>
        </div>
        <style>
            .progress-bar {
                width: 100%;
                height: 20px;
                background: #f1f1f1;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 10px;
            }
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                transition: width 0.3s ease;
            }
        </style>
        <?php
    }
    
    public function save_meta_data($post_id) {
        if (!isset($_POST['campaign_manager_nonce']) || 
            !wp_verify_nonce($_POST['campaign_manager_nonce'], 'campaign_manager_save_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $fields = array('campaign_target', 'campaign_deadline', 'campaign_status', 'campaign_currency');
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    public function add_admin_columns($columns) {
        $new_columns = array(
            'target' => __('Target', 'campaign-manager'),
            'raised' => __('Raised', 'campaign-manager'),
            'deadline' => __('Deadline', 'campaign-manager'),
            'status' => __('Status', 'campaign-manager'),
            'submissions' => __('Donations', 'campaign-manager')
        );
        
        return array_merge($columns, $new_columns);
    }
    
    public function fill_admin_columns($column, $post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'campaign_submissions';
        
        switch ($column) {
            case 'target':
                $target = get_post_meta($post_id, '_campaign_target', true);
                $currency = get_post_meta($post_id, '_campaign_currency', true) ?: 'IDR';
                $symbol = $currency === 'IDR' ? 'Rp ' : '$';
                echo $target ? $symbol . number_format($target, 0, ',', '.') : '-';
                break;
                
            case 'raised':
                $raised = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(amount) FROM $table_name WHERE campaign_id = %d AND status = 'completed'",
                    $post_id
                ));
                $currency = get_post_meta($post_id, '_campaign_currency', true) ?: 'IDR';
                $symbol = $currency === 'IDR' ? 'Rp ' : '$';
                echo $symbol . number_format($raised ?: 0, 0, ',', '.');
                break;
                
            case 'deadline':
                $deadline = get_post_meta($post_id, '_campaign_deadline', true);
                if ($deadline) {
                    $date = date('d/m/Y', strtotime($deadline));
                    $is_expired = strtotime($deadline) < time();
                    echo $is_expired ? '<span style="color: red;">' . $date . ' (Expired)</span>' : $date;
                } else {
                    echo '-';
                }
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_campaign_status', true) ?: 'active';
                $colors = array(
                    'active' => 'green',
                    'paused' => 'orange',
                    'completed' => 'blue'
                );
                echo '<span style="color: ' . $colors[$status] . ';">' . ucfirst($status) . '</span>';
                break;
                
            case 'submissions':
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE campaign_id = %d",
                    $post_id
                ));
                echo $count;
                break;
        }
    }
}