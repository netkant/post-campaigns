<?php

/**
 * Plugin Name: Post Campaigns
 * Description: MailWizz Integration for WP. Send MailWizz campaigns via MailWizz API
 * Version: 1.0.0
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

    $campaign = array(
        'campaign' => array(
            'name'       => 'Nyhedsbrev - ' . $post_title,
            'type'       => 'regular',
            'from_name'  => MAILWIZZ_FROM_NAME,
            'from_email' => MAILWIZZ_FROM_EMAIL,
            'subject'    => $post_title,
            'reply_to'   => MAILWIZZ_REPLY_TO,
            'send_at'    => wp_date('Y-m-d H:i:s'),
            'list_uid'   => MAILWIZZ_LIST_ID,
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
 * Handles the sending of the newsletter.
 */
function post_campaigns_send_newsletter_handler($post_ID)
{
    $post = get_post($post_ID);
    if (!$post) {
        return;
    }

    ob_start();
    $template = apply_filters('post_campaigns_template', plugin_dir_path(__FILE__) . 'templates/default-mail-template.php');

    load_template($template, true, array('post' => $post));
    $post_content = ob_get_clean();

    $response = post_campaigns_create_campaign($post->post_title, $post_content, $post_ID);
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
 * Add a cron schedule for the newsletter campaign on save_post.
 */
add_action('acf/save_post', 'post_campaigns_add_newsletter_cron_schedule', 10, 1);
function post_campaigns_add_newsletter_cron_schedule($post_ID)
{
    $post = get_post($post_ID);


    if ($post->post_type !== 'post') {
        return;
    }

    $next_schedule = wp_next_scheduled('post_campaigns_send_newsletter', array($post_ID));

    if ($post->post_status !== 'publish') {
        if ($next_schedule) {
            wp_unschedule_event($next_schedule, 'post_campaigns_send_newsletter', array($post_ID));
        }
        return;
    }

    $is_enabled = get_field('send_campaign', $post_ID);

    if (!$is_enabled && $next_schedule) {
        wp_unschedule_event($next_schedule, 'post_campaigns_send_newsletter', array($post_ID));
        return;
    }

    if (!$is_enabled) {
        return;
    }

    $sendtime = get_field('newsletter_sendtime', $post_ID, false);

    if (empty($sendtime) && $next_schedule) {
        wp_unschedule_event($next_schedule, 'post_campaigns_send_newsletter', array($post_ID));
        return;
    }

    if (empty($sendtime)) {
        return;
    }

    $date = new DateTime($sendtime, wp_timezone());
    $sendtime = $date->getTimestamp();

    if ($next_schedule !== $sendtime) {
        if ($next_schedule) {
            wp_unschedule_event($next_schedule, 'post_campaigns_send_newsletter', array($post_ID));
        }
        wp_schedule_single_event($sendtime, 'post_campaigns_send_newsletter', array($post_ID));
    }
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
