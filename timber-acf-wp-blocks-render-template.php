<?php

defined('ABSPATH') || exit;

if (! class_exists('Timber_Acf_Wp_Blocks')) {
	return;
}

Timber_Acf_Wp_Blocks::timber_blocks_callback(
	$block,
	isset($content) ? $content : '',
	isset($is_preview) ? $is_preview : false,
	isset($post_id) ? $post_id : 0,
	isset($wp_block) ? $wp_block : null,
	isset($context) ? $context : array()
);
