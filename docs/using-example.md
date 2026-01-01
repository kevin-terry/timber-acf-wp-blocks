# Using examples

From ACF 5.8.11 you can use **Example** to set example data for block previews. You can read more about this:

- [https://developer.wordpress.org/block-editor/developers/block-api/block-registration/](https://developer.wordpress.org/block-editor/developers/block-api/block-registration/)

To do this in **Timber ACF Blocks** you need to create your block with a set of example parameters in JSON format.
In `testimonial.twig`:

```twig
{#
  Title: Testimonial
  Description: Customer testimonial
  Category: formatting
  Icon: admin-comments
  Keywords: testimonial quote "customer testimonial"
  Mode: edit
  Example: { "testimonial": "Testimonials", "author": "John Doe" }
#}

<blockquote>
    <p>{{ fields.testimonial }}</p>
    <cite>
      <span>{{ fields.author }}</span>
    </cite>
</blockquote>

```

When showing an **example** we only pass the fields and their values as a valid JSON.

## Using an image as preview

The simplest way to add a preview is to use the **ExampleImage** parameter, which displays a static image in the block inserter. This is ideal for quick mockups or screenshots of what the block looks like.

In `testimonial.twig`:

```twig
{#
  Title: Testimonial
  Description: Customer testimonial
  Category: formatting
  Icon: admin-comments
  Keywords: testimonial quote "customer testimonial"
  Mode: edit
  ExampleImage: assets/block-previews/testimonial.png
#}

<blockquote>
    <p>{{ fields.testimonial }}</p>
    <cite>
      <span>{{ fields.author }}</span>
    </cite>
</blockquote>

```

The **ExampleImage** parameter accepts:

- **Relative paths**: `assets/block-previews/testimonial.png` (theme directory is prepended automatically)
- **Absolute URLs**: `https://example.com/img/testimonial-preview.jpg`

If both **ExampleImage** and **Example** JSON are set, the image will take precedence.

## Using static HTML as preview

There are cases when we would like to use a static HTML as example instead of a generated example. To do this create a file called `{slug}-example.twig`. So if your block is called `testimonial.twig` than the example would be called `testimonial-example.twig`. The suffix for this **example** file can be changed using a [filter](filters.md)

In `testimonial-example.twig`:

```twig
<blockquote>
    <p>Testimonial</p>
    <cite>
      <span>John Doe</span>
    </cite>
</blockquote>

```
