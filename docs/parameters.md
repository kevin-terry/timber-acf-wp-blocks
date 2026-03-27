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
 SupportsFullHeight: (true|false)
 SupportsHtml: (true|false)
 SupportsInserter: (true|false)
 SupportsLock: (true|false)
 SupportsJSX: (true|false)
 HideSidebarFields: (true|false)
 AutoInlineEditing: (true|false)
 InlineEditableFields: (space-separated field names)
 Example: (JSON format)
 ExampleImage: (path or URL)
 Parent: (space-separated)
 Ancestor: (space-separated)
 UsesContext: (space-separated)
 ProvidesContext: (JSON format)
 DefaultData:
#}
```

## Additional Support Flags

### InlineEditableFields

Restricts which fields are allowed to keep ACF's inline-editing placeholder tokens during preview rendering.

```twig
{# InlineEditableFields: heading body #}
```

Use this together with `AutoInlineEditing: true` when your template includes non-text fields in URLs, attributes, class names, or other derived values and you want to avoid placeholder-token leaks.

If omitted, the package defaults to preserving placeholders only for ACF `text` and `textarea` fields.

### SupportsHtml

Controls whether the block can be edited as raw HTML in the editor.

### SupportsInserter

Controls whether the block appears in the inserter UI.

### SupportsLock

Controls whether users can change the block lock setting from the editor UI.

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
