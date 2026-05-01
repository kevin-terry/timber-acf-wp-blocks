<?php

namespace Timber {
	class Timber {
		public static $last_render = null;

		public static function context() {
			return array();
		}

		public static function render( $paths, $context ) {
			self::$last_render = array(
				'paths'   => $paths,
				'context' => $context,
			);
		}
	}
}

namespace {
	function assert_test( $condition, $message ) {
		if ( ! $condition ) {
			fwrite( STDERR, $message . PHP_EOL );
			exit( 1 );
		}
	}

	if ( ! class_exists( 'Timber' ) ) {
		class_alias( 'Timber\\Timber', 'Timber' );
	}

	if ( ! function_exists( 'add_action' ) ) {
		function add_action() {}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter() {}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook_name, $value ) {
			return $value;
		}
	}

	if ( ! function_exists( 'get_fields' ) ) {
		function get_fields() {
			return array();
		}
	}

	if ( ! function_exists( 'locate_template' ) ) {
		function locate_template() {
			return false;
		}
	}

	require dirname( __DIR__ ) . '/timber-acf-wp-blocks.php';

	$runtime_settings = new \ReflectionProperty( 'Timber_Acf_Wp_Blocks', 'block_runtime_settings' );
	$runtime_settings->setAccessible( true );
	$runtime_settings->setValue(
		null,
		array(
			'acf/testimonial' => array(
				'default_align'          => 'full',
				'inline_editable_fields' => array(),
			),
			'acf/no-align'    => array(
				'default_align'          => null,
				'inline_editable_fields' => array(),
			),
		)
	);

	\Timber\Timber::$last_render = null;
	Timber_Acf_Wp_Blocks::timber_blocks_callback(
		array(
			'name'  => 'acf/testimonial',
			'id'    => 'block_default_align',
			'title' => 'Testimonial',
		)
	);

	assert_test(
		'testimonial alignfull' === \Timber\Timber::$last_render['context']['classes'],
		'Default Align header should add an align class when no saved block align exists.'
	);

	\Timber\Timber::$last_render = null;
	Timber_Acf_Wp_Blocks::timber_blocks_callback(
		array(
			'name'  => 'acf/testimonial',
			'id'    => 'block_saved_align',
			'title' => 'Testimonial',
			'align' => 'wide',
		)
	);

	assert_test(
		'testimonial alignwide' === \Timber\Timber::$last_render['context']['classes'],
		'Saved block alignment should take precedence over the Twig header default.'
	);

	\Timber\Timber::$last_render = null;
	Timber_Acf_Wp_Blocks::timber_blocks_callback(
		array(
			'name'  => 'acf/testimonial',
			'id'    => 'block_none_align',
			'title' => 'Testimonial',
			'align' => 'none',
		)
	);

	assert_test(
		'testimonial' === \Timber\Timber::$last_render['context']['classes'],
		'A saved align value of none should suppress alignment classes.'
	);

	\Timber\Timber::$last_render = null;
	Timber_Acf_Wp_Blocks::timber_blocks_callback(
		array(
			'name'  => 'acf/no-align',
			'id'    => 'block_no_default_align',
			'title' => 'No Align',
		)
	);

	assert_test(
		'no-align' === \Timber\Timber::$last_render['context']['classes'],
		'Blocks without a default Align header should not gain an alignment class.'
	);

	echo "block align default validation passed.\n";
}
