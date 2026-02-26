<?php

function post_campaigns_allowed() {
	return apply_filters('post_campaigns_allowed', current_user_can('manage_options'));
}

add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	if (!post_campaigns_allowed()) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_68d0f7819d4dc',
	'title' => __('Newsletter', 'post-campaigns'),
	'fields' => array(
		array(
			'key' => 'field_68d0f78166ce7',
			'label' => __('Send campaign', 'post-campaigns'),
			'name' => 'send_campaign',
			'aria-label' => '',
			'type' => 'true_false',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'message' => '',
			'default_value' => 0,
			'allow_in_bindings' => 0,
			'ui_on_text' => '',
			'ui_off_text' => '',
			'ui' => 1,
		),
		array(
			'key' => 'field_68d12de00ff3e',
			'label' => __('Send time', 'post-campaigns'),
			'name' => 'newsletter_sendtime',
			'aria-label' => '',
			'type' => 'date_time_picker',
			'instructions' => __('This is the time the campaign will be created. Mails will be sent out shortly after.', 'post-campaigns'),
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'display_format' => 'Y-m-d H:i:s',
			'return_format' => 'U',
			'first_day' => 1,
			'default_to_current_date' => 0,
			'allow_in_bindings' => 0,
		),
		array(
			'key' => 'field_68d397b830e54',
			'label' => __('Campaign ID', 'post-campaigns'),
			'name' => 'newsletter_campaign_id',
			'aria-label' => '',
			'type' => 'text',
			'instructions' => __('The MailWizz campaign ID for this post.', 'post-campaigns'),
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '',
			'maxlength' => '',
			'allow_in_bindings' => 0,
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
			'readonly' => 1,
		),
		array(
			'key' => 'field_send_status_display',
			'label' => __('Send status', 'post-campaigns'),
			'name' => 'send_status_display',
			'type' => 'message',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'message' => '',
			'new_lines' => 'wpautop',
			'esc_html' => 0,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'press_release',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'side',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );

/**
 * Prevent campaign ID from being overwritten with empty value.
 * This protects against stale form data in Gutenberg.
 */
add_filter('acf/update_value/key=field_68d397b830e54', function($value, $post_id, $field) {
	// If new value is empty, check if there's an existing value to preserve
	if (empty($value)) {
		$existing = get_post_meta($post_id, 'newsletter_campaign_id', true);
		if (!empty($existing)) {
			return $existing;
		}
	}
	return $value;
}, 10, 3);

/**
 * Dynamically populate the send status field based on cron and campaign state.
 */
add_filter('acf/load_field/key=field_send_status_display', function($field) {
	$post_id = get_the_ID();
	if (!$post_id) {
		$field['message'] = '';
		return $field;
	}

	$campaign_id = get_field('newsletter_campaign_id', $post_id);
	$next_scheduled = wp_next_scheduled('post_campaigns_send_newsletter', array($post_id));

	if ($campaign_id) {
		$delete_url = add_query_arg(array(
			'post_campaigns_delete_campaign' => 1,
			'post' => $post_id,
			'_wpnonce' => wp_create_nonce('post_campaigns_delete_campaign_' . $post_id),
		), admin_url('post.php?post=' . $post_id . '&action=edit'));

		$field['message'] = '<span style="color: #46b450; font-weight: 600;">' . esc_html__('Sent', 'post-campaigns') . '</span>';
		$field['message'] .= '<br><a href="' . esc_url($delete_url) . '" class="button button-small button-link-delete" style="margin-top: 8px;" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this campaign ID? This will allow creating a new campaign for this post.', 'post-campaigns')) . '\');">' . esc_html__('Delete campaign', 'post-campaigns') . '</a>';
	} elseif ($next_scheduled) {
		$scheduled_time = wp_date('Y-m-d H:i:s', $next_scheduled);
		$cancel_url = add_query_arg(array(
			'post_campaigns_cancel_sending' => 1,
			'post' => $post_id,
			'_wpnonce' => wp_create_nonce('post_campaigns_cancel_sending_' . $post_id),
		), admin_url('post.php?post=' . $post_id . '&action=edit'));

		$field['message'] = '<span style="color: #f0b849; font-weight: 600;">' . esc_html__('Pending sending', 'post-campaigns') . '</span>';
		$field['message'] .= '<br><small>' . sprintf(esc_html__('Scheduled for: %s', 'post-campaigns'), $scheduled_time) . '</small>';
		$field['message'] .= '<br><a href="' . esc_url($cancel_url) . '" class="button button-small" style="margin-top: 8px;" onclick="return confirm(\'' . esc_js(__('Are you sure you want to cancel this scheduled campaign?', 'post-campaigns')) . '\');">' . esc_html__('Cancel sending', 'post-campaigns') . '</a>';
	} else {
		$field['message'] = '<span style="color: #999;">' . esc_html__('Not scheduled', 'post-campaigns') . '</span>';
	}

	// Add "Send test mail" button if test list is configured and post is published
	if (defined('MAILWIZZ_TEST_LIST_ID') && get_post_status($post_id) === 'publish') {
		$test_url = add_query_arg(array(
			'post_campaigns_send_test_mail' => 1,
			'post' => $post_id,
			'_wpnonce' => wp_create_nonce('post_campaigns_send_test_mail_' . $post_id),
		), admin_url('post.php?post=' . $post_id . '&action=edit'));

		$field['message'] .= '<br><hr><br><label style="font-weight: 500; margin-bottom: 3px;">' . esc_html__('Test Mail', 'post-campaigns') . '</label><br><a href="' . esc_url($test_url) . '" class="button button-small" style="margin-top: 8px;" onclick="return confirm(\'' . esc_js(__('Send a test campaign to the test list?', 'post-campaigns')) . '\');">' . esc_html__('Send test mail', 'post-campaigns') . '</a>';
	}

	return $field;
});

