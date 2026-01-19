<?php

use Timber\Timber;

/**
 * Check if class exists before redefining it
 */
if (! class_exists('Timber_Acf_Wp_Blocks')) {
	/**
	 * Main Timber_Acf_Wp_Block Class
	 */
	class Timber_Acf_Wp_Blocks
	{
		/**
		 * Constructor
		 */
		public function __construct()
		{
			if (
				is_callable('add_action')
				&& is_callable('acf_register_block_type')
				&& class_exists('Timber')
			) {
				add_action('acf/init', array(__CLASS__, 'timber_block_init'), 10, 0);
			} elseif (is_callable('add_action')) {
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
		public static function timber_block_init()
		{
			// Get base directories (not including auto-discovered subfolders).
			$base_directories = apply_filters('timber/acf-gutenberg-blocks-templates', array('views/blocks'));

			// Track registered blocks to avoid duplicates.
			$registered_blocks = array();

			foreach ($base_directories as $base_dir) {
				$base_path = \locate_template($base_dir);

				if (! $base_path || ! file_exists($base_path)) {
					continue;
				}

				$directory_iterator = new DirectoryIterator($base_path);

				foreach ($directory_iterator as $item) {
					if ($item->isDot()) {
						continue;
					}

					// Check for subfolder structure: views/blocks/my-block/my-block.twig
					if ($item->isDir()) {
						$slug = $item->getFilename();
						$subfolder_twig = $base_path . '/' . $slug . '/' . $slug . '.twig';

						if (file_exists($subfolder_twig)) {
							// Subfolder structure found
							$structure = array(
								'type'          => 'subfolder',
								'directory'     => $base_dir . '/' . $slug,
								'absolute_path' => $base_path . '/' . $slug,
								'twig_file'     => $subfolder_twig,
								'block_json'    => $base_path . '/' . $slug . '/block.json',
							);

							if (! isset($registered_blocks[$slug])) {
								self::register_block_from_structure($slug, $structure);
								$registered_blocks[$slug] = true;
							}
						}
						continue;
					}

					// Flat file structure: views/blocks/my-block.twig
					if ($item->isFile()) {
						$file_parts = pathinfo($item->getFilename());

						if (! isset($file_parts['extension']) || 'twig' !== $file_parts['extension']) {
							continue;
						}

						$slug = $file_parts['filename'];

						// Skip if already registered via subfolder
						if (isset($registered_blocks[$slug])) {
							continue;
						}

						$structure = array(
							'type'          => 'flat',
							'directory'     => $base_dir,
							'absolute_path' => $base_path,
							'twig_file'     => $base_path . '/' . $slug . '.twig',
							'block_json'    => null,
						);

						self::register_block_from_structure($slug, $structure);
						$registered_blocks[$slug] = true;
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
		public static function register_block_from_structure($slug, $structure)
		{
			// Get header info from the Twig file.
			$file_headers = self::get_twig_file_headers($structure['twig_file']);

			// Validate required headers.
			if (empty($file_headers['title']) || empty($file_headers['category'])) {
				return;
			}

			if ($structure['type'] === 'subfolder') {
				// Modern subfolder structure - use block.json
				$block_json_path = $structure['block_json'];
				$existing_data = array();

				// Load existing block.json if it exists
				if (file_exists($block_json_path)) {
					$existing_content = file_get_contents($block_json_path);
					$existing_data = json_decode($existing_content, true) ?? array();
				}

				// Auto-detect example image if not specified in headers
				if (empty($file_headers['example_image'])) {
					$file_headers['example_image'] = self::auto_detect_example_image($structure['absolute_path'], $structure['directory']);
				}

				// Generate block.json data from Twig headers
				$block_json_data = self::generate_block_json_data($file_headers, $slug, $existing_data);

				// Maybe write/update block.json
				self::maybe_write_block_json($block_json_path, $structure['twig_file'], $block_json_data);

				// Register using block.json if it exists
				if (file_exists($block_json_path)) {
					self::register_block_from_json($block_json_path);
					return;
				}
			}

			// Flat structure or block.json doesn't exist - use legacy method
			if ($structure['type'] === 'flat') {
				self::trigger_flat_structure_deprecation($slug);
			}

			// Use legacy acf_register_block_type for flat files
			$data = self::build_legacy_block_data($file_headers, $slug);
			$data = self::timber_block_default_data($data);
			acf_register_block_type($data);
		}

		/**
		 * Get standardized file headers from a Twig template.
		 *
		 * @param string $file_path Absolute path to Twig file.
		 * @return array Parsed headers.
		 */
		public static function get_twig_file_headers($file_path)
		{
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
					'example'                    => 'Example',
					'example_image'              => 'ExampleImage',
					'supports_jsx'               => 'SupportsJSX',
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
		public static function build_legacy_block_data($file_headers, $slug)
		{
			// Keywords exploding with quotes.
			$keywords = str_getcsv($file_headers['keywords'], ' ', '"', '\\');

			// Set up block data for registration.
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
				'render_callback'   => array(__CLASS__, 'timber_blocks_callback'),
				'enqueue_assets'    => $file_headers['enqueue_assets'],
				'default_data'      => $file_headers['default_data'],
			);

			// Removes empty defaults.
			$data = array_filter($data);

			// If the PostTypes header is set in the template, restrict this block
			// to those types.
			if (! empty($file_headers['post_types'])) {
				$data['post_types'] = explode(' ', $file_headers['post_types']);
			}
			// If the SupportsAlign header is set in the template, restrict this block
			// to those aligns.
			if (! empty($file_headers['supports_align'])) {
				$data['supports']['align'] =
					in_array($file_headers['supports_align'], array('true', 'false'), true) ?
					filter_var($file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN) :
					explode(' ', $file_headers['supports_align']);
			}
			// If the SupportsAlignContent header is set in the template, restrict this block
			// to those aligns.
			if (! empty($file_headers['supports_align_content'])) {
				$data['supports']['alignContent'] = ('true' === $file_headers['supports_align_content']) ?
					true : (('matrix' === $file_headers['supports_align_content']) ? "matrix" : false);
			}
			// If the SupportsMode header is set in the template, restrict this block
			// mode feature.
			if (! empty($file_headers['supports_mode'])) {
				$data['supports']['mode'] =
					('true' === $file_headers['supports_mode']) ? true : false;
			}
			// If the SupportsMultiple header is set in the template, restrict this block
			// multiple feature.
			if (! empty($file_headers['supports_multiple'])) {
				$data['supports']['multiple'] =
					('true' === $file_headers['supports_multiple']) ? true : false;
			}
			// If the SupportsAnchor header is set in the template, restrict this block
			// anchor feature.
			if (! empty($file_headers['supports_anchor'])) {
				$data['supports']['anchor'] =
					('true' === $file_headers['supports_anchor']) ? true : false;
			}

			// If the SupportsCustomClassName is set to false hides the possibilty to
			// add custom class name.
			if (! empty($file_headers['supports_custom_class_name'])) {
				$data['supports']['customClassName'] =
					('true' === $file_headers['supports_custom_class_name']) ? true : false;
			}

			// If the SupportsReusable is set in the templates it adds a posibility to
			// make this block reusable.
			if (! empty($file_headers['supports_reusable'])) {
				$data['supports']['reusable'] =
					('true' === $file_headers['supports_reusable']) ? true : false;
			}

			// If the SupportsFullHeight is set in the templates it adds a posibility to
			// make this block full height.
			if (! empty($file_headers['supports_full_height'])) {
				$data['supports']['full_height'] =
					('true' === $file_headers['supports_full_height']) ? true : false;
			}

			// Gives a possibility to enqueue style. If not an absoulte URL than adds
			// theme directory.
			if (! empty($file_headers['enqueue_style'])) {
				if (! filter_var($file_headers['enqueue_style'], FILTER_VALIDATE_URL)) {
					$data['enqueue_style'] =
						get_template_directory_uri() . '/' . $file_headers['enqueue_style'];
				} else {
					$data['enqueue_style'] = $file_headers['enqueue_style'];
				}
			}

			// Gives a possibility to enqueue script. If not an absoulte URL than adds
			// theme directory.
			if (! empty($file_headers['enqueue_script'])) {
				if (! filter_var($file_headers['enqueue_script'], FILTER_VALIDATE_URL)) {
					$data['enqueue_script'] =
						get_template_directory_uri() . '/' . $file_headers['enqueue_script'];
				} else {
					$data['enqueue_script'] = $file_headers['enqueue_script'];
				}
			}

			// Gives a possibility to set an example image. If not an absolute URL than adds
			// theme directory.
			if (! empty($file_headers['example_image'])) {
				if (! filter_var($file_headers['example_image'], FILTER_VALIDATE_URL)) {
					$data['example_image'] =
						get_template_directory_uri() . '/' . $file_headers['example_image'];
				} else {
					$data['example_image'] = $file_headers['example_image'];
				}
			}

			// Support for experimantal JSX.
			if (! empty($file_headers['supports_jsx'])) {
				// Leaving the experimaental part for 2 versions.
				$data['supports']['__experimental_jsx'] =
					('true' === $file_headers['supports_jsx']) ? true : false;
				$data['supports']['jsx']                =
					('true' === $file_headers['supports_jsx']) ? true : false;
			}

			// Support for "example".
			if (! empty($file_headers['example'])) {
				$json                       = json_decode($file_headers['example'], true);
				$example_data               = (null !== $json) ? $json : array();
				$example_data['is_example'] = true;
				$data['example']            = array(
					'attributes' => array(
						'mode' => 'preview',
						'data' => $example_data,
					),
				);
			}

			// Support for "parent".
			if (! empty($file_headers['parent'])) {
				$data['parent'] = str_getcsv($file_headers['parent'], ' ', '"');
			}

			// Support for "ancestor".
			if (! empty($file_headers['ancestor'])) {
				$data['ancestor'] = str_getcsv($file_headers['ancestor'], ' ', '"');
			}

			// Support for "usesContext".
			if (! empty($file_headers['uses_context'])) {
				$data['uses_context'] = explode(' ', $file_headers['uses_context']);
			}

			// Support for "providesContext".
			if (! empty($file_headers['provides_context'])) {
				$json = json_decode($file_headers['provides_context'], true);
				if (null !== $json) {
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
		public static function timber_blocks_callback($block, $content = '', $is_preview = false, $post_id = 0, $wp_block = null, $block_context = array())
		{
			// Set up the slug to be useful.
			$slug = str_replace('acf/', '', $block['name']);

			$is_example = false;

			if (! empty($block['data']['is_example'])) {
				$is_example = true;
			}

			// Get example_image from either legacy location or ACF block.json location
			$example_image = null;
			if (! empty($block['example_image'])) {
				$example_image = $block['example_image'];
			} elseif (! empty($block['acf']['exampleImage'])) {
				// From block.json acf.exampleImage - resolve relative path
				$example_image = $block['acf']['exampleImage'];
				if (! filter_var($example_image, FILTER_VALIDATE_URL)) {
					$example_image = get_template_directory_uri() . '/' . $example_image;
				}
			}

			// If this is an example and we have an example_image, render the image instead.
			if ($is_example && ! empty($example_image)) {
				echo '<img src="' . esc_url($example_image) . '" alt="' . esc_attr($block['title']) . '" style="width: 100%; height: auto;" />';
				return;
			}

			// Context compatibility.
			if (method_exists('Timber', 'context')) {
				$context = Timber::context();
			} else {
				$context = Timber::get_context();
			}

			$context['block']         = $block;
			$context['post_id']       = $post_id;
			$context['slug']          = $slug;
			$context['is_preview']    = $is_preview;
			$context['fields']        = \get_fields();
			$context['wp_block']      = $wp_block;
			$context['block_context'] = $block_context;
			$classes               = array_merge(
				array($slug),
				isset($block['className']) ? array($block['className']) : array(),
				$is_preview ? array('is-preview') : array(),
				array('align' . $context['block']['align'])
			);

			$context['classes'] = implode(' ', $classes);

			if ($is_example) {
				$context['fields'] = $block['data'];
			}

			$context = apply_filters('timber/acf-gutenberg-blocks-data', $context);
			$context = apply_filters('timber/acf-gutenberg-blocks-data/' . $slug, $context);
			$context = apply_filters('timber/acf-gutenberg-blocks-data/' . $block['id'], $context);

			$paths = self::timber_acf_path_render($slug, $is_preview, $is_example);

			Timber::render($paths, $context);
		}

		/**
		 * Generates array with paths and slugs
		 *
		 * @param string $slug       File slug.
		 * @param bool   $is_preview Checks if preview.
		 * @param bool   $is_example Checks if example.
		 */
		public static function timber_acf_path_render($slug, $is_preview, $is_example)
		{
			$directories = self::timber_block_directory_getter();

			$ret = array();

			/**
			 * Filters the name of suffix for example file.
			 *
			 * @since 1.12
			 */
			$example_identifier = apply_filters('timber/acf-gutenberg-blocks-example-identifier', '-example');

			/**
			 * Filters the name of suffix for preview file.
			 *
			 * @since 1.12
			 */
			$preview_identifier = apply_filters('timber/acf-gutenberg-blocks-preview-identifier', '-preview');

			foreach ($directories as $directory) {
				if ($is_example) {
					$ret[] = $directory . "/{$slug}{$example_identifier}.twig";
				}
				if ($is_preview) {
					$ret[] = $directory . "/{$slug}{$preview_identifier}.twig";
				}
				$ret[] = $directory . "/{$slug}.twig";
			}

			return $ret;
		}

		/**
		 * Generates the list of subfolders based on current directories
		 *
		 * @param array $directories File path array.
		 */
		public static function timber_blocks_subdirectories($directories)
		{
			$ret = array();

			foreach ($directories as $base_directory) {
				// Check if the folder exist.
				if (! file_exists(\locate_template($base_directory))) {
					continue;
				}

				$template_directory = new RecursiveDirectoryIterator(
					\locate_template($base_directory),
					FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_SELF
				);

				if ($template_directory) {
					foreach ($template_directory as $directory) {
						if ($directory->isDir() && ! $directory->isDot()) {
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
		public static function timber_block_directory_getter()
		{
			// Get an array of directories containing blocks.
			$directories = apply_filters('timber/acf-gutenberg-blocks-templates', array('views/blocks'));

			// Check subfolders.
			$subdirectories = self::timber_blocks_subdirectories($directories);

			if (! empty($subdirectories)) {
				$directories = array_merge($directories, $subdirectories);
			}

			return $directories;
		}

		/**
		 * Default options setter.
		 *
		 * @param  [array] $data - header set data.
		 * @return [array]
		 */
		public static function timber_block_default_data($data)
		{
			$default_data = apply_filters('timber/acf-gutenberg-blocks-default-data', array());
			$data_array   = array();

			if (! empty($data['default_data'])) {
				$default_data_key = $data['default_data'];
			}

			if (isset($default_data_key) && ! empty($default_data[$default_data_key])) {
				$data_array = $default_data[$default_data_key];
			} elseif (! empty($default_data['default'])) {
				$data_array = $default_data['default'];
			}

			if (is_array($data_array)) {
				$data = array_merge($data_array, $data);
			}

			return $data;
		}

		/**
		 * Determine if block.json auto-generation is enabled.
		 * Priority: filter > constant > WP_DEBUG
		 *
		 * @return bool
		 */
		public static function should_auto_generate_json()
		{
			$default = defined('TIMBER_BLOCKS_AUTO_GENERATE')
				? TIMBER_BLOCKS_AUTO_GENERATE
				: (defined('WP_DEBUG') && WP_DEBUG);

			return apply_filters('timber/acf-gutenberg-blocks-auto-generate-json', $default);
		}

		/**
		 * Auto-detect example image in block directory.
		 * Looks for example image files (example.png, example.jpg, etc.).
		 *
		 * @param string $absolute_path Absolute path to block directory.
		 * @param string $relative_dir  Relative directory path (e.g., 'views/blocks/my-block').
		 * @return string|null Relative path to example image, or null if not found.
		 */
		public static function auto_detect_example_image($absolute_path, $relative_dir)
		{
			// Example image filenames to look for (in priority order)
			$example_filenames = apply_filters('timber/acf-gutenberg-blocks-example-filenames', array(
				'example.png',
				'example.jpg',
				'example.jpeg',
				'example.webp',
				'example.gif',
			));

			foreach ($example_filenames as $filename) {
				$image_path = $absolute_path . '/' . $filename;
				if (file_exists($image_path)) {
					// Return the theme-relative path
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
		public static function get_block_structure($directory, $slug)
		{
			$theme_path = get_template_directory();
			$subfolder_path = $theme_path . '/' . $directory . '/' . $slug;
			$twig_in_subfolder = $subfolder_path . '/' . $slug . '.twig';

			if (is_dir($subfolder_path) && file_exists($twig_in_subfolder)) {
				return array(
					'type'           => 'subfolder',
					'directory'      => $directory . '/' . $slug,
					'absolute_path'  => $subfolder_path,
					'twig_file'      => $twig_in_subfolder,
					'block_json'     => $subfolder_path . '/block.json',
				);
			}

			return false;
		}

		/**
		 * Generate block.json content from Twig file headers.
		 *
		 * @param array  $file_headers Parsed headers from Twig file.
		 * @param string $slug         Block slug.
		 * @param array  $existing     Existing block.json data (if any).
		 * @return array Block.json compatible array.
		 */
		public static function generate_block_json_data($file_headers, $slug, $existing = array())
		{
			$block_json = array(
				'name'            => 'acf/' . $slug,
				'title'           => $file_headers['title'],
				'description'     => $file_headers['description'] ?? '',
				'category'        => $file_headers['category'],
				'apiVersion'      => 3,
				'acf'             => array(
					'blockVersion'   => 3,
					'mode'           => $file_headers['mode'] ?? 'preview',
					'renderCallback' => 'Timber_Acf_Wp_Blocks::timber_blocks_callback',
				),
				'supports'        => array(),
				'_generatedFromTwig' => true,
			);

			// Icon
			if (! empty($file_headers['icon'])) {
				$block_json['icon'] = $file_headers['icon'];
			}

			// Keywords
			if (! empty($file_headers['keywords'])) {
				$block_json['keywords'] = str_getcsv($file_headers['keywords'], ' ', '"', '\\');
			}

			// Align
			if (! empty($file_headers['align'])) {
				$block_json['align'] = $file_headers['align'];
			}

			// Post Types
			if (! empty($file_headers['post_types'])) {
				$block_json['postTypes'] = explode(' ', $file_headers['post_types']);
			}

			// Parent blocks
			if (! empty($file_headers['parent'])) {
				$block_json['parent'] = str_getcsv($file_headers['parent'], ' ', '"');
			}

			// Ancestor blocks
			if (! empty($file_headers['ancestor'])) {
				$block_json['ancestor'] = str_getcsv($file_headers['ancestor'], ' ', '"');
			}

			// Uses context
			if (! empty($file_headers['uses_context'])) {
				$block_json['usesContext'] = explode(' ', $file_headers['uses_context']);
			}

			// Provides context
			if (! empty($file_headers['provides_context'])) {
				$json = json_decode($file_headers['provides_context'], true);
				if (null !== $json) {
					$block_json['providesContext'] = $json;
				}
			}

			// Supports
			$supports = array();

			if (! empty($file_headers['supports_align'])) {
				$supports['align'] = in_array($file_headers['supports_align'], array('true', 'false'), true)
					? filter_var($file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN)
					: explode(' ', $file_headers['supports_align']);
			}

			if (! empty($file_headers['supports_align_content'])) {
				$supports['alignContent'] = ('true' === $file_headers['supports_align_content'])
					? true
					: (('matrix' === $file_headers['supports_align_content']) ? 'matrix' : false);
			}

			if (! empty($file_headers['supports_mode'])) {
				$supports['mode'] = ('true' === $file_headers['supports_mode']);
			}

			if (! empty($file_headers['supports_multiple'])) {
				$supports['multiple'] = ('true' === $file_headers['supports_multiple']);
			}

			if (! empty($file_headers['supports_anchor'])) {
				$supports['anchor'] = ('true' === $file_headers['supports_anchor']);
			}

			if (! empty($file_headers['supports_custom_class_name'])) {
				$supports['customClassName'] = ('true' === $file_headers['supports_custom_class_name']);
			}

			if (! empty($file_headers['supports_reusable'])) {
				$supports['reusable'] = ('true' === $file_headers['supports_reusable']);
			}

			if (! empty($file_headers['supports_full_height'])) {
				$supports['fullHeight'] = ('true' === $file_headers['supports_full_height']);
			}

			if (! empty($file_headers['supports_jsx'])) {
				$supports['jsx'] = ('true' === $file_headers['supports_jsx']);
			}

			if (! empty($supports)) {
				$block_json['supports'] = $supports;
			} else {
				unset($block_json['supports']);
			}

			// Enqueue style (relative path for block.json)
			if (! empty($file_headers['enqueue_style'])) {
				if (! filter_var($file_headers['enqueue_style'], FILTER_VALIDATE_URL)) {
					// For block.json, use file: prefix for theme-relative paths
					$block_json['editorStyle'] = 'file:../../' . $file_headers['enqueue_style'];
					$block_json['style'] = 'file:../../' . $file_headers['enqueue_style'];
				}
			}

			// Enqueue script (relative path for block.json)
			if (! empty($file_headers['enqueue_script'])) {
				if (! filter_var($file_headers['enqueue_script'], FILTER_VALIDATE_URL)) {
					$block_json['editorScript'] = 'file:../../' . $file_headers['enqueue_script'];
					$block_json['script'] = 'file:../../' . $file_headers['enqueue_script'];
				}
			}

			// Enqueue assets callback (ACF specific)
			if (! empty($file_headers['enqueue_assets'])) {
				$block_json['acf']['enqueueAssets'] = $file_headers['enqueue_assets'];
			}

			// Example image (custom extension for static preview)
			if (! empty($file_headers['example_image'])) {
				if (! filter_var($file_headers['example_image'], FILTER_VALIDATE_URL)) {
					// Relative path - will need to be resolved at runtime
					$block_json['acf']['exampleImage'] = $file_headers['example_image'];
				} else {
					$block_json['acf']['exampleImage'] = $file_headers['example_image'];
				}
			}

			// Example data
			if (! empty($file_headers['example'])) {
				$json = json_decode($file_headers['example'], true);
				if (null !== $json) {
					$block_json['example'] = array(
						'attributes' => array(
							'mode' => 'preview',
							'data' => $json,
						),
					);
				}
			}

			// Merge with existing, preserving extra properties from existing JSON
			// but letting Twig headers win for properties they define
			if (! empty($existing)) {
				// Preserve extra properties from existing that aren't in our generated data
				foreach ($existing as $key => $value) {
					if (! isset($block_json[$key])) {
						$block_json[$key] = $value;
					}
				}

				// Merge supports specially to preserve extra support flags
				if (isset($existing['supports']) && is_array($existing['supports'])) {
					$block_json['supports'] = array_merge(
						$existing['supports'],
						$block_json['supports'] ?? array()
					);
				}

				// Merge acf settings specially
				if (isset($existing['acf']) && is_array($existing['acf'])) {
					$block_json['acf'] = array_merge(
						$existing['acf'],
						$block_json['acf']
					);
				}
			}

			return $block_json;
		}

		/**
		 * Write block.json file if needed.
		 *
		 * @param string $block_json_path Path to block.json file.
		 * @param string $twig_file_path  Path to source Twig file.
		 * @param array  $block_json_data Generated block.json data.
		 * @return bool True if file was written, false otherwise.
		 */
		public static function maybe_write_block_json($block_json_path, $twig_file_path, $block_json_data)
		{
			// Check if auto-generation is enabled
			if (! self::should_auto_generate_json()) {
				return false;
			}

			$should_write = false;
			$existing_data = array();

			if (file_exists($block_json_path)) {
				$existing_content = file_get_contents($block_json_path);
				$existing_data = json_decode($existing_content, true);

				// Check if this file was auto-generated and should be updated
				if (isset($existing_data['_generatedFromTwig']) && $existing_data['_generatedFromTwig'] === true) {
					// Check if Twig file is newer than block.json
					if (filemtime($twig_file_path) > filemtime($block_json_path)) {
						$should_write = true;
					}
				}
				// If _generatedFromTwig is false or missing, don't overwrite (manual control)
			} else {
				// No block.json exists, create one
				$should_write = true;
			}

			if ($should_write) {
				// Re-generate with existing data to preserve extra properties
				if (! empty($existing_data)) {
					$block_json_data = array_merge($existing_data, $block_json_data);
					// Ensure our flag is set
					$block_json_data['_generatedFromTwig'] = true;
				}

				$json_content = json_encode($block_json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				$result = file_put_contents($block_json_path, $json_content);

				return $result !== false;
			}

			return false;
		}

		/**
		 * Register a block using block.json (modern method).
		 *
		 * @param string $block_json_path Path to block.json file.
		 * @return bool True if registered successfully.
		 */
		public static function register_block_from_json($block_json_path)
		{
			if (! file_exists($block_json_path)) {
				return false;
			}

			$block_dir = dirname($block_json_path);

			// register_block_type reads block.json and handles ACF blocks properly
			$result = register_block_type($block_dir);

			return $result !== false;
		}

		/**
		 * Trigger deprecation warning for flat file structure.
		 *
		 * @param string $slug Block slug.
		 */
		public static function trigger_flat_structure_deprecation($slug)
		{
			if (defined('WP_DEBUG') && WP_DEBUG) {
				$message = sprintf(
					'Timber ACF Blocks: Block "%s" uses flat file structure which is deprecated. ' .
						'Consider migrating to subfolder structure: views/blocks/%s/%s.twig with block.json. ' .
						'See documentation for migration guide.',
					$slug,
					$slug,
					$slug
				);
				trigger_error($message, E_USER_DEPRECATED);
			}
		}
	}
}

if (is_callable('add_action')) {
	add_action(
		'after_setup_theme',
		function () {
			new Timber_Acf_Wp_Blocks();
		}
	);
}
