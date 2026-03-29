<?php
/**
 * Plugin bootstrap and block registration helpers for Timber ACF blocks.
 *
 * @package Timber_Acf_Wp_Blocks
 */

use Timber\Timber;

/**
 * Check if class exists before redefining it
 */
if ( ! class_exists( 'Timber_Acf_Wp_Blocks' ) ) {
	/**
	 * Main Timber_Acf_Wp_Block Class
	 */
	class Timber_Acf_Wp_Blocks {


		/**
		 * Per-block runtime settings derived from Twig headers.
		 *
		 * @var array
		 */
		private static $block_runtime_settings = array();

		/**
		 * Tracks blocks using flat file structure for admin notice.
		 *
		 * @var array
		 */
		private static $flat_structure_blocks = array();

		/**
		 * Cache resolved block directories for the current request.
		 *
		 * @var array|null
		 */
		private static $cached_block_directories = null;

		/**
		 * Constructor
		 */
		public function __construct() {
			if (
				is_callable( 'add_action' )
				&& is_callable( 'acf_register_block_type' )
				&& class_exists( 'Timber' )
			) {
				add_filter(
					'block_type_metadata',
					array( __CLASS__, 'maybe_swap_to_render_template_for_auto_inline_editing' )
				);
				add_action( 'acf/init', array( __CLASS__, 'timber_block_init' ), 10, 0 );
				add_action( 'admin_init', array( __CLASS__, 'handle_flat_structure_notice_dismiss' ) );
				add_action( 'admin_notices', array( __CLASS__, 'show_flat_structure_notice' ) );
			} elseif ( is_callable( 'add_action' ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error"><p>Timber ACF WP Blocks requires Timber and ACF.';
						echo 'Check if the plugins or libraries are installed and activated.</p></div>';
					}
				);
			}
		}


		/**
		 * Create blocks based on templates found in Timber's "views/blocks" directory.
		 * Supports both flat file structure (legacy) and subfolder structure (modern).
		 */
		public static function timber_block_init() {
			// Get base directories (not including auto-discovered subfolders).
			$base_directories = apply_filters( 'timber/acf-gutenberg-blocks-templates', array( 'views/blocks' ) );

			// Track registered blocks to avoid duplicates.
			$registered_blocks = array();

			foreach ( $base_directories as $base_dir ) {
				$base_path = \locate_template( $base_dir );

				if ( ! $base_path || ! file_exists( $base_path ) ) {
					continue;
				}

				$directory_iterator = new DirectoryIterator( $base_path );

				foreach ( $directory_iterator as $item ) {
					if ( $item->isDot() ) {
						continue;
					}

					// Check for subfolder structure: views/blocks/my-block/my-block.twig.
					if ( $item->isDir() ) {
						$slug           = $item->getFilename();
						$subfolder_twig = $base_path . '/' . $slug . '/' . $slug . '.twig';

						if ( file_exists( $subfolder_twig ) ) {
							// Subfolder structure found.
							$structure = array(
								'type'          => 'subfolder',
								'directory'     => $base_dir . '/' . $slug,
								'absolute_path' => $base_path . '/' . $slug,
								'twig_file'     => $subfolder_twig,
								'block_json'    => $base_path . '/' . $slug . '/block.json',
							);

							if ( ! isset( $registered_blocks[ $slug ] ) ) {
								self::register_block_from_structure( $slug, $structure );
								$registered_blocks[ $slug ] = true;
							}
						}
						continue;
					}

					// Flat file structure: views/blocks/my-block.twig.
					if ( $item->isFile() ) {
						$file_parts = pathinfo( $item->getFilename() );

						if ( ! isset( $file_parts['extension'] ) || 'twig' !== $file_parts['extension'] ) {
							continue;
						}

						$slug = $file_parts['filename'];

						// Skip if already registered via subfolder.
						if ( isset( $registered_blocks[ $slug ] ) ) {
							continue;
						}

						$structure = array(
							'type'          => 'flat',
							'directory'     => $base_dir,
							'absolute_path' => $base_path,
							'twig_file'     => $base_path . '/' . $slug . '.twig',
							'block_json'    => null,
						);

						self::register_block_from_structure( $slug, $structure );
						$registered_blocks[ $slug ] = true;
					}
				}
			}
		}

		/**
		 * Register a block based on its structure type.
		 *
		 * @param string $slug      Block slug.
		 * @param array  $structure Structure information array.
		 */
		public static function register_block_from_structure( $slug, $structure ) {
			// Get header info from the Twig file.
			$file_headers = self::get_twig_file_headers( $structure['twig_file'] );
			self::store_block_runtime_settings( $slug, $file_headers );

			if ( empty( $file_headers['title'] ) || empty( $file_headers['category'] ) ) {
				return;
			}

			if ( 'subfolder' === $structure['type'] ) {
				$block_json_path = $structure['block_json'];
				$existing_data   = array();

				if ( file_exists( $block_json_path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Block metadata files live on the local filesystem.
					$existing_content = file_get_contents( $block_json_path );
					$existing_data    = json_decode( $existing_content, true ) ?? array();
				}

				if ( empty( $file_headers['example_image'] ) ) {
					$file_headers['example_image'] = self::auto_detect_example_image(
						$structure['absolute_path'],
						$structure['directory']
					);
				}

				$block_json_data = self::generate_block_json_data( $file_headers, $slug, $existing_data );

				self::maybe_write_block_json( $block_json_path, $structure['twig_file'], $block_json_data );

				if ( file_exists( $block_json_path ) ) {
					self::register_block_from_json( $block_json_path );
					return;
				}
			}

			if ( 'flat' === $structure['type'] ) {
				self::trigger_flat_structure_deprecation( $slug );
			}

			$data = self::build_legacy_block_data( $file_headers, $slug );
			$data = self::timber_block_default_data( $data );
			acf_register_block_type( $data );
		}

		/**
		 * Get standardized file headers from a Twig template.
		 *
		 * @param string $file_path Absolute path to Twig file.
		 * @return array Parsed headers.
		 */
		public static function get_twig_file_headers( $file_path ) {
			return get_file_data(
				$file_path,
				array(
					'title'                      => 'Title',
					'description'                => 'Description',
					'category'                   => 'Category',
					'icon'                       => 'Icon',
					'keywords'                   => 'Keywords',
					'mode'                       => 'Mode',
					'align'                      => 'Align',
					'post_types'                 => 'PostTypes',
					'supports_align'             => 'SupportsAlign',
					'supports_align_content'     => 'SupportsAlignContent',
					'supports_mode'              => 'SupportsMode',
					'supports_multiple'          => 'SupportsMultiple',
					'supports_anchor'            => 'SupportsAnchor',
					'enqueue_style'              => 'EnqueueStyle',
					'enqueue_script'             => 'EnqueueScript',
					'enqueue_assets'             => 'EnqueueAssets',
					'supports_custom_class_name' => 'SupportsCustomClassName',
					'supports_reusable'          => 'SupportsReusable',
					'supports_full_height'       => 'SupportsFullHeight',
					'supports_html'              => 'SupportsHtml',
					'supports_inserter'          => 'SupportsInserter',
					'supports_lock'              => 'SupportsLock',
					'example'                    => 'Example',
					'example_image'              => 'ExampleImage',
					'supports_jsx'               => 'SupportsJSX',
					'hide_sidebar_fields'        => 'HideSidebarFields',
					'auto_inline_editing'        => 'AutoInlineEditing',
					'inline_editable_fields'     => 'InlineEditableFields',
					'parent'                     => 'Parent',
					'ancestor'                   => 'Ancestor',
					'uses_context'               => 'UsesContext',
					'provides_context'           => 'ProvidesContext',
					'default_data'               => 'DefaultData',
				)
			);
		}

		/**
		 * Build legacy block data array for acf_register_block_type.
		 *
		 * @param array  $file_headers Parsed Twig headers.
		 * @param string $slug         Block slug.
		 * @return array Block registration data.
		 */
		public static function build_legacy_block_data( $file_headers, $slug ) {
			// Keywords exploding with quotes.
			$keywords = str_getcsv( $file_headers['keywords'], ' ', '"', '\\' );

			$data = array(
				'name'              => $slug,
				'title'             => $file_headers['title'],
				'description'       => $file_headers['description'],
				'category'          => $file_headers['category'],
				'icon'              => $file_headers['icon'],
				'keywords'          => $keywords,
				'mode'              => $file_headers['mode'],
				'align'             => $file_headers['align'],
				'api_version'       => 3,
				'acf_block_version' => 3,
				'render_callback'   => array( __CLASS__, 'timber_blocks_callback' ),
				'enqueue_assets'    => $file_headers['enqueue_assets'],
				'default_data'      => $file_headers['default_data'],
			);

			$data = array_filter( $data );

			if ( ! empty( $file_headers['post_types'] ) ) {
				$data['post_types'] = explode( ' ', $file_headers['post_types'] );
			}

			if ( ! empty( $file_headers['supports_align'] ) ) {
				$data['supports']['align'] =
					in_array( $file_headers['supports_align'], array( 'true', 'false' ), true ) ?
					filter_var( $file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN ) :
					explode( ' ', $file_headers['supports_align'] );
			}

			if ( ! empty( $file_headers['supports_align_content'] ) ) {
				$data['supports']['alignContent'] = ( 'true' === $file_headers['supports_align_content'] ) ?
					true : ( ( 'matrix' === $file_headers['supports_align_content'] ) ? 'matrix' : false );
			}

			if ( ! empty( $file_headers['supports_mode'] ) ) {
				$data['supports']['mode'] =
					( 'true' === $file_headers['supports_mode'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_multiple'] ) ) {
				$data['supports']['multiple'] =
					( 'true' === $file_headers['supports_multiple'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_anchor'] ) ) {
				$data['supports']['anchor'] =
					( 'true' === $file_headers['supports_anchor'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_custom_class_name'] ) ) {
				$data['supports']['customClassName'] =
					( 'true' === $file_headers['supports_custom_class_name'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_reusable'] ) ) {
				$data['supports']['reusable'] =
					( 'true' === $file_headers['supports_reusable'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_full_height'] ) ) {
				$data['supports']['full_height'] =
					( 'true' === $file_headers['supports_full_height'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_html'] ) ) {
				$data['supports']['html'] =
					( 'true' === $file_headers['supports_html'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_inserter'] ) ) {
				$data['supports']['inserter'] =
					( 'true' === $file_headers['supports_inserter'] ) ? true : false;
			}

			if ( ! empty( $file_headers['supports_lock'] ) ) {
				$data['supports']['lock'] =
					( 'true' === $file_headers['supports_lock'] ) ? true : false;
			}

			if ( ! empty( $file_headers['enqueue_style'] ) ) {
				if ( ! filter_var( $file_headers['enqueue_style'], FILTER_VALIDATE_URL ) ) {
					$data['enqueue_style'] =
						get_template_directory_uri() . '/' . $file_headers['enqueue_style'];
				} else {
					$data['enqueue_style'] = $file_headers['enqueue_style'];
				}
			}

			if ( ! empty( $file_headers['enqueue_script'] ) ) {
				if ( ! filter_var( $file_headers['enqueue_script'], FILTER_VALIDATE_URL ) ) {
					$data['enqueue_script'] =
						get_template_directory_uri() . '/' . $file_headers['enqueue_script'];
				} else {
					$data['enqueue_script'] = $file_headers['enqueue_script'];
				}
			}

			if ( ! empty( $file_headers['example_image'] ) ) {
				if ( ! filter_var( $file_headers['example_image'], FILTER_VALIDATE_URL ) ) {
					$data['example_image'] =
						get_template_directory_uri() . '/' . $file_headers['example_image'];
				} else {
					$data['example_image'] = $file_headers['example_image'];
				}
			}

			if ( ! empty( $file_headers['supports_jsx'] ) ) {
				$data['supports']['__experimental_jsx'] =
					( 'true' === $file_headers['supports_jsx'] ) ? true : false;
				$data['supports']['jsx']                =
					( 'true' === $file_headers['supports_jsx'] ) ? true : false;
			}

			if ( ! empty( $file_headers['example'] ) ) {
				$json                       = json_decode( $file_headers['example'], true );
				$example_data               = ( null !== $json ) ? $json : array();
				$example_data['is_example'] = true;
				$data['example']            = array(
					'attributes' => array(
						'mode' => 'preview',
						'data' => $example_data,
					),
				);
			}

			if ( ! empty( $file_headers['parent'] ) ) {
				$data['parent'] = str_getcsv( $file_headers['parent'], ' ', '"' );
			}

			if ( ! empty( $file_headers['ancestor'] ) ) {
				$data['ancestor'] = str_getcsv( $file_headers['ancestor'], ' ', '"' );
			}

			if ( ! empty( $file_headers['uses_context'] ) ) {
				$data['uses_context'] = explode( ' ', $file_headers['uses_context'] );
			}

			if ( ! empty( $file_headers['provides_context'] ) ) {
				$json = json_decode( $file_headers['provides_context'], true );
				if ( null !== $json ) {
					$data['provides_context'] = $json;
				}
			}

			return $data;
		}

		/**
		 * Callback to register blocks
		 *
		 * @param array    $block         stores all the data from ACF.
		 * @param string   $content       content passed to block.
		 * @param bool     $is_preview    checks if block is in preview mode.
		 * @param int      $post_id       Post ID.
		 * @param WP_Block $wp_block      The block instance (API v2).
		 * @param array    $block_context The block context (API v2).
		 */
		public static function timber_blocks_callback(
			$block,
			$content = '',
			$is_preview = false,
			$post_id = 0,
			$wp_block = null,
			$block_context = array()
		) {
			$slug = str_replace( 'acf/', '', $block['name'] );

			$is_example = false;

			if ( ! empty( $block['data']['is_example'] ) ) {
				$is_example = true;
			}

			$example_image = null;
			if ( ! empty( $block['example_image'] ) ) {
				$example_image = $block['example_image'];
			} elseif ( ! empty( $block['acf']['exampleImage'] ) ) {
				$example_image = $block['acf']['exampleImage'];
				if ( ! filter_var( $example_image, FILTER_VALIDATE_URL ) ) {
					$example_image = get_template_directory_uri() . '/' . $example_image;
				}
			}

			if ( $is_example && ! empty( $example_image ) ) {
				printf(
					'<img src="%1$s" alt="%2$s" style="width: 100%%; height: auto;" />',
					esc_url( $example_image ),
					esc_attr( $block['title'] )
				);
				return;
			}

			// Context compatibility.
			if ( method_exists( 'Timber', 'context' ) ) {
				$context = Timber::context();
			} else {
				$context = Timber::get_context();
			}

			$context['block']         = $block;
			$context['post_id']       = $post_id;
			$context['slug']          = $slug;
			$context['is_preview']    = $is_preview;
			$context['fields']        = $is_example ? $block['data'] : \get_fields();
			$context['fields']        = self::prepare_fields_for_auto_inline_editing(
				$context['fields'],
				$block,
				$is_preview
			);
			$context['inner_content'] = $content;
			$context['wp_block']      = $wp_block;
			$context['block_context'] = $block_context;
			$classes                  = array_merge(
				array( $slug ),
				isset( $block['className'] ) ? array( $block['className'] ) : array(),
				$is_preview ? array( 'is-preview' ) : array(),
				! empty( $context['block']['align'] ) ? array( 'align' . $context['block']['align'] ) : array()
			);

			$context['classes'] = implode( ' ', $classes );

			$context = apply_filters( 'timber/acf-gutenberg-blocks-data', $context );
			$context = apply_filters( 'timber/acf-gutenberg-blocks-data/' . $slug, $context );
			$context = apply_filters( 'timber/acf-gutenberg-blocks-data/' . $block['id'], $context );

			$paths = self::timber_acf_path_render( $slug, $is_preview, $is_example );

			Timber::render( $paths, $context );
		}

		/**
		 * Generates array with paths and slugs
		 *
		 * @param string $slug       File slug.
		 * @param bool   $is_preview Checks if preview.
		 * @param bool   $is_example Checks if example.
		 */
		public static function timber_acf_path_render( $slug, $is_preview, $is_example ) {
			$directories = self::timber_block_directory_getter();

			$ret = array();

			/**
			 * Filters the name of suffix for example file.
			 *
			 * @since 1.12
			 */
			$example_identifier = apply_filters( 'timber/acf-gutenberg-blocks-example-identifier', '-example' );

			/**
			 * Filters the name of suffix for preview file.
			 *
			 * @since 1.12
			 */
			$preview_identifier = apply_filters( 'timber/acf-gutenberg-blocks-preview-identifier', '-preview' );

			foreach ( $directories as $directory ) {
				if ( $is_example ) {
					$ret[] = $directory . "/{$slug}{$example_identifier}.twig";
				}
				if ( $is_preview ) {
					$ret[] = $directory . "/{$slug}{$preview_identifier}.twig";
				}
				$ret[] = $directory . "/{$slug}.twig";
			}

			return $ret;
		}

		/**
		 * Swap callback-rendered blocks to a shared render template when ACF auto inline editing is enabled.
		 *
		 * @param array $metadata Raw block metadata.
		 * @return array
		 */
		public static function maybe_swap_to_render_template_for_auto_inline_editing( $metadata ) {
			if ( ! is_array( $metadata ) || empty( $metadata['acf'] ) || ! is_array( $metadata['acf'] ) ) {
				return $metadata;
			}

			$internal_render_template = self::get_auto_inline_render_template_path();

			if ( empty( $metadata['acf']['autoInlineEditing'] ) ) {
				if (
					isset( $metadata['acf']['renderTemplate'] )
					&& $internal_render_template === $metadata['acf']['renderTemplate']
				) {
					unset( $metadata['acf']['renderTemplate'] );
					$metadata['acf']['renderCallback'] = 'Timber_Acf_Wp_Blocks::timber_blocks_callback';
				}

				return $metadata;
			}

			if (
				empty( $metadata['acf']['renderCallback'] )
				|| ! self::is_timber_blocks_render_callback( $metadata['acf']['renderCallback'] )
			) {
				return $metadata;
			}

			$metadata['acf']['renderTemplate'] = $internal_render_template;
			unset( $metadata['acf']['renderCallback'] );

			return $metadata;
		}

		/**
		 * Normalize placeholder values before Twig render so empty non-text fields don't leak into markup.
		 *
		 * @param mixed $fields     ACF field values.
		 * @param array $block      Current block data.
		 * @param bool  $is_preview Whether the block is rendering in preview.
		 * @return mixed
		 */
		public static function prepare_fields_for_auto_inline_editing( $fields, $block, $is_preview ) {
			if ( ! $is_preview || ! is_array( $fields ) ) {
				return $fields;
			}

			if ( empty( $block['auto_inline_editing'] ) && empty( $block['acf']['autoInlineEditing'] ) ) {
				return $fields;
			}

			$allowed_field_names = self::get_allowed_auto_inline_field_names( $block );

			return self::sanitize_auto_inline_placeholders( $fields, $allowed_field_names );
		}

		/**
		 * Store parsed runtime settings that Twig rendering can consult later.
		 *
		 * @param string $slug         Block slug.
		 * @param array  $file_headers Parsed Twig headers.
		 */
		private static function store_block_runtime_settings( $slug, $file_headers ) {
			self::$block_runtime_settings[ self::normalize_block_name( $slug ) ] = array(
				'inline_editable_fields' => self::parse_space_separated_header(
					$file_headers,
					'inline_editable_fields'
				),
			);
		}

		/**
		 * Normalize a block name to the ACF-prefixed form.
		 *
		 * @param string $block_name Block name or slug.
		 * @return string
		 */
		private static function normalize_block_name( $block_name ) {
			if ( ! is_string( $block_name ) || '' === $block_name ) {
				return '';
			}

			return false === strpos( $block_name, '/' ) ? 'acf/' . $block_name : $block_name;
		}

		/**
		 * Parse a space-delimited header into a unique list of values.
		 *
		 * @param array  $file_headers Parsed Twig headers.
		 * @param string $header_key   Header key.
		 * @return array
		 */
		private static function parse_space_separated_header( $file_headers, $header_key ) {
			if ( empty( $file_headers[ $header_key ] ) ) {
				return array();
			}

			$items = str_getcsv( $file_headers[ $header_key ], ' ', '"' );
			$items = array_filter( array_map( 'trim', $items ) );

			return array_values( array_unique( $items ) );
		}

		/**
		 * Determine whether metadata points to this package's render callback.
		 *
		 * @param mixed $render_callback Render callback from metadata.
		 * @return bool
		 */
		private static function is_timber_blocks_render_callback( $render_callback ) {
			if ( ! is_string( $render_callback ) ) {
				return false;
			}

			return 'Timber_Acf_Wp_Blocks::timber_blocks_callback' === ltrim( $render_callback, '\\' );
		}

		/**
		 * Get the shared render-template path for auto inline editing.
		 *
		 * @return string
		 */
		private static function get_auto_inline_render_template_path() {
			return __DIR__ . '/timber-acf-wp-blocks-render-template.php';
		}

		/**
		 * Resolve the inline-editable field whitelist for a block.
		 *
		 * @param array $block Current block data.
		 * @return array
		 */
		private static function get_allowed_auto_inline_field_names( $block ) {
			$block_name       = ! empty( $block['name'] ) ? self::normalize_block_name( $block['name'] ) : '';
			$runtime_settings = isset( self::$block_runtime_settings[ $block_name ] )
				? self::$block_runtime_settings[ $block_name ]
				: array();

			if ( ! empty( $runtime_settings['inline_editable_fields'] ) ) {
				return $runtime_settings['inline_editable_fields'];
			}

			return self::get_default_inline_editable_field_names( $block );
		}

		/**
		 * Default to ACF's contenteditable-safe field types when no header whitelist is defined.
		 *
		 * @param array $block Current block data.
		 * @return array
		 */
		private static function get_default_inline_editable_field_names( $block ) {
			if ( ! function_exists( 'acf_get_block_fields' ) ) {
				return array();
			}

			$field_names   = array();
			$allowed_types = array( 'text', 'textarea' );
			$fields        = acf_get_block_fields( $block );

			if ( ! is_array( $fields ) ) {
				return array();
			}

			foreach ( $fields as $field ) {
				if ( empty( $field['name'] ) || empty( $field['type'] ) ) {
					continue;
				}

				if ( in_array( $field['type'], $allowed_types, true ) ) {
					$field_names[] = $field['name'];
				}
			}

			return array_values( array_unique( $field_names ) );
		}

		/**
		 * Recursively strip placeholder tokens for fields that should not stay inline-editable.
		 *
		 * @param mixed $value               Field value or nested data.
		 * @param array $allowed_field_names Field names allowed to keep placeholders.
		 * @return mixed
		 */
		private static function sanitize_auto_inline_placeholders( $value, $allowed_field_names ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $key => $item ) {
					$value[ $key ] = self::sanitize_auto_inline_placeholders( $item, $allowed_field_names );
				}

				return $value;
			}

			if ( ! is_string( $value ) || false === strpos( $value, 'acf_auto_inline_editing_field_name_' ) ) {
				return $value;
			}

			return preg_replace_callback(
				'/acf_auto_inline_editing_field_name_([A-Za-z0-9_]+)/',
				function ( $matches ) use ( $allowed_field_names ) {
					return in_array( $matches[1], $allowed_field_names, true ) ? $matches[0] : '';
				},
				$value
			);
		}

		/**
		 * Generates the list of subfolders based on current directories
		 *
		 * @param array $directories File path array.
		 */
		public static function timber_blocks_subdirectories( $directories ) {
			$ret = array();

			foreach ( $directories as $base_directory ) {
				$base_path = \locate_template( $base_directory );

				// Check if the folder exist.
				if ( ! $base_path || ! file_exists( $base_path ) ) {
					continue;
				}

				$template_directory = new RecursiveDirectoryIterator(
					$base_path,
					FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_SELF
				);

				if ( $template_directory ) {
					foreach ( $template_directory as $directory ) {
						if ( $directory->isDir() && ! $directory->isDot() ) {
							$ret[] = $base_directory . '/' . $directory->getFilename();
						}
					}
				}
			}

			return $ret;
		}

		/**
		 * Universal function to handle getting folders and subfolders
		 */
		public static function timber_block_directory_getter() {
			if ( is_array( self::$cached_block_directories ) ) {
				return self::$cached_block_directories;
			}

			$directories = apply_filters( 'timber/acf-gutenberg-blocks-templates', array( 'views/blocks' ) );

			$subdirectories = self::timber_blocks_subdirectories( $directories );

			if ( ! empty( $subdirectories ) ) {
				$directories = array_merge( $directories, $subdirectories );
			}

			self::$cached_block_directories = $directories;

			return self::$cached_block_directories;
		}

		/**
		 * Default options setter.
		 *
		 * @param  [array] $data - header set data.
		 * @return [array]
		 */
		public static function timber_block_default_data( $data ) {
			$default_data = apply_filters( 'timber/acf-gutenberg-blocks-default-data', array() );
			$data_array   = array();

			if ( ! empty( $data['default_data'] ) ) {
				$default_data_key = $data['default_data'];
			}

			if ( isset( $default_data_key ) && ! empty( $default_data[ $default_data_key ] ) ) {
				$data_array = $default_data[ $default_data_key ];
			} elseif ( ! empty( $default_data['default'] ) ) {
				$data_array = $default_data['default'];
			}

			if ( is_array( $data_array ) ) {
				$data = array_merge( $data_array, $data );
			}

			return $data;
		}

		/**
		 * Determine if block.json auto-generation is enabled.
		 * Priority: filter > constant > WP_DEBUG
		 *
		 * @return bool
		 */
		public static function should_auto_generate_json() {
			// Safety guard: never auto-generate files on production.
			if ( function_exists( 'wp_get_environment_type' ) && 'production' === wp_get_environment_type() ) {
				return false;
			}

			if (
				! function_exists( 'wp_get_environment_type' )
				&& defined( 'WP_ENVIRONMENT_TYPE' )
				&& 'production' === WP_ENVIRONMENT_TYPE
			) {
				return false;
			}

			if ( defined( 'TIMBER_BLOCKS_AUTO_GENERATE' ) ) {
				$default = (bool) constant( 'TIMBER_BLOCKS_AUTO_GENERATE' );
			} else {
				$default = defined( 'WP_DEBUG' )
					? (bool) constant( 'WP_DEBUG' )
					: false;
			}

			return (bool) apply_filters( 'timber/acf-gutenberg-blocks-auto-generate-json', $default );
		}

		/**
		 * Auto-detect example image in block directory.
		 * Looks for example image files (example.png, example.jpg, etc.).
		 *
		 * @param string $absolute_path Absolute path to block directory.
		 * @param string $relative_dir  Relative directory path (e.g., 'views/blocks/my-block').
		 * @return string|null Relative path to example image, or null if not found.
		 */
		public static function auto_detect_example_image( $absolute_path, $relative_dir ) {
			// Example image filenames to look for (in priority order).
			$example_filenames = apply_filters(
				'timber/acf-gutenberg-blocks-example-filenames',
				array(
					'example.png',
					'example.jpg',
					'example.jpeg',
					'example.webp',
					'example.gif',
				)
			);

			foreach ( $example_filenames as $filename ) {
				$image_path = $absolute_path . '/' . $filename;
				if ( file_exists( $image_path ) ) {
					// Return the theme-relative path.
					return $relative_dir . '/' . $filename;
				}
			}

			return null;
		}

		/**
		 * Check if a block uses subfolder structure.
		 *
		 * @param string $directory Base directory (e.g., 'views/blocks').
		 * @param string $slug      Block slug.
		 * @return array|false Returns array with paths if subfolder structure, false if flat.
		 */
		public static function get_block_structure( $directory, $slug ) {
			$theme_path        = get_template_directory();
			$subfolder_path    = $theme_path . '/' . $directory . '/' . $slug;
			$twig_in_subfolder = $subfolder_path . '/' . $slug . '.twig';

			if ( is_dir( $subfolder_path ) && file_exists( $twig_in_subfolder ) ) {
				return array(
					'type'          => 'subfolder',
					'directory'     => $directory . '/' . $slug,
					'absolute_path' => $subfolder_path,
					'twig_file'     => $twig_in_subfolder,
					'block_json'    => $subfolder_path . '/block.json',
				);
			}

			return false;
		}

		/**
		 * Generate block.json content from Twig file headers.
		 *
		 * @param array  $file_headers Parsed headers from Twig file.
		 * @param string $slug         Block slug.
		 * @param array  $existing     Existing block.json data used to preserve unsupported manual properties.
		 * @return array Block.json compatible array.
		 */
		public static function generate_block_json_data( $file_headers, $slug, $existing = array() ) {
			$block_json = array(
				// Keep this first so generated files clearly show their source of truth.
				'_generatedFromTwig' => true,
				'name'               => 'acf/' . $slug,
				'title'              => $file_headers['title'],
				'description'        => $file_headers['description'] ?? '',
				'category'           => $file_headers['category'],
				'apiVersion'         => 3,
				'acf'                => array(
					'blockVersion'   => 3,
					'mode'           => $file_headers['mode'] ?? 'preview',
					'renderCallback' => 'Timber_Acf_Wp_Blocks::timber_blocks_callback',
				),
				'supports'           => array(),
			);

			if ( ! empty( $file_headers['icon'] ) ) {
				$block_json['icon'] = $file_headers['icon'];
			}

			if ( ! empty( $file_headers['keywords'] ) ) {
				$block_json['keywords'] = str_getcsv( $file_headers['keywords'], ' ', '"', '\\' );
			}

			if ( ! empty( $file_headers['align'] ) ) {
				$block_json['align'] = $file_headers['align'];
			}

			if ( ! empty( $file_headers['post_types'] ) ) {
				$block_json['postTypes'] = explode( ' ', $file_headers['post_types'] );
			}

			if ( ! empty( $file_headers['parent'] ) ) {
				$block_json['parent'] = str_getcsv( $file_headers['parent'], ' ', '"' );
			}

			if ( ! empty( $file_headers['ancestor'] ) ) {
				$block_json['ancestor'] = str_getcsv( $file_headers['ancestor'], ' ', '"' );
			}

			if ( ! empty( $file_headers['uses_context'] ) ) {
				$block_json['usesContext'] = explode( ' ', $file_headers['uses_context'] );
			}

			if ( ! empty( $file_headers['provides_context'] ) ) {
				$json = json_decode( $file_headers['provides_context'], true );
				if ( null !== $json ) {
					$block_json['providesContext'] = $json;
				}
			}

			$supports = array();

			if ( ! empty( $file_headers['supports_align'] ) ) {
				$supports['align'] = in_array( $file_headers['supports_align'], array( 'true', 'false' ), true )
					? filter_var( $file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN )
					: explode( ' ', $file_headers['supports_align'] );
			}

			if ( ! empty( $file_headers['supports_align_content'] ) ) {
				$supports['alignContent'] = ( 'true' === $file_headers['supports_align_content'] )
					? true
					: ( ( 'matrix' === $file_headers['supports_align_content'] ) ? 'matrix' : false );
			}

			if ( ! empty( $file_headers['supports_mode'] ) ) {
				$supports['mode'] = ( 'true' === $file_headers['supports_mode'] );
			}

			if ( ! empty( $file_headers['supports_multiple'] ) ) {
				$supports['multiple'] = ( 'true' === $file_headers['supports_multiple'] );
			}

			if ( ! empty( $file_headers['supports_anchor'] ) ) {
				$supports['anchor'] = ( 'true' === $file_headers['supports_anchor'] );
			}

			if ( ! empty( $file_headers['supports_custom_class_name'] ) ) {
				$supports['customClassName'] = ( 'true' === $file_headers['supports_custom_class_name'] );
			}

			if ( ! empty( $file_headers['supports_reusable'] ) ) {
				$supports['reusable'] = ( 'true' === $file_headers['supports_reusable'] );
			}

			if ( ! empty( $file_headers['supports_full_height'] ) ) {
				$supports['fullHeight'] = ( 'true' === $file_headers['supports_full_height'] );
			}

			if ( ! empty( $file_headers['supports_html'] ) ) {
				$supports['html'] = ( 'true' === $file_headers['supports_html'] );
			}

			if ( ! empty( $file_headers['supports_inserter'] ) ) {
				$supports['inserter'] = ( 'true' === $file_headers['supports_inserter'] );
			}

			if ( ! empty( $file_headers['supports_lock'] ) ) {
				$supports['lock'] = ( 'true' === $file_headers['supports_lock'] );
			}

			if ( ! empty( $file_headers['supports_jsx'] ) ) {
				$supports['jsx'] = ( 'true' === $file_headers['supports_jsx'] );
			}

			if ( ! empty( $supports ) ) {
				$block_json['supports'] = $supports;
			} else {
				unset( $block_json['supports'] );
			}

			if ( ! empty( $file_headers['enqueue_style'] ) ) {
				if ( ! filter_var( $file_headers['enqueue_style'], FILTER_VALIDATE_URL ) ) {
					$block_json['editorStyle'] = 'file:../../' . $file_headers['enqueue_style'];
					$block_json['style']       = 'file:../../' . $file_headers['enqueue_style'];
				}
			}

			if ( ! empty( $file_headers['enqueue_script'] ) ) {
				if ( ! filter_var( $file_headers['enqueue_script'], FILTER_VALIDATE_URL ) ) {
					$block_json['editorScript'] = 'file:../../' . $file_headers['enqueue_script'];
					$block_json['script']       = 'file:../../' . $file_headers['enqueue_script'];
				}
			}

			if ( ! empty( $file_headers['enqueue_assets'] ) ) {
				$block_json['acf']['enqueueAssets'] = $file_headers['enqueue_assets'];
			}

			if ( ! empty( $file_headers['hide_sidebar_fields'] ) ) {
				$block_json['acf']['hideFieldsInSidebar'] = ( 'true' === $file_headers['hide_sidebar_fields'] );
			}

			if ( ! empty( $file_headers['auto_inline_editing'] ) ) {
				$block_json['acf']['autoInlineEditing'] = ( 'true' === $file_headers['auto_inline_editing'] );
			}

			if ( ! empty( $file_headers['example_image'] ) ) {
				if ( ! filter_var( $file_headers['example_image'], FILTER_VALIDATE_URL ) ) {
					$block_json['acf']['exampleImage'] = $file_headers['example_image'];
				} else {
					$block_json['acf']['exampleImage'] = $file_headers['example_image'];
				}
			}

			if ( ! empty( $file_headers['example'] ) ) {
				$json = json_decode( $file_headers['example'], true );
				if ( null !== $json ) {
					$block_json['example'] = array(
						'attributes' => array(
							'mode' => 'preview',
							'data' => $json,
						),
					);
				}
			}

			$block_json = self::merge_preserved_block_json_properties( $block_json, $existing );

			return $block_json;
		}

		/**
		 * Preserve unsupported manual block.json properties while keeping Twig-managed keys authoritative.
		 *
		 * @param array $block_json Generated block.json data.
		 * @param array $existing   Existing block.json data.
		 * @return array
		 */
		private static function merge_preserved_block_json_properties( $block_json, $existing ) {
			if ( empty( $existing ) || ! is_array( $existing ) ) {
				return $block_json;
			}

			$managed_top_level_keys = self::get_managed_block_json_keys();

			foreach ( $existing as $key => $value ) {
				if ( 'supports' === $key && is_array( $value ) ) {
					$supports = self::merge_preserved_nested_block_json_properties(
						isset( $block_json['supports'] ) && is_array( $block_json['supports'] )
							? $block_json['supports']
							: array(),
						$value,
						self::get_managed_block_json_support_keys()
					);

					if ( ! empty( $supports ) ) {
						$block_json['supports'] = $supports;
					} else {
						unset( $block_json['supports'] );
					}

					continue;
				}

				if ( 'acf' === $key && is_array( $value ) ) {
					$block_json['acf'] = self::merge_preserved_nested_block_json_properties(
						isset( $block_json['acf'] ) && is_array( $block_json['acf'] )
							? $block_json['acf']
							: array(),
						$value,
						self::get_managed_block_json_acf_keys()
					);

					continue;
				}

				if ( in_array( $key, $managed_top_level_keys, true ) ) {
					continue;
				}

				$block_json[ $key ] = $value;
			}

			return $block_json;
		}

		/**
		 * Preserve unsupported manual properties inside a managed block.json object.
		 *
		 * @param array $generated     Generated nested data.
		 * @param array $existing      Existing nested data.
		 * @param array $managed_keys  Keys controlled by Twig header generation.
		 * @return array
		 */
		private static function merge_preserved_nested_block_json_properties( $generated, $existing, $managed_keys ) {
			foreach ( $existing as $key => $value ) {
				if ( in_array( $key, $managed_keys, true ) ) {
					continue;
				}

				$generated[ $key ] = $value;
			}

			return $generated;
		}

		/**
		 * Get top-level block.json keys managed by Twig header generation.
		 *
		 * @return array
		 */
		private static function get_managed_block_json_keys() {
			return array(
				'name',
				'title',
				'description',
				'category',
				'apiVersion',
				'acf',
				'supports',
				'_generatedFromTwig',
				'icon',
				'keywords',
				'align',
				'postTypes',
				'parent',
				'ancestor',
				'usesContext',
				'providesContext',
				'editorStyle',
				'style',
				'editorScript',
				'script',
				'example',
			);
		}

		/**
		 * Get supports keys managed by Twig header generation.
		 *
		 * @return array
		 */
		private static function get_managed_block_json_support_keys() {
			return array(
				'align',
				'alignContent',
				'mode',
				'multiple',
				'anchor',
				'customClassName',
				'reusable',
				'fullHeight',
				'html',
				'inserter',
				'lock',
				'jsx',
			);
		}

		/**
		 * Get ACF keys managed by Twig header generation.
		 *
		 * @return array
		 */
		private static function get_managed_block_json_acf_keys() {
			return array(
				'blockVersion',
				'mode',
				'renderCallback',
				'enqueueAssets',
				'hideFieldsInSidebar',
				'autoInlineEditing',
				'exampleImage',
			);
		}

		/**
		 * Write block.json file if needed.
		 *
		 * @param string $block_json_path Path to block.json file.
		 * @param string $twig_file_path  Path to source Twig file.
		 * @param array  $block_json_data Generated block.json data.
		 * @return bool True if file was written, false otherwise.
		 */
		public static function maybe_write_block_json( $block_json_path, $twig_file_path, $block_json_data ) {
			if ( ! self::should_auto_generate_json() ) {
				return false;
			}

			$should_write  = false;
			$existing_data = array();

			if ( file_exists( $block_json_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Block metadata files live on the local filesystem.
				$existing_content = file_get_contents( $block_json_path );
				$existing_data    = json_decode( $existing_content, true );

				if ( isset( $existing_data['_generatedFromTwig'] ) && true === $existing_data['_generatedFromTwig'] ) {
					if ( filemtime( $twig_file_path ) > filemtime( $block_json_path ) ) {
						$should_write = true;
					}
				}
			} else {
				$should_write = true;
			}

			if ( $should_write ) {
				$block_json_data['_generatedFromTwig'] = true;

				$json_content = wp_json_encode( $block_json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Block metadata files live on the local filesystem.
				$result = file_put_contents( $block_json_path, $json_content );

				return false !== $result;
			}

			return false;
		}

		/**
		 * Register a block using block.json (modern method).
		 *
		 * @param string $block_json_path Path to block.json file.
		 * @return bool True if registered successfully.
		 */
		public static function register_block_from_json( $block_json_path ) {
			if ( ! file_exists( $block_json_path ) ) {
				return false;
			}

			$block_dir = dirname( $block_json_path );

			$result = register_block_type( $block_dir );

			return false !== $result;
		}

		/**
		 * Track a block using flat file structure for admin notice.
		 *
		 * @param string $slug Block slug.
		 */
		public static function trigger_flat_structure_deprecation( $slug ) {
			self::$flat_structure_blocks[] = $slug;
		}

		/**
		 * Handle dismissal of flat structure notice.
		 */
		public static function handle_flat_structure_notice_dismiss() {
			if (
				isset( $_GET['timber_dismiss_flat_notice'] ) &&
				'1' === $_GET['timber_dismiss_flat_notice'] &&
				current_user_can( 'manage_options' )
			) {
				check_admin_referer( 'timber_dismiss_flat_notice' );

				$dismissed = get_option( 'timber_blocks_dismissed_flat_slugs', array() );
				$dismissed = array_unique( array_merge( $dismissed, self::$flat_structure_blocks ) );
				update_option( 'timber_blocks_dismissed_flat_slugs', $dismissed );

				wp_safe_redirect(
					remove_query_arg( array( 'timber_dismiss_flat_notice', '_wpnonce' ) )
				);
				exit;
			}
		}

		/**
		 * Show admin notice for blocks using flat file structure.
		 */
		public static function show_flat_structure_notice() {
			// Only show in debug mode and to admins.
			if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$dismissed = get_option( 'timber_blocks_dismissed_flat_slugs', array() );

			$new_flat_blocks = array_diff( self::$flat_structure_blocks, $dismissed );

			if ( empty( $new_flat_blocks ) ) {
				return;
			}

			$dismiss_url = wp_nonce_url(
				add_query_arg( 'timber_dismiss_flat_notice', '1' ),
				'timber_dismiss_flat_notice'
			);

			$block_list = '<code>' . implode(
				'</code>, <code>',
				array_map( 'esc_html', $new_flat_blocks )
			) . '</code>';

			echo '<div class="notice notice-warning">';
			echo wp_kses_post(
				'<p><strong>Timber ACF Blocks:</strong> The following blocks use the legacy flat file structure: ' .
					$block_list .
					'</p>'
			);
			echo '<p>Consider migrating to the subfolder structure with <code>block.json</code> ';
			echo 'for better performance and compatibility. ';
			echo '<a href="https://kevin-terry.github.io/timber-acf-wp-blocks/#/block-json" ';
			echo 'target="_blank">';
			echo 'View migration guide →</a></p>';
			echo '<p><a href="' . esc_url( $dismiss_url ) . '">Dismiss this notice</a></p>';
			echo '</div>';
		}
	}
}

if ( is_callable( 'add_action' ) ) {
	add_action(
		'after_setup_theme',
		function () {
			new Timber_Acf_Wp_Blocks();
		}
	);
}
