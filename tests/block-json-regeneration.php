<?php

function assert_test( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

if ( ! defined( 'TIMBER_BLOCKS_AUTO_GENERATE' ) ) {
	define( 'TIMBER_BLOCKS_AUTO_GENERATE', true );
}

require dirname( __DIR__ ) . '/timber-acf-wp-blocks.php';

$headers = array(
	'title'           => 'Validation Block',
	'description'     => 'Validation description.',
	'category'        => 'layout',
	'supports_anchor' => 'true',
);

$existing = array(
	'_generatedFromTwig' => true,
	'icon'       => 'admin-site',
	'attributes' => array(
		'headline' => array(
			'type' => 'string',
		),
	),
	'example'    => array(
		'attributes' => array(
			'mode' => 'preview',
			'data' => array(
				'legacy' => true,
			),
		),
	),
	'supports'   => array(
		'align'   => array( 'wide' ),
		'mode'    => false,
		'spacing' => true,
	),
	'acf'        => array(
		'mode'                => 'edit',
		'hideFieldsInSidebar' => true,
		'renderTemplate'      => 'custom-render.php',
		'customFlag'          => 'kept',
	),
);

$generated = Timber_Acf_Wp_Blocks::generate_block_json_data( $headers, 'validation-block', $existing );

$headers_with_example = array_merge(
	$headers,
	array(
		'example' => '{"headline":"Preview headline"}',
	)
);

$generated_with_example = Timber_Acf_Wp_Blocks::generate_block_json_data( $headers_with_example, 'validation-block', $existing );

assert_test( 'acf/validation-block' === $generated['name'], 'Generated block name is incorrect.' );
assert_test( ! isset( $generated['icon'] ), 'Managed top-level keys removed from Twig should not be preserved.' );
assert_test( ! isset( $generated['example'] ), 'Managed example data removed from Twig should not be preserved.' );
assert_test( isset( $generated['attributes']['headline'] ), 'Unsupported top-level keys should be preserved.' );
assert_test( true === $generated['supports']['anchor'], 'Header-generated supports keys should be present.' );
assert_test( ! isset( $generated['supports']['align'] ), 'Managed supports keys removed from Twig should not be preserved.' );
assert_test( ! isset( $generated['supports']['mode'] ), 'Managed supports keys should not override regenerated values.' );
assert_test( true === $generated['supports']['spacing'], 'Unsupported supports keys should be preserved.' );
assert_test( 'preview' === $generated['acf']['mode'], 'Managed ACF keys should remain authoritative.' );
assert_test( ! isset( $generated['acf']['hideFieldsInSidebar'] ), 'Managed ACF keys removed from Twig should not be preserved.' );
assert_test( 'custom-render.php' === $generated['acf']['renderTemplate'], 'Unsupported ACF keys should be preserved.' );
assert_test( 'kept' === $generated['acf']['customFlag'], 'Custom ACF flags should be preserved.' );
assert_test( isset( $generated_with_example['example'] ), 'Generated block.json should include example data when the Twig Example header is set.' );
assert_test( 'preview' === $generated_with_example['example']['attributes']['mode'], 'Generated example data should remain in preview mode.' );
assert_test( 'Preview headline' === $generated_with_example['example']['attributes']['data']['headline'], 'Generated example data should preserve the Twig Example payload.' );
assert_test( true === $generated_with_example['example']['attributes']['data']['is_example'], 'Generated block.json example data should include the is_example flag for render parity.' );

$twig_fixture = tempnam( sys_get_temp_dir(), 'timber-twig-' );
$json_fixture = tempnam( sys_get_temp_dir(), 'timber-json-' );

$twig_fixture_with_example = tempnam( sys_get_temp_dir(), 'timber-twig-example-' );
$json_fixture_with_example = tempnam( sys_get_temp_dir(), 'timber-json-example-' );

assert_test( false !== $twig_fixture, 'Unable to create temporary Twig fixture.' );
assert_test( false !== $json_fixture, 'Unable to create temporary JSON fixture.' );
assert_test( false !== $twig_fixture_with_example, 'Unable to create temporary Twig example fixture.' );
assert_test( false !== $json_fixture_with_example, 'Unable to create temporary JSON example fixture.' );

file_put_contents( $twig_fixture, "/*\nTitle: Validation Block\nCategory: layout\n*/\n" );
file_put_contents( $json_fixture, wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
file_put_contents( $twig_fixture_with_example, "/*\nTitle: Validation Block\nCategory: layout\nExample: { \"headline\": \"Preview headline\" }\n*/\n" );
file_put_contents( $json_fixture_with_example, wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

touch( $json_fixture, time() - 10 );
touch( $twig_fixture, time() );
touch( $json_fixture_with_example, time() - 10 );
touch( $twig_fixture_with_example, time() );

$write_result = Timber_Acf_Wp_Blocks::maybe_write_block_json( $json_fixture, $twig_fixture, $generated );
$write_result_with_example = Timber_Acf_Wp_Blocks::maybe_write_block_json( $json_fixture_with_example, $twig_fixture_with_example, $generated_with_example );

assert_test( true === $write_result, 'Regenerated block.json fixture should be written.' );
assert_test( true === $write_result_with_example, 'Regenerated block.json example fixture should be written.' );

$written_raw = file_get_contents( $json_fixture );
$written     = json_decode( $written_raw, true );

$written_raw_with_example = file_get_contents( $json_fixture_with_example );
$written_with_example     = json_decode( $written_raw_with_example, true );

assert_test( false !== $written_raw, 'Written block.json fixture should be readable.' );
assert_test( 1 === preg_match( '/^\{\n\s+"_generatedFromTwig": true,/u', $written_raw ), 'Generated flag should be written at the top of block.json.' );
assert_test( is_array( $written ), 'Written block.json fixture should decode to an array.' );
assert_test( ! isset( $written['icon'] ), 'Stale managed top-level keys should not be reintroduced during write.' );
assert_test( ! isset( $written['example'] ), 'Stale managed example data should not be reintroduced during write.' );
assert_test( isset( $written['attributes']['headline'] ), 'Unsupported top-level keys should survive regeneration writes.' );
assert_test( true === $written['supports']['anchor'], 'Header-generated supports keys should survive regeneration writes.' );
assert_test( ! isset( $written['supports']['align'] ), 'Stale managed supports keys should be removed during write.' );
assert_test( true === $written['supports']['spacing'], 'Unsupported supports keys should survive regeneration writes.' );
assert_test( 'preview' === $written['acf']['mode'], 'Managed ACF keys should survive regeneration writes.' );
assert_test( ! isset( $written['acf']['hideFieldsInSidebar'] ), 'Stale managed ACF keys should be removed during write.' );
assert_test( 'custom-render.php' === $written['acf']['renderTemplate'], 'Unsupported ACF keys should survive regeneration writes.' );
assert_test( true === $written['_generatedFromTwig'], 'Generated flag should remain true after regeneration writes.' );
assert_test( false !== $written_raw_with_example, 'Written block.json example fixture should be readable.' );
assert_test( is_array( $written_with_example ), 'Written block.json example fixture should decode to an array.' );
assert_test( 'Preview headline' === $written_with_example['example']['attributes']['data']['headline'], 'Written block.json example fixture should preserve the Twig Example payload.' );
assert_test( true === $written_with_example['example']['attributes']['data']['is_example'], 'Written block.json example fixture should include the is_example flag for render parity.' );

unlink( $twig_fixture );
unlink( $json_fixture );
unlink( $twig_fixture_with_example );
unlink( $json_fixture_with_example );

echo "block.json regeneration validation passed.\n";
