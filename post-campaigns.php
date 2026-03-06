<?php

/**
 * Plugin Name: Post Campaigns
 * Description: MailWizz Integration for WP. Send MailWizz campaigns via MailWizz API
 * Version: 1.3.0
 * Author: Netkant
 * Author URI: https://netkant.dk
 * Text Domain: post-campaigns
 */

/**
 * THIS FILE REQUIRES ACF:
 */

/**
 * WP-CONFIG.PHP SETTINGS.
 * MAILWIZZ_BASE_URL
 * MAILWIZZ_API_KEY
 * MAILWIZZ_LIST_ID
 * MAILWIZZ_TEST_LIST_ID (optional)
 * MAILWIZZ_FROM_NAME
 * MAILWIZZ_FROM_EMAIL
 * MAILWIZZ_REPLY_TO
 */

if (function_exists('acf_add_local_field_group')) {
    require_once plugin_dir_path(__FILE__) . 'acf/acf-fields.php';
}

require_once plugin_dir_path(__FILE__) . 'includes/admin.php';

/**
 * Create a campaign by posting to the API-URL/campaigns endpoint.
 *
 * @param array $args Optional. Override default campaign data.
 * @return array|WP_Error The API response or WP_Error on failure.
 */
function post_campaigns_create_campaign($post_title, $post_content, $args = array())
{
    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/campaigns';
    $api_key = MAILWIZZ_API_KEY;

    if (empty(trim($post_title))) {
        $post_title = __('Unnamed newsletter test', 'post-campaigns');
    }

    $list_uid = isset($args['list_uid']) ? $args['list_uid'] : MAILWIZZ_LIST_ID;

    $campaign = array(
        'campaign' => array(
            'name'       => 'Nyhedsbrev - ' . $post_title,
            'type'       => 'regular',
            'from_name'  => MAILWIZZ_FROM_NAME,
            'from_email' => MAILWIZZ_FROM_EMAIL,
            'subject'    => $post_title,
            'reply_to'   => MAILWIZZ_REPLY_TO,
            'send_at'    => wp_date('Y-m-d H:i:s'),
            'list_uid'   => $list_uid,
            'options'    => array(
                'open_tracking'    => 'no',
                'url_tracking'     => 'no',
                'json_feed'        => 'no',
                'xml_feed'         => 'no',
                'plain_text_email' => 'yes',
            ),
            'template' => array(
                'content'         => base64_encode($post_content),
                'inline_css'      => 'no',
                'auto_plain_text' => 'yes',
            ),
        ),
    );

    $request_args = array(
        'headers' => array(
            'X-Api-Key' => $api_key,
        ),
        'body'    => $campaign,
        'timeout' => 30,
    );

    $response = wp_remote_post($api_url, $request_args);

    echo '<hr /><pre>';
    print_r($response);
    echo '</pre>';

    if (is_wp_error($response)) {
        error_log('MailWizz API request error: ' . $response->get_error_message());
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $resp_body = wp_remote_retrieve_body($response);

    // Log response on error for debugging
    if ($http_code >= 400) {
        return new WP_Error('mailwizz_api_error', 'MailWizz API request error: ' . $response->get_error_message());
    }

    return json_decode($resp_body, true);
}

/**
 * Create a new list in MailWizz.
 *
 * @param string $list_name The name for the new list.
 * @return array|WP_Error The API response with list_uid or WP_Error on failure.
 */
function post_campaigns_create_mailwizz_list($list_name)
{
    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/lists';
    $api_key = MAILWIZZ_API_KEY;

    $list_data = array(
        'general' => array(
            'name'        => $list_name,
            'description' => sprintf(__('Test list created from WordPress: %s', 'post-campaigns'), $list_name),
            'opt_in' => 'single',
        ),
        'defaults' => array(
            'from_name'  => MAILWIZZ_FROM_NAME,
            'from_email' => MAILWIZZ_FROM_EMAIL,
            'reply_to'   => MAILWIZZ_REPLY_TO,
        ),
        'company' => array(
            'name'       => 'Test Company',
            'country_id' => 57,
            'address_1'  => 'Test Address 1',
            'city'       => 'Copenhagen',
            'zip_code'   => '1000',
        ),
    );

    $request_args = array(
        'headers' => array(
            'X-Api-Key' => $api_key,
        ),
        'body'    => $list_data,
        'timeout' => 30,
    );

    $response = wp_remote_post($api_url, $request_args);

    if (is_wp_error($response)) {
        error_log('Post Campaigns: Failed to create list - ' . $response->get_error_message());
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $resp_body = wp_remote_retrieve_body($response);
    $data = json_decode($resp_body, true);

    // Log the full response for debugging
    error_log('Post Campaigns: Create list API response (HTTP ' . $http_code . '): ' . $resp_body);

    // Check for error status or error in response
    if ($http_code >= 400 || (isset($data['status']) && $data['status'] === 'error')) {
        $error_msg = 'Unknown error';
        if (isset($data['error'])) {
            // Handle array or string error messages
            $error_msg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
        } elseif (isset($data['message'])) {
            $error_msg = is_array($data['message']) ? json_encode($data['message']) : $data['message'];
        }
        error_log('Post Campaigns: Create list error - ' . $error_msg);
        return new WP_Error('mailwizz_api_error', $error_msg);
    }

    // Check if we got a list_uid back
    if (empty($data['list_uid'])) {
        error_log('Post Campaigns: Create list response missing list_uid');
        return new WP_Error('mailwizz_api_error', 'No list_uid returned from API');
    }

    return $data;
}

/**
 * Delete a list from MailWizz.
 *
 * @param string $list_uid The MailWizz list UID to delete.
 * @return array|WP_Error The API response or WP_Error on failure.
 */
function post_campaigns_delete_mailwizz_list($list_uid)
{
    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/lists/' . urlencode($list_uid);
    $api_key = MAILWIZZ_API_KEY;

    $request_args = array(
        'method'  => 'DELETE',
        'headers' => array(
            'X-Api-Key' => $api_key,
        ),
        'timeout' => 30,
    );

    $response = wp_remote_request($api_url, $request_args);

    if (is_wp_error($response)) {
        error_log('Post Campaigns: Failed to delete list ' . $list_uid . ' - ' . $response->get_error_message());
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);

    if ($http_code >= 400) {
        return new WP_Error('mailwizz_api_error', 'Failed to delete list');
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * Get all subscribers from a MailWizz list.
 *
 * @param string $list_uid The MailWizz list UID.
 * @return array|WP_Error Array of subscribers or WP_Error on failure.
 */
function post_campaigns_get_mailwizz_subscribers($list_uid)
{
    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/lists/' . urlencode($list_uid) . '/subscribers';
    $api_key = MAILWIZZ_API_KEY;

    $request_args = array(
        'headers' => array(
            'X-Api-Key' => $api_key,
        ),
        'timeout' => 30,
    );

    $response = wp_remote_get($api_url, $request_args);

    if (is_wp_error($response)) {
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($http_code >= 400) {
        return new WP_Error('mailwizz_api_error', 'Failed to get subscribers');
    }

    return isset($data['data']['records']) ? $data['data']['records'] : array();
}

/**
 * Add a subscriber to a MailWizz list.
 *
 * @param string $list_uid The MailWizz list UID.
 * @param string $email The subscriber email address.
 * @return array|WP_Error The API response or WP_Error on failure.
 */
function post_campaigns_add_mailwizz_subscriber($list_uid, $email)
{
    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/lists/' . urlencode($list_uid) . '/subscribers';
    $api_key = MAILWIZZ_API_KEY;

    $subscriber_data = array(
        'EMAIL'  => $email,
        'status' => 'confirmed',
    );

    $request_args = array(
        'headers' => array(
            'X-Api-Key' => $api_key,
        ),
        'body'    => $subscriber_data,
        'timeout' => 30,
    );

    $response = wp_remote_post($api_url, $request_args);

    if (is_wp_error($response)) {
        error_log('Post Campaigns: Failed to add subscriber ' . $email . ' - ' . $response->get_error_message());
        return $response;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * Delete a subscriber from a MailWizz list.
 *
 * @param string $list_uid The MailWizz list UID.
 * @param string $subscriber_uid The subscriber UID to delete.
 * @return array|WP_Error The API response or WP_Error on failure.
 */
function post_campaigns_delete_mailwizz_subscriber($list_uid, $subscriber_uid)
{
    $api_url = MAILWIZZ_BASE_URL . '/api/index.php/lists/' . urlencode($list_uid) . '/subscribers/' . urlencode($subscriber_uid);
    $api_key = MAILWIZZ_API_KEY;

    $request_args = array(
        'method'  => 'DELETE',
        'headers' => array(
            'X-Api-Key' => $api_key,
        ),
        'timeout' => 30,
    );

    $response = wp_remote_request($api_url, $request_args);

    if (is_wp_error($response)) {
        return $response;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * Handles the sending of the newsletter.
 */
function post_campaigns_send_newsletter_handler($post_ID)
{
    $post = get_post($post_ID);
    if (!$post) {
        return;
    }

    // Prevent duplicate campaigns - if a campaign ID already exists, do not send again
    $existing_campaign_id = get_field('newsletter_campaign_id', $post_ID);
    if (!empty($existing_campaign_id)) {
        error_log('Post Campaigns: Attempted to create duplicate campaign for post ' . $post_ID . '. Existing campaign ID: ' . $existing_campaign_id);
        return;
    }

    ob_start();
    $template = apply_filters('post_campaigns_template', plugin_dir_path(__FILE__) . 'templates/default-mail-template.php');

    load_template($template, true, array('post' => $post));
    $post_content = ob_get_clean();

    $response = post_campaigns_create_campaign($post->post_title, $post_content);
    if (is_wp_error($response)) {
        error_log('MailWizz API request error: ' . $response->get_error_message());
        wp_schedule_single_event(date('U', strtotime('+5 minutes')), 'post_campaigns_send_newsletter', array($post_ID));
        return;
    }

    $campaign_id = $response['campaign_uid'];

    update_field('newsletter_campaign_id', $campaign_id, $post_ID);

    $newsletter_campaign_id_field = get_field_object('newsletter_campaign_id', $post_ID);
    $newsletter_campaign_id_field['value'] = $campaign_id;

    update_field('send_campaign', false, $post_ID);
    update_field('newsletter_sendtime', '', $post_ID);
}

add_action('post_campaigns_send_newsletter', 'post_campaigns_send_newsletter_handler');

/**
 * Handles sending a test newsletter to the specified test list.
 *
 * @param int $post_ID The post ID.
 * @param string $test_list_id Optional. The test list UID. Falls back to MAILWIZZ_TEST_LIST_ID.
 */
function post_campaigns_send_test_newsletter_handler($post_ID, $test_list_id = '')
{
    $post = get_post($post_ID);
    if (!$post) {
        return;
    }

    // Fallback to constant if no list_id provided
    if (empty($test_list_id) && defined('MAILWIZZ_TEST_LIST_ID')) {
        $test_list_id = MAILWIZZ_TEST_LIST_ID;
    }

    if (empty($test_list_id)) {
        error_log('Post Campaigns: No test list ID provided for post ' . $post_ID);
        return;
    }

    ob_start();
    $template = apply_filters('post_campaigns_template', plugin_dir_path(__FILE__) . 'templates/default-mail-template.php');

    load_template($template, true, array('post' => $post));
    $post_content = ob_get_clean();

    $response = post_campaigns_create_campaign($post->post_title, $post_content, array(
        'list_uid' => $test_list_id,
    ));

    if (is_wp_error($response)) {
        error_log('Post Campaigns: Test mail API error for post ' . $post_ID . ': ' . $response->get_error_message());
        return;
    }
}

add_action('post_campaigns_send_test_newsletter', 'post_campaigns_send_test_newsletter_handler', 10, 2);

/**
 * Add a cron schedule for the newsletter campaign on save_post.
 */
add_action('acf/save_post', 'post_campaigns_add_newsletter_cron_schedule', 10, 1);
function post_campaigns_add_newsletter_cron_schedule($post_ID)
{
    $post = get_post($post_ID);

    if ($post->post_type !== 'press_release') {
        return;
    }

    $next_schedule = wp_next_scheduled('post_campaigns_send_newsletter', array($post_ID));

    // Prevent scheduling if a campaign already exists for this post
    $existing_campaign_id = get_field('newsletter_campaign_id', $post_ID);
    if (!empty($existing_campaign_id)) {
        // Unschedule any existing cron and reset fields
        if ($next_schedule) {
            wp_unschedule_event($next_schedule, 'post_campaigns_send_newsletter', array($post_ID));
        }
        update_field('send_campaign', false, $post_ID);
        update_field('newsletter_sendtime', '', $post_ID);
        return;
    }

    // If a cron is already scheduled, don't allow changes from stale form data (Gutenberg doesn't reload)
    // User must cancel the scheduled campaign first before making changes
    if ($next_schedule) {
        update_field('send_campaign', true, $post_ID);
        // Restore the scheduled time to prevent stale form data from overwriting
        update_field('newsletter_sendtime', $next_schedule, $post_ID);
        return;
    }

    if ($post->post_status !== 'publish') {
        return;
    }

    $is_enabled = get_field('send_campaign', $post_ID);

    if (!$is_enabled) {
        return;
    }

    $sendtime = get_field('newsletter_sendtime', $post_ID, false);

    if (empty($sendtime)) {
        return;
    }

    $date = new DateTime($sendtime, wp_timezone());
    $sendtime = $date->getTimestamp();

    wp_schedule_single_event($sendtime, 'post_campaigns_send_newsletter', array($post_ID));
}

add_action('acf/save_post', 'post_campaigns_update_newsletter_campaign_id', 10, 1);
function post_campaigns_update_newsletter_campaign_id($post_ID)
{
    $post = get_post($post_ID);
    if (!$post) {
        return;
    }

    $newsletter_campaign_id = get_field('newsletter_campaign_id', $post_ID);
    if (!$newsletter_campaign_id) {
        update_field('newsletter_campaign_id', '', $post_ID);
        return;
    }
}

/**
 * Sync test lists with MailWizz when options page is saved.
 *
 * Uses a separate WP option (post_campaigns_known_list_ids) to track
 * which list IDs exist, so we can detect deletions reliably.
 */
add_action('acf/save_post', 'post_campaigns_sync_test_lists', 20, 1);
function post_campaigns_sync_test_lists($post_id)
{
    // Only run on options page saves
    if ($post_id !== 'options') {
        return;
    }

    // Get previously known list IDs from our own tracking option
    $previous_ids = get_option('post_campaigns_known_list_ids', array());
    if (!is_array($previous_ids)) {
        $previous_ids = array();
    }

    // Get current test lists after save
    $test_lists = get_field('test_lists', 'option');
    $current_ids = array();
    $errors = array();
    $lists_updated = false;

    if (is_array($test_lists)) {
        foreach ($test_lists as $index => $list) {
            $list_name = $list['list_info']['list_name'] ?? '';
            $list_id = $list['list_info']['list_id'] ?? '';
            $subscribers = $list['mail_list'] ?? array();

            // Create new list if no list_id exists
            if (empty($list_id) && !empty($list_name)) {
                $result = post_campaigns_create_mailwizz_list($list_name);

                if (is_wp_error($result)) {
                    $errors[] = sprintf(__('Failed to create list "%s": %s', 'post-campaigns'), $list_name, $result->get_error_message());
                    continue;
                }

                $list_id = $result['list_uid'] ?? '';

                if (!empty($list_id)) {
                    // Update the ACF field with the new list_id
                    $test_lists[$index]['list_info']['list_id'] = $list_id;
                    $lists_updated = true;
                }
            }

            if (!empty($list_id)) {
                $current_ids[] = $list_id;

                // Sync subscribers for this list
                $sync_result = post_campaigns_sync_list_subscribers($list_id, $subscribers);
                if (is_wp_error($sync_result)) {
                    $errors[] = sprintf(__('Failed to sync subscribers for "%s": %s', 'post-campaigns'), $list_name, $sync_result->get_error_message());
                }
            }
        }

        // Save updated test_lists with new list_ids
        if ($lists_updated) {
            update_field('test_lists', $test_lists, 'option');
        }
    }

    // Find and delete removed lists
    $deleted_ids = array_diff($previous_ids, $current_ids);
    foreach ($deleted_ids as $deleted_id) {
        $delete_result = post_campaigns_delete_mailwizz_list($deleted_id);
        if (is_wp_error($delete_result)) {
            $errors[] = sprintf(__('Failed to delete list %s: %s', 'post-campaigns'), $deleted_id, $delete_result->get_error_message());
        }
    }

    // Update our tracking option with the current list IDs
    update_option('post_campaigns_known_list_ids', $current_ids);

    // Store errors for display
    if (!empty($errors)) {
        set_transient('post_campaigns_sync_errors_' . get_current_user_id(), $errors, 60);
    }
}

/**
 * Sync subscribers for a list - delete all existing, then add new ones.
 *
 * @param string $list_id The MailWizz list UID.
 * @param array $subscribers Array of subscriber data from ACF repeater.
 * @return bool|WP_Error True on success or WP_Error on failure.
 */
function post_campaigns_sync_list_subscribers($list_id, $subscribers)
{
    // Get existing subscribers
    $existing = post_campaigns_get_mailwizz_subscribers($list_id);

    if (is_wp_error($existing)) {
        return $existing;
    }

    // Delete all existing subscribers
    foreach ($existing as $subscriber) {
        if (!empty($subscriber['subscriber_uid'])) {
            post_campaigns_delete_mailwizz_subscriber($list_id, $subscriber['subscriber_uid']);
        }
    }

    // Add new subscribers from ACF data
    if (is_array($subscribers)) {
        foreach ($subscribers as $subscriber) {
            $email = $subscriber['subscriber_mail'] ?? '';
            if (!empty($email) && is_email($email)) {
                $result = post_campaigns_add_mailwizz_subscriber($list_id, $email);
                if (is_wp_error($result)) {
                    error_log('Post Campaigns: Failed to add subscriber ' . $email . ' to list ' . $list_id);
                }
            }
        }
    }

    return true;
}
