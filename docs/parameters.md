# Block Parameters

When creating blocks, you can include any of the following parameters.

```twig
{#
 Title: (required)
 Description:
 Category: (required)
 Icon:
 Keywords: (space-separated)
 PostTypes: (space-separated)
 Mode:
 Align:
 EnqueueStyle:
 EnqueueScript:
 EnqueueAssets: (function callback)
 SupportsAlign: (space-separated)
 SupportsAlignContent: (true|matrix)
 SupportsAnchor: (true|false)
 SupportsCustomClassName: (true|false)
 SupportsMode: (true|false)
 SupportsMultiple: (true|false)
 SupportsReusable: (true|false)
 SupportsFullHeight: (true:false)
 SupportsJSX: (true|false)
 Example: (JSON format)
 ExampleImage: (path or URL)
 Parent: (space-separated)
 Ancestor: (space-separated)
 UsesContext: (space-separated)
 ProvidesContext: (JSON format)
 DefaultData:
#}
```

## Block Hierarchy

### Parent

Restricts the block so it can only be inserted as a **direct child** of the specified parent blocks.

```twig
{# Parent: acf/card-grid acf/accordion #}
```

### Ancestor

Like Parent, but allows the block to be nested **anywhere inside** the specified ancestor blocks (not just as a direct child).

```twig
{# Ancestor: acf/section acf/container #}
```

## Block Context

Block context allows parent blocks to share data with their children without using global state.

### UsesContext

Specifies which context values this block consumes from its ancestors.

```twig
{# UsesContext: postId postType myPlugin/recordId #}
```

The context values are then available in your Twig template via `block_context`:

```twig
{% set record_id = block_context['myPlugin/recordId'] %}
```

### ProvidesContext

Specifies context values this block provides to its descendants. Uses JSON format mapping context names to ACF field names.

```twig
{# ProvidesContext: {"myPlugin/recordId": "record_id", "myPlugin/cardIndex": "index"} #}
```

This makes the `record_id` field value available to all child blocks as `myPlugin/recordId`.
