<?php
// Add a custom column to the post overview for newsletter campaign ID
add_filter('manage_post_posts_columns', function($columns) {
    $columns['newsletter_campaign'] = __('Newsletter campaign', 'post-campaigns');
    return $columns;
});

add_action('manage_post_posts_custom_column', function($column, $post_id) {
    if ($column !== 'newsletter_campaign') {
        return;
    }

    $campaign_id = get_field('newsletter_campaign_id', $post_id);

    if (!$campaign_id) {
        echo '-';
        echo '<br />';
        return;
    }

    $stats = post_campaigns_get_newsletter_campaign_stats($post_id, $campaign_id);

    if (is_wp_error($stats)) {
        echo '-';
        echo '<br />';
        return;
    }

    $default_stats = [
        [
            'name'  => __('Successful deliveries', 'post-campaigns'),
            'stat'  => $stats['delivery_success_count'] ?? 0,
            'rate'  => $stats['delivery_success_rate'] ?? 0,
        ],
    ];

    /**
     * Filter to allow adding more stats to the newsletter campaign column.
     *
     * @param array $stats_list The array of stats to display. Each stat is an array with keys: name, stat, rate.
     * @param array $stats      The raw stats array from the API.
     * @param string $campaign_id The campaign ID.
     */
    $stats_list = apply_filters('post_campaigns_newsletter_campaign_stats_list', $default_stats, $stats, $campaign_id);

    echo '<ul style="margin: 6px 0; padding: 0; list-style: none;">';
    foreach ($stats_list as $stat_item) {
        $name = esc_html($stat_item['name'] ?? '');
        $stat = esc_html($stat_item['stat'] ?? '');
        $rate = isset($stat_item['rate']) ? ' - ' . esc_html($stat_item['rate']) . '%' : '';
        echo '<li>' . $name . ': ' . $stat . $rate . '</li>';
    }
    echo '<li><a href="' . MAILWIZZ_BASE_URL . '/campaigns/' . urlencode($campaign_id) . '" target="_blank"> ' . __('See campaign', 'post-campaigns') . '</a></li>';
    echo '<li><p> ' . __('Last updated:', 'post-campaigns') . ': ' . wp_date('Y-m-d H:i:s', $stats['timestamp']) . '</p></li>';
    echo '</ul>';


}, 10, 2);


/**
 * Get the stats for a newsletter campaign.
 * Saves in transient with a 10 minute expiration.
 */

 function post_campaigns_get_newsletter_campaign_stats($post_id, $campaign_id) {

    $stats = get_post_meta($post_id, 'post_campaigns_newsletter_campaign_stats', true);
    if ($stats) {
        return $stats;
    }

    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/campaigns/' . urlencode($campaign_id) . '/stats';
    $request_args = array(
        'headers' => array(
            'X-Api-Key' => MAILWIZZ_API_KEY,
            'Content-Type' => 'application/json',
        ),
    );

    $response = wp_remote_get($api_url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $stats = array_merge($data['data'], ['timestamp' => time()]);

    update_post_meta($post_id, 'post_campaigns_newsletter_campaign_stats', $stats);

    return $stats;
}

// Add a "Clear Stats Cache" button to the post edit screen for posts with a campaign ID.
add_filter('post_row_actions', function($actions, $post) {
    if ($post->post_type !== 'post') {
        return $actions;
    }
    $campaign_id = get_field('newsletter_campaign_id', $post->ID);
    if (!$campaign_id) {
        return $actions;
    }
    $url = add_query_arg(array(
        'post_campaigns_clear_newsletter_stats' => 1,
        'post' => $post->ID,
        '_wpnonce' => wp_create_nonce('post_campaigns_clear_newsletter_stats_' . $post->ID),
    ));
    $actions['post_campaigns_clear_newsletter_stats'] = '<a href="' . esc_url($url) . '"style="margin-top:0px;">' . esc_html__('Reload campaign stats', 'post-campaigns') . '</a>';
    return $actions;
}, 10, 2);

// Handle the clear transient action.
add_action('admin_init', function() {
    global $pagenow;
    if ($pagenow !== 'edit.php') {
        return;
    }
    if (
        isset($_GET['post_campaigns_clear_newsletter_stats'], $_GET['post'], $_GET['_wpnonce']) &&
        $_GET['post_campaigns_clear_newsletter_stats'] == 1 &&
        current_user_can('edit_post', intval($_GET['post']))
    ) {
        $post_id = intval($_GET['post']);
        $nonce = $_GET['_wpnonce'];
        if (!wp_verify_nonce($nonce, 'post_campaigns_clear_newsletter_stats_' . $post_id)) {
            wp_die(__('Security check failed', 'post-campaigns'));
        }
        $campaign_id = get_field('newsletter_campaign_id', $post_id);
        if ($campaign_id) {
            delete_post_meta($post_id, 'post_campaigns_newsletter_campaign_stats');
        }
        // Redirect back to the post edit screen.
        wp_safe_redirect(remove_query_arg(array('post_campaigns_clear_newsletter_stats', '_wpnonce'), wp_get_referer() ?: admin_url('post.php?post=' . $post_id . '&action=edit')));
        exit;
    }
}, 10);

// Add "Reload campaign stats" to bulk actions dropdown for posts
add_filter('bulk_actions-edit-post', function($bulk_actions) {
    $bulk_actions['post_campaigns_reload_campaign_stats'] = __('Reload campaign stats', 'post-campaigns');
    return $bulk_actions;
});

// Handle the bulk action for reloading campaign stats
add_filter('handle_bulk_actions-edit-post', function($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'post_campaigns_reload_campaign_stats') {
        return $redirect_to;
    }

    $reloaded = 0;
    foreach ($post_ids as $post_id) {
        $campaign_id = get_field('newsletter_campaign_id', $post_id);
        if ($campaign_id) {
            delete_post_meta($post_id, 'post_campaigns_newsletter_campaign_stats');
            $reloaded++;
        }
    }

    $redirect_to = add_query_arg(array(
        'post_campaigns_reloaded_campaign_stats' => $reloaded,
    ), $redirect_to);

    return $redirect_to;
}, 10, 3);

// Show admin notice after bulk reload
add_action('admin_notices', function() {
    if (!empty($_REQUEST['post_campaigns_reloaded_campaign_stats'])) {
        $count = intval($_REQUEST['post_campaigns_reloaded_campaign_stats']);
        if ($count > 0) {
            printf(
                '<div id="message" class="updated notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(_n('Reloaded campaign stats for %d post.', 'Reloaded campaign stats for %d posts.', $count, 'post-campaigns'), $count))
            );
        }
    }
});
