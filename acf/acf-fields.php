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
			'instructions' => __('This is the link to the campaign in the system. Delete this field and save if you want to create a new campaign in the same post.', 'post-campaigns'),
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
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'post',
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

