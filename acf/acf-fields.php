<?php

function post_campaigns_allowed()
{
	return apply_filters('post_campaigns_allowed', current_user_can('manage_options'));
}

add_action('acf/include_fields', function () {
	if (! function_exists('acf_add_local_field_group')) {
		return;
	}

	if (!post_campaigns_allowed()) {
		return;
	}

	acf_add_local_field_group(array(
		'key' => 'group_68d0f7819d4dc',
		'title' => __('Newsletter', 'post-campaigns'),
		'fields' => array(
			array(
				'key' => 'field_68d0f78166ce7',
				'label' => __('Send newsletter', 'post-campaigns'),
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
				'instructions' => __('This is the time the newsletter will be created. Emails will be sent out shortly after.', 'post-campaigns'),
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
				'label' => __('Newsletter ID', 'post-campaigns'),
				'name' => 'newsletter_campaign_id',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => __('The MailWizz newsletter ID for this post.', 'post-campaigns'),
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
	));
});

/**
 * Register the Kampagne options page.
 */
add_action('acf/init', function () {
	if (! function_exists('acf_add_options_page')) {
		return;
	}

	if (!post_campaigns_allowed()) {
		return;
	}

	acf_add_options_page(array(
		'page_title' => __('Settings', 'post-campaigns'),
		'menu_slug' => 'kampagne',
		'parent_slug' => 'edit.php?post_type=press_release',
		'redirect' => false,
		'menu_icon' => array(
			'type' => 'dashicons',
			'value' => 'dashicons-admin-generic',
		),
		'icon_url' => 'dashicons-admin-generic',
	));
});

/**
 * Register Test Lister field group for the Kampagne options page.
 */
add_action('acf/include_fields', function () {
	if (! function_exists('acf_add_local_field_group')) {
		return;
	}

	if (!post_campaigns_allowed()) {
		return;
	}

	acf_add_local_field_group(array(
		'key' => 'group_69a168edafa0d',
		'title' => __('Test Lists', 'post-campaigns'),
		'fields' => array(
			array(
				'key' => 'field_69a168edf0ce0',
				'label' => __('Test Lists', 'post-campaigns'),
				'name' => 'test_lists',
				'aria-label' => '',
				'type' => 'repeater',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'layout' => 'block',
				'pagination' => 0,
				'min' => 0,
				'max' => 0,
				'collapsed' => '',
				'button_label' => __('Add List', 'post-campaigns'),
				'rows_per_page' => 20,
				'sub_fields' => array(
					array(
						'key' => 'field_69a169caa8519',
						'label' => __('List Info', 'post-campaigns'),
						'name' => 'list_info',
						'aria-label' => '',
						'type' => 'group',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'layout' => 'block',
						'sub_fields' => array(
							array(
								'key' => 'field_69a16923f0ce2',
								'label' => __('List Name', 'post-campaigns'),
								'name' => 'list_name',
								'aria-label' => '',
								'type' => 'text',
								'instructions' => '',
								'required' => 0,
								'conditional_logic' => 0,
								'wrapper' => array(
									'width' => '49',
									'class' => '',
									'id' => '',
								),
								'default_value' => '',
								'maxlength' => '',
								'allow_in_bindings' => 0,
								'placeholder' => '',
								'prepend' => '',
								'append' => '',
							),
							array(
								'key' => 'field_69a16934f0ce4',
								'label' => __('List ID', 'post-campaigns'),
								'name' => 'list_id',
								'aria-label' => '',
								'type' => 'text',
								'instructions' => '',
								'required' => 0,
								'conditional_logic' => 0,
								'wrapper' => array(
									'width' => '49',
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
						),
						'parent_repeater' => 'field_69a168edf0ce0',
					),
					array(
						'key' => 'field_69a16901f0ce1',
						'label' => __('Email List', 'post-campaigns'),
						'name' => 'mail_list',
						'aria-label' => '',
						'type' => 'repeater',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '100',
							'class' => '',
							'id' => '',
						),
						'layout' => 'table',
						'min' => 0,
						'max' => 0,
						'collapsed' => '',
						'button_label' => __('Add Email', 'post-campaigns'),
						'rows_per_page' => 20,
						'sub_fields' => array(
							array(
								'key' => 'field_69a16948f0ce5',
								'label' => __('Email', 'post-campaigns'),
								'name' => 'subscriber_mail',
								'aria-label' => '',
								'type' => 'text',
								'instructions' => '',
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
								'parent_repeater' => 'field_69a16901f0ce1',
							),
						),
						'parent_repeater' => 'field_69a168edf0ce0',
					),
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'options_page',
					'operator' => '==',
					'value' => 'kampagne',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
		'display_title' => '',
	));
});

/**
 * Prevent campaign ID from being overwritten with empty value.
 * This protects against stale form data in Gutenberg.
 */
add_filter('acf/update_value/key=field_68d397b830e54', function ($value, $post_id, $field) {
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
add_filter('acf/load_field/key=field_send_status_display', function ($field) {
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
		$field['message'] .= '<br><a href="' . esc_url($delete_url) . '" class="button button-small button-link-delete" style="margin-top: 8px;" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this newsletter ID? This will allow creating a new newsletter for this post.', 'post-campaigns')) . '\');">' . esc_html__('Delete newsletter', 'post-campaigns') . '</a>';
	} elseif ($next_scheduled) {
		$scheduled_time = wp_date('Y-m-d H:i:s', $next_scheduled);
		$cancel_url = add_query_arg(array(
			'post_campaigns_cancel_sending' => 1,
			'post' => $post_id,
			'_wpnonce' => wp_create_nonce('post_campaigns_cancel_sending_' . $post_id),
		), admin_url('post.php?post=' . $post_id . '&action=edit'));

		$field['message'] = '<span style="color: #f0b849; font-weight: 600;">' . esc_html__('Pending sending', 'post-campaigns') . '</span>';
		$field['message'] .= '<br><small>' . sprintf(esc_html__('Scheduled for: %s', 'post-campaigns'), $scheduled_time) . '</small>';
		$field['message'] .= '<br><a href="' . esc_url($cancel_url) . '" class="button button-small" style="margin-top: 8px;" onclick="return confirm(\'' . esc_js(__('Are you sure you want to cancel this scheduled newsletter?', 'post-campaigns')) . '\');">' . esc_html__('Cancel sending', 'post-campaigns') . '</a>';
	} else {
		$field['message'] = '<span style="color: #999;">' . esc_html__('Not scheduled', 'post-campaigns') . '</span>';
	}

	return $field;
});

/**
 * Render test mail dropdown + button after the send status field.
 * Uses acf/render_field to output HTML directly, avoiding ACF message field sanitization.
 */
add_action('acf/render_field/key=field_send_status_display', function ($field) {
	$post_id = get_the_ID();
	if (!$post_id || !in_array(get_post_status($post_id), array('publish', 'draft'))) {
		return;
	}

	$test_lists = get_field('test_lists', 'option');
	$has_custom_lists = is_array($test_lists) && !empty($test_lists);
	$has_fallback = defined('MAILWIZZ_TEST_LIST_ID') && !empty(MAILWIZZ_TEST_LIST_ID);

	if (!$has_custom_lists && !$has_fallback) {
		return;
	}

	// Build dropdown options
	$options = array();

	if ($has_custom_lists) {
		foreach ($test_lists as $list) {
			$list_name = $list['list_info']['list_name'] ?? __('Unnamed List', 'post-campaigns');
			$list_id = $list['list_info']['list_id'] ?? '';
			if (!empty($list_id)) {
				$options[] = array('id' => $list_id, 'name' => $list_name);
			}
		}
	}

	if (empty($options) && $has_fallback) {
		$options[] = array('id' => MAILWIZZ_TEST_LIST_ID, 'name' => __('Default Test List', 'post-campaigns'));
	}

	if (empty($options)) {
		return;
	}

	$base_url = add_query_arg(array(
		'post_campaigns_send_test_mail' => 1,
		'post' => $post_id,
		'_wpnonce' => wp_create_nonce('post_campaigns_send_test_mail_' . $post_id),
	), admin_url('post.php?post=' . $post_id . '&action=edit'));

	echo '<hr style="margin: 12px 0;">';
	echo '<label style="font-weight: 500; margin-bottom: 6px; display: block;">' . esc_html__('Test Mail', 'post-campaigns') . '</label>';
	echo '<select id="post_campaigns_test_list_select" style="width: 100%; margin-bottom: 8px;">';
	foreach ($options as $opt) {
		echo '<option value="' . esc_attr($opt['id']) . '">' . esc_html($opt['name']) . '</option>';
	}
	echo '</select>';
	echo '<a href="' . esc_url($base_url) . '" id="post_campaigns_test_mail_btn" class="button button-small" style="display: block; text-align: center;" onclick="return confirm(\'' . esc_js(__('Send a test newsletter to the selected list?', 'post-campaigns')) . '\');">' . esc_html__('Send test mail', 'post-campaigns') . '</a>';

	echo '<script>
	(function() {
		var select = document.getElementById("post_campaigns_test_list_select");
		var btn = document.getElementById("post_campaigns_test_mail_btn");
		if (!select || !btn) return;
		var baseUrl = "' . esc_url_raw($base_url) . '";
		function updateUrl() {
			btn.href = baseUrl + "&test_list_id=" + encodeURIComponent(select.value);
		}
		select.addEventListener("change", updateUrl);
		updateUrl();
	})();
	</script>';
});
