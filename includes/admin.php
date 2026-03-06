<?php

/**
 * Clear campaign fields after Gutenberg save to prevent stale form data.
 * Server-side protection prevents duplicate scheduling, this is just for UX.
 */
add_action('enqueue_block_editor_assets', function() {
    global $post;
    if (!$post || $post->post_type !== 'press_release') {
        return;
    }

    $script = "
    (function($) {
        var wasSaving = false;

        wp.data.subscribe(function() {
            var isSaving = wp.data.select('core/editor').isSavingPost();
            var isAutosaving = wp.data.select('core/editor').isAutosavingPost();

            if (isSaving && !isAutosaving) {
                wasSaving = true;
            }

            // After save completes
            if (wasSaving && !isSaving) {
                wasSaving = false;

                setTimeout(function() {
                    // Check if send_campaign was enabled
                    var sendToggle = $('input[name=\"acf[field_68d0f78166ce7]\"]');
                    var sendTimeInput = $('input[name=\"acf[field_68d12de00ff3e]\"]');

                    if (sendToggle.is(':checked') && sendTimeInput.val()) {
                        // Turn off the toggle visually
                        sendToggle.prop('checked', false).trigger('change');
                        // Clear the datetime field
                        sendTimeInput.val('');
                        // Update ACF's internal state for the toggle
                        if (typeof acf !== 'undefined') {
                            var field = acf.getField('field_68d0f78166ce7');
                            if (field) {
                                field.\$input().prop('checked', false).trigger('change');
                            }
                        }
                    }
                }, 300);
            }
        });
    })(jQuery);
    ";

    wp_add_inline_script('wp-edit-post', $script);
});

// Add a custom column to the post overview for newsletter campaign ID
add_filter('manage_press_release_posts_columns', function($columns) {
    $columns['newsletter_campaign'] = __('Newsletter', 'post-campaigns');
    return $columns;
});

add_action('manage_press_release_posts_custom_column', function($column, $post_id) {
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
    echo '<li><a href="' . MAILWIZZ_BASE_URL . '/campaigns/' . urlencode($campaign_id) . '" target="_blank"> ' . __('See newsletter', 'post-campaigns') . '</a></li>';
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
    if ($post->post_type !== 'press_release') {
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
    $actions['post_campaigns_clear_newsletter_stats'] = '<a href="' . esc_url($url) . '"style="margin-top:0px;">' . esc_html__('Reload newsletter stats', 'post-campaigns') . '</a>';
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

// Handle the cancel sending action.
add_action('admin_init', function() {
    global $pagenow;
    if ($pagenow !== 'post.php') {
        return;
    }
    if (
        isset($_GET['post_campaigns_cancel_sending'], $_GET['post'], $_GET['_wpnonce']) &&
        $_GET['post_campaigns_cancel_sending'] == 1 &&
        current_user_can('edit_post', intval($_GET['post']))
    ) {
        $post_id = intval($_GET['post']);
        $nonce = $_GET['_wpnonce'];
        if (!wp_verify_nonce($nonce, 'post_campaigns_cancel_sending_' . $post_id)) {
            wp_die(__('Security check failed', 'post-campaigns'));
        }

        // Unschedule the cron event
        $next_scheduled = wp_next_scheduled('post_campaigns_send_newsletter', array($post_id));
        if ($next_scheduled) {
            wp_unschedule_event($next_scheduled, 'post_campaigns_send_newsletter', array($post_id));
        }

        // Reset the send campaign field
        update_field('send_campaign', false, $post_id);
        update_field('newsletter_sendtime', '', $post_id);

        // Set a transient for the admin notice
        set_transient('post_campaigns_cancelled_' . $post_id, true, 30);

        // Redirect back to the post edit screen.
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }
}, 10);

// Show admin notice after cancelling a scheduled campaign
add_action('admin_notices', function() {
    global $pagenow;
    if ($pagenow !== 'post.php' || !isset($_GET['post'])) {
        return;
    }
    $post_id = intval($_GET['post']);
    if (get_transient('post_campaigns_cancelled_' . $post_id)) {
        delete_transient('post_campaigns_cancelled_' . $post_id);
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Scheduled newsletter has been cancelled.', 'post-campaigns')
        );
    }
});

// Handle the delete campaign action.
add_action('admin_init', function() {
    global $pagenow;
    if ($pagenow !== 'post.php') {
        return;
    }
    if (
        isset($_GET['post_campaigns_delete_campaign'], $_GET['post'], $_GET['_wpnonce']) &&
        $_GET['post_campaigns_delete_campaign'] == 1 &&
        current_user_can('edit_post', intval($_GET['post']))
    ) {
        $post_id = intval($_GET['post']);
        $nonce = $_GET['_wpnonce'];
        if (!wp_verify_nonce($nonce, 'post_campaigns_delete_campaign_' . $post_id)) {
            wp_die(__('Security check failed', 'post-campaigns'));
        }

        // Clear the campaign ID and cached stats
        update_field('newsletter_campaign_id', '', $post_id);
        delete_post_meta($post_id, 'post_campaigns_newsletter_campaign_stats');

        // Set a transient for the admin notice
        set_transient('post_campaigns_deleted_' . $post_id, true, 30);

        // Redirect back to the post edit screen.
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }
}, 10);

// Show admin notice after deleting a campaign
add_action('admin_notices', function() {
    global $pagenow;
    if ($pagenow !== 'post.php' || !isset($_GET['post'])) {
        return;
    }
    $post_id = intval($_GET['post']);
    if (get_transient('post_campaigns_deleted_' . $post_id)) {
        delete_transient('post_campaigns_deleted_' . $post_id);
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Newsletter record has been deleted. You can now schedule a new newsletter for this post.', 'post-campaigns')
        );
    }
});

// Handle the send test mail action.
add_action('admin_init', function() {
    global $pagenow;
    if ($pagenow !== 'post.php') {
        return;
    }
    if (
        isset($_GET['post_campaigns_send_test_mail'], $_GET['post'], $_GET['_wpnonce']) &&
        $_GET['post_campaigns_send_test_mail'] == 1 &&
        current_user_can('edit_post', intval($_GET['post']))
    ) {
        $post_id = intval($_GET['post']);
        $nonce = $_GET['_wpnonce'];
        if (!wp_verify_nonce($nonce, 'post_campaigns_send_test_mail_' . $post_id)) {
            wp_die(__('Security check failed', 'post-campaigns'));
        }

        // Get selected test list ID, fallback to constant
        $test_list_id = '';
        if (isset($_GET['test_list_id']) && !empty($_GET['test_list_id'])) {
            $test_list_id = sanitize_text_field($_GET['test_list_id']);
        } elseif (defined('MAILWIZZ_TEST_LIST_ID')) {
            $test_list_id = MAILWIZZ_TEST_LIST_ID;
        }

        if (empty($test_list_id)) {
            set_transient('post_campaigns_test_mail_error_' . $post_id, __('No test list selected.', 'post-campaigns'), 30);
            wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
            exit;
        }

        // Schedule test newsletter with the selected list ID
        wp_schedule_single_event(time(), 'post_campaigns_send_test_newsletter', array($post_id, $test_list_id));

        // Set a transient for the admin notice
        set_transient('post_campaigns_test_mail_sent_' . $post_id, true, 30);

        // Redirect back to the post edit screen.
        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }
}, 10);

// Show admin notice after scheduling a test mail
add_action('admin_notices', function() {
    global $pagenow;
    if ($pagenow !== 'post.php' || !isset($_GET['post'])) {
        return;
    }
    $post_id = intval($_GET['post']);
    if (get_transient('post_campaigns_test_mail_sent_' . $post_id)) {
        delete_transient('post_campaigns_test_mail_sent_' . $post_id);
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Test newsletter has been scheduled and will be sent to the test list shortly.', 'post-campaigns')
        );
    }
});

// Add "Reload campaign stats" to bulk actions dropdown for posts
add_filter('bulk_actions-edit-press_release', function($bulk_actions) {
    $bulk_actions['post_campaigns_reload_campaign_stats'] = __('Reload newsletter stats', 'post-campaigns');
    return $bulk_actions;
});

// Handle the bulk action for reloading campaign stats
add_filter('handle_bulk_actions-edit-press_release', function($redirect_to, $doaction, $post_ids) {
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
                esc_html(sprintf(_n('Reloaded newsletter stats for %d post.', 'Reloaded newsletter stats for %d posts.', $count, 'post-campaigns'), $count))
            );
        }
    }
});

// Show admin notice for test mail errors
add_action('admin_notices', function() {
    global $pagenow;
    if ($pagenow !== 'post.php' || !isset($_GET['post'])) {
        return;
    }
    $post_id = intval($_GET['post']);
    $error = get_transient('post_campaigns_test_mail_error_' . $post_id);
    if ($error) {
        delete_transient('post_campaigns_test_mail_error_' . $post_id);
        printf(
            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
            esc_html($error)
        );
    }
});

// Show admin notice for test list sync errors
add_action('admin_notices', function() {
    $transient_key = 'post_campaigns_sync_errors_' . get_current_user_id();
    $errors = get_transient($transient_key);

    if (!empty($errors) && is_array($errors)) {
        delete_transient($transient_key);
        foreach ($errors as $error) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html($error)
            );
        }
    }
});
