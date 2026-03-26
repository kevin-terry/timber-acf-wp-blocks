# Installation

## Requirements

To use **Timber ACF WP Blocks** you will need:

- [Advanced Custom Fields Pro 6.6](https://www.advancedcustomfields.com) or newer (for API v3 support)
- [Timber](https://github.com/timber/timber)
- WordPress 6.3 or newer (for Block API v3)

> **Note**: ACF PRO 5.8+ will still work with legacy flat file structure, but ACF PRO 6.6+ is recommended for block.json support.

## Composer

This repository is an independently maintained fork with additional fixes and features that are not present in the original upstream package.

The current Composer package name is still `palmiak/timber-acf-wp-blocks`, so you must add this fork as a VCS repository before requiring it:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/kevin-terry/timber-acf-wp-blocks"
    }
  ],
  "require": {
    "palmiak/timber-acf-wp-blocks": "dev-master"
  }
}
```

Or, after adding the vcs repository entry, you can run the following in your Timber-based project:

```sh
composer require palmiak/timber-acf-wp-blocks:dev-master
```

> **Warning**: If you do not add the VCS repository entry for `https://github.com/kevin-terry/timber-acf-wp-blocks`, Composer may resolve the original upstream package instead of this fork.

## Block Directory Structure

### Modern Structure (Recommended)

Create blocks in subfolders for automatic `block.json` generation:

```txt
views/blocks/
├── my-block/
│   └── my-block.twig
├── another-block/
│   └── another-block.twig
```

### Legacy Structure (Deprecated)

Flat file structure still works but will show deprecation warnings:

```txt
views/blocks/
├── my-block.twig
├── another-block.twig
```

See [Block.json Support](block-json.md) for migration guide.

You can also change your blocks directory with a [filter](filters.md).

> **Note**: filenames should only contain lowercase alphanumeric characters and dashes, and must begin with a letter.

When you have your blocks ready the only thing left it to create a New group in ACF and select your block in **Show this field group if** selector.
