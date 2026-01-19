# Installation

## Requirements

To use **Timber ACF WP Blocks** you will need:

- [Advanced Custom Fields Pro 6.6](https://www.advancedcustomfields.com) or newer (for API v3 support)
- [Timber](https://github.com/timber/timber)
- WordPress 6.3 or newer (for Block API v3)

> **Note**: ACF PRO 5.8+ will still work with legacy flat file structure, but ACF PRO 6.6+ is recommended for block.json support.

## Installation

Run the following in your Timber-based theme directory

```sh
composer require "palmiak/timber-acf-wp-blocks"
```

or if want to install it as a Plugin run:

```sh
composer require "palmiak/timber-acf-wp-blocks-plugin"
```

## Block Directory Structure

### Modern Structure (Recommended)

Create blocks in subfolders for automatic `block.json` generation:

```
views/blocks/
├── my-block/
│   └── my-block.twig
├── another-block/
│   └── another-block.twig
```

### Legacy Structure (Deprecated)

Flat file structure still works but will show deprecation warnings:

```
views/blocks/
├── my-block.twig
├── another-block.twig
```

See [Block.json Support](block-json.md) for migration guide.

You can also change your blocks directory with a [filter](filters.md).

> **Note**: filenames should only contain lowercase alphanumeric characters and dashes, and must begin with a letter.

When you have your blocks ready the only thing left it to create a New group in ACF and select your block in **Show this field group if** selector.
