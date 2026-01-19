<p align="center">
  <img src="timber-wp-acf-blocks.png">
</p>

# Timber ACF WP Blocks

Generate ACF Gutenberg blocks just by adding templates to your Timber theme. This package is based heavily on [this article](https://medium.com/nicooprat/acf-blocks-avec-gutenberg-et-sage-d8c20dab6270) by [nicoprat](https://github.com/nicooprat) and the [plugin](https://github.com/MWDelaney/sage-acf-wp-blocks) by [MWDelaney](https://github.com/MWDelaney).

### ✨ Now supports ACF Blocks API v3

Automatic `block.json` generation, modern block registration, and full compatibility with ACF PRO 6.6+. Fully backwards compatible with existing flat file structures.

## Complete documentation

[Read the complete documentation](https://palmiak.github.io/timber-acf-wp-blocks/#/)

## Contributors

This plugin is built with help of contributors:

- [Kevin Terry](https://github.com/kevinterry) — block.json generation, API v3 support
- [roylodder](https://github.com/roylodder)
- [BrentWMiller](https://github.com/BrentWMiller)
- [Marcin Krzemiński](https://github.com/marcinkrzeminski)
- [Kuba Mikita](https://github.com/Kubitomakita)
- [LandWire](https://github.com/landwire)
- [Viktor Szépe](https://github.com/szepeviktor)

## Creating blocks

Create a subfolder in `views/blocks` with a matching Twig template. The plugin auto-generates `block.json` from your template headers:

```
views/blocks/testimonial/
├── testimonial.twig
├── block.json (auto-generated)
└── example.png (optional preview image)
```

```twig
{#
  Title: Testimonial
  Description: Customer testimonial
  Category: formatting
  Icon: admin-comments
  Keywords: testimonial quote
  Mode: preview
  SupportsAlign: left right
#}

<blockquote data-{{ block.id }}>
    <p>{{ fields.testimonial }}</p>
    <cite>
      <span>{{ fields.author }}</span>
    </cite>
</blockquote>

<style type="text/css">
  [data-{{ block.id }}] {
    background: {{ fields.background_color }};
    color: {{ fields.text_color }};
  }
</style>
```

## Timber 2.0

**Timber ACF WP Blocks** is fully compatible with both **Timber 1.x** and **Timber 2.x** versions.

## How can I report security bugs?

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage, and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/7e30249d-c84c-42d1-81b7-2c9238f86638)
