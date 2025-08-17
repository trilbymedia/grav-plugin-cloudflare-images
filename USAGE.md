# Cloudflare Image Transformation for Grav

This theme includes a custom Twig extension that provides intelligent image handling with Cloudflare's image transformation service, automatically falling back to Grav's native Media handling on local development.

## Configuration

Add to your `user/config/themes/mytheme.yaml`:

```yaml
cloudflare:
  images:
    enabled: true                    # Enable Cloudflare transformations
    domain: 'yourdomain.com'         # Your Cloudflare-enabled domain
    default_quality: 85              # Default image quality (1-100)
    default_format: 'auto'           # Default format: auto|webp|avif|json
```

## Available Functions

### 1. `cf_image()` - Get transformed image URL only

Returns just the URL with transformations applied.

```twig
{# Basic usage #}
{{ cf_image('villa-hero.jpg', {width: 800, quality: 90}) }}

{# With Media object #}
{{ cf_image(page.media['hero.jpg'], {width: 1200, height: 600, fit: 'cover'}) }}

{# Theme stream #}
{{ cf_image('theme://images/headers/mythemey.jpg', {width: 1920, format: 'webp'}) }}
```

### 2. `cf_img_tag()` - Generate complete `<img>` tag

Generates a complete HTML img tag with proper separation of transformation options and HTML attributes.

```twig
{# Basic image with class and lazy loading #}
{{ cf_img_tag('villa-hero.jpg', {
    width: 800,
    height: 600,
    quality: 85,
    class: 'rounded-lg shadow-xl',
    loading: 'lazy',
    alt: 'Beautiful Tuscan Villa'
}) }}

{# Output: #}
{# <img src="https://yourdomain.com/cdn-cgi/image/w=800,h=600,q=85,f=auto,fit=scale-down/..." 
       class="rounded-lg shadow-xl" 
       loading="lazy" 
       alt="Beautiful Tuscan Villa" /> #}

{# With responsive sizes #}
{{ cf_img_tag('villa-hero.jpg', {
    sizes: [640, 768, 1024, 1536],
    quality: 90,
    class: 'w-full h-auto',
    loading: 'lazy',
    alt: 'Villa exterior',
    'data-gallery': 'villa'
}) }}

{# With custom data attributes #}
{{ cf_img_tag(page.media['hero.jpg'], {
    width: 1200,
    class: 'hero-image',
    id: 'main-hero',
    'data-aos': 'fade-in',
    'data-aos-duration': '1000',
    'aria-label': 'Main villa image',
    fetchpriority: 'high'
}) }}
```

### 3. `cf_responsive()` - Get responsive image data

Returns an array with src, srcset, and sizes for manual img tag construction.

```twig
{% set responsive = cf_responsive('villa-hero.jpg', [640, 768, 1024, 1536], {
    quality: 85,
    format: 'webp'
}) %}

<img src="{{ responsive.src }}" 
     srcset="{{ responsive.srcset }}" 
     sizes="{{ responsive.sizes }}"
     class="w-full h-auto"
     loading="lazy"
     alt="Villa">
```

### 4. `cf_picture_tag()` - Generate `<picture>` element

Creates a picture element with art direction support.

```twig
{{ cf_picture_tag('villa-hero.jpg', [
    {
        media: '(min-width: 1024px)',
        sizes: [1024, 1536, 2048],
        options: {quality: 90, format: 'webp'}
    },
    {
        media: '(min-width: 768px)',
        sizes: [768, 1024],
        options: {quality: 85, format: 'webp'}
    }
], {
    width: 640,
    quality: 80,
    class: 'w-full h-auto',
    loading: 'lazy',
    alt: 'Responsive villa image'
}) }}
```

## Supported Options

### Transformation Options (Cloudflare/Grav)
- `width` - Image width in pixels
- `height` - Image height in pixels
- `quality` - Quality (1-100)
- `format` - Output format (auto, webp, avif, json)
- `fit` - How to fit (scale-down, contain, cover, crop, pad)
- `dpr` - Device pixel ratio
- `sharpen` - Sharpen amount
- `blur` - Blur radius
- `brightness` - Brightness adjustment
- `contrast` - Contrast adjustment
- `gamma` - Gamma correction
- `rotate` - Rotation angle

### HTML Attributes (preserved for img tag)
- `alt` - Alternative text
- `title` - Title attribute
- `class` - CSS classes
- `id` - Element ID
- `style` - Inline styles
- `loading` - Loading strategy (lazy, eager, auto)
- `decoding` - Decoding hint (sync, async, auto)
- `sizes` - Responsive sizes attribute
- `crossorigin` - CORS settings
- `referrerpolicy` - Referrer policy
- `fetchpriority` - Fetch priority (high, low, auto)
- `data-*` - Any data attributes
- `aria-*` - Any ARIA attributes

## Automatic Environment Detection

The system automatically detects local development environments and uses Grav's Media handling instead of Cloudflare when:
- Host is `localhost`, `127.0.0.1`, or `::1`
- Domain ends with `.local` (like `trilby.local`)
- Domain ends with `.test` or `.dev`
- Cloudflare is not enabled in configuration

## Real-World Examples

### Hero Image with Lazy Loading
```twig
{{ cf_img_tag(page.header.hero_image ?? 'theme://images/default-hero.jpg', {
    width: 1920,
    height: 600,
    fit: 'cover',
    quality: 90,
    class: 'w-full h-[600px] object-cover',
    loading: 'eager',
    fetchpriority: 'high',
    alt: page.title
}) }}
```

### Gallery Thumbnail with Lightbox
```twig
{% for image in page.media.images %}
    {{ cf_img_tag(image, {
        width: 400,
        height: 300,
        fit: 'cover',
        quality: 80,
        class: 'gallery-thumb cursor-pointer rounded',
        loading: 'lazy',
        'data-lightbox': 'gallery',
        'data-title': image.meta.title,
        alt: image.meta.alt_text ?? 'Gallery image'
    }) }}
{% endfor %}
```

### Responsive Blog Post Image
```twig
{{ cf_img_tag(post.media['featured.jpg'], {
    sizes: [640, 768, 1024, 1536],
    quality: 85,
    class: 'post-featured-image mb-8',
    loading: post.order == 1 ? 'eager' : 'lazy',
    alt: post.title ~ ' - Featured Image',
    'data-post-id': post.id
}) }}
```

### Avatar with Fallback
```twig
{% set avatar = user.avatar ?? 'theme://images/default-avatar.png' %}
{{ cf_img_tag(avatar, {
    width: 128,
    height: 128,
    fit: 'cover',
    quality: 90,
    class: 'rounded-full border-2 border-white shadow-lg',
    loading: 'lazy',
    alt: user.fullname ~ ' avatar'
}) }}
```

## Benefits

1. **Performance**: Cloudflare CDN serves optimized images from edge locations
2. **Automatic Format Selection**: Serves WebP/AVIF to supported browsers
3. **On-the-fly Resizing**: No need to pre-generate multiple image sizes
4. **Bandwidth Savings**: Reduced image sizes without quality loss
5. **Developer Experience**: Same code works locally with Grav Media
6. **SEO Friendly**: Proper HTML attributes preserved
7. **Accessibility**: Alt text and ARIA attributes supported

## Migration from Standard Grav Media

```twig
{# Before - Standard Grav Media #}
{{ page.media['hero.jpg'].cropResize(800, 600).quality(85).html('', 'Beautiful Villa', 'rounded-lg shadow-xl') }}

{# After - Cloudflare with fallback #}
{{ cf_img_tag(page.media['hero.jpg'], {
    width: 800,
    height: 600,
    quality: 85,
    class: 'rounded-lg shadow-xl',
    alt: 'Beautiful Villa'
}) }}
```

The new syntax provides better separation of concerns and works identically in both local and production environments.