# Block.json Support

As of ACF PRO 6.6+, the recommended way to register ACF blocks is using `block.json` files. This plugin now supports automatic generation of `block.json` files from your Twig template headers.

## API Version

The plugin now registers blocks with:

- WordPress Block API v3 (`apiVersion: 3`)
- ACF Block Version 3 (`acf.blockVersion: 3`)

Enables the latest features including iframe editor rendering.

## Structure Types

### Legacy Flat Structure (Deprecated)

```txt
views/blocks/
├── my-block.twig
├── another-block.twig
```

This structure still works but will trigger a deprecation warning in debug mode. Consider migrating to the subfolder structure.

### Modern Subfolder Structure (Recommended)

```txt
views/blocks/
├── my-block/
│   ├── my-block.twig
│   └── block.json (auto-generated)
├── another-block/
│   ├── another-block.twig
│   └── block.json (auto-generated)
```

## Automatic block.json Generation

When using the subfolder structure, the plugin can automatically generate and maintain `block.json` files from your Twig template headers.

### How It Works

1. Place your Twig file in a subfolder: `views/blocks/my-block/my-block.twig`
2. Add your block headers as usual in the Twig file
3. The plugin detects the subfolder structure and generates `block.json`
4. The block is registered using the modern `register_block_type()` function

### Controlling Auto-Generation

Auto-generation is controlled by a priority chain:

1. **Production safety guard** (highest priority):
   If `wp_get_environment_type()` (or `WP_ENVIRONMENT_TYPE`) is `production`, auto-generation is always disabled.

2. **Filter**:

```php
add_filter('timber/acf-gutenberg-blocks-auto-generate-json', function($enabled) {
    return true; // or false
});
```

3. **Constant** (if filter not set):

```php
// In wp-config.php or functions.php
define('TIMBER_BLOCKS_AUTO_GENERATE', true);
```

4. **WP_DEBUG** (default fallback):
   If neither filter nor constant is defined, auto-generation follows `WP_DEBUG`.

### The `_generatedFromTwig` Flag

Generated `block.json` files include a special flag:

```json
{
  "name": "acf/my-block",
  "title": "My Block",
  "_generatedFromTwig": true
}
```

This flag controls regeneration behavior:

- `true` - File was auto-generated and will be updated when Twig changes
- `false` - File is manually maintained; auto-generation skipped
- Missing - Treated as manually maintained

**To stop auto-generation for a specific block**, set the flag to `false`:

```json
{
  "_generatedFromTwig": false
}
```

### Preserving Custom Properties

When regenerating `block.json`, the plugin:

- **Overwrites** properties defined in your Twig headers
- **Preserves** extra properties you've added manually to the JSON

This allows you to add advanced block.json features not supported by Twig headers while still benefiting from auto-generation.

## Migration Guide

### Step 1: Create Subfolders

For each block, create a subfolder with the same name as the block:

```bash
# Before
views/blocks/my-block.twig

# After
views/blocks/my-block/my-block.twig
```

### Step 2: Move Files

Move your Twig file into its subfolder. If you have associated CSS/JS files, consider moving them too:

```
views/blocks/my-block/
├── my-block.twig
├── my-block.css (optional)
└── my-block.js (optional)
```

### Step 3: Enable Auto-Generation

Enable auto-generation in development:

```php
define('TIMBER_BLOCKS_AUTO_GENERATE', true);
```

### Step 4: Commit Generated Files

The generated `block.json` files should be committed to version control. This ensures:

- Production sites don't need write permissions
- Consistent block registration across environments
- Faster loading (no regeneration check)

### Step 5: (Optional) Disable for Production

For production, you can disable auto-generation:

```php
define('TIMBER_BLOCKS_AUTO_GENERATE', false);
```

Or rely on the `WP_DEBUG` default (auto-generation only when debugging).

## Header to block.json Mapping

| Twig Header             | block.json Property         |
| ----------------------- | --------------------------- |
| Title                   | `title`                     |
| Description             | `description`               |
| Category                | `category`                  |
| Icon                    | `icon`                      |
| Keywords                | `keywords` (array)          |
| Mode                    | `acf.mode`                  |
| Align                   | `align`                     |
| PostTypes               | `postTypes` (array)         |
| Parent                  | `parent` (array)            |
| Ancestor                | `ancestor` (array)          |
| UsesContext             | `usesContext` (array)       |
| ProvidesContext         | `providesContext` (object)  |
| SupportsAlign           | `supports.align`            |
| SupportsAlignContent    | `supports.alignContent`     |
| SupportsMode            | `supports.mode`             |
| SupportsMultiple        | `supports.multiple`         |
| SupportsAnchor          | `supports.anchor`           |
| SupportsCustomClassName | `supports.customClassName`  |
| SupportsReusable        | `supports.reusable`         |
| SupportsFullHeight      | `supports.fullHeight`       |
| SupportsJSX             | `supports.jsx`              |
| EnqueueStyle            | `style` / `editorStyle`     |
| EnqueueScript           | `script` / `editorScript`   |
| Example                 | `example`                   |
| ExampleImage            | `acf.exampleImage` (custom) |
| HideSidebarFields       | `acf.hideFieldsInSidebar`   |
| AutoInlineEditing       | `acf.autoInlineEditing`     |

## Custom Extensions

### Example Image

This is a **non-standard extension** specific to this plugin. It allows you to display a static image instead of rendering the block when shown in the block inserter preview.

```twig
{# ExampleImage: images/blocks/my-block-preview.png #}
```

When this is set and the block is being previewed in the inserter, the image will be displayed instead of rendering the block template. This is useful for:

- Complex blocks with external data dependencies
- Providing polished marketing screenshots
- Performance optimization in the editor

#### Auto-Detection of Example Images

For the **subfolder structure**, you don't even need to specify the `ExampleImage` header! Simply place an `example.jpg` (or other format) in your block's folder:

```
views/blocks/my-block/
├── my-block.twig
├── block.json
└── example.jpg    ← Auto-detected!
```

Supported formats (checked in order):

- `example.png`
- `example.jpg`
- `example.jpeg`
- `example.webp`
- `example.gif`

You can customize this list with a filter:

```php
add_filter('timber/acf-gutenberg-blocks-example-filenames', function($filenames) {
    return ['block-example.png', 'example.png'];
});
```

### Hide Fields In Sidebar

`HideSidebarFields` maps directly to ACF's `hideFieldsInSidebar` setting.

> **Note:** Only applies to blocks using generated `block.json` files (the modern subfolder structure).

```twig
{# HideSidebarFields: true #}
```

Use `true` or `false` (same convention as other boolean Twig headers).

### Automatic Inline Editing

`AutoInlineEditing` maps directly to ACF's `autoInlineEditing` setting.

> **Note:** Only applies to blocks using generated `block.json` files (the modern subfolder structure).

```twig
{# AutoInlineEditing: true #}
```

Use `true` or `false` (same convention as other boolean Twig headers).

Recommended usage:

- Use `AutoInlineEditing: true` for simple blocks where a field value is rendered directly in one element.
- Use `AutoInlineEditing: false` when templates have conditional logic or more complex field output.

Common caveats:

- Repeater and Flexible Content fields are not auto-inline-editable.
- Fields that return arrays (for example image fields returning arrays) are often not auto-inline-editable without manual handling.

When you need full control, keep auto inline editing disabled and use ACF's manual inline editing helper attributes in your render template.
