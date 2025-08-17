# Cloudflare Images Plugin for Grav CMS

The **Cloudflare Images** plugin provides intelligent image handling with Cloudflare's image transformation service, automatically falling back to Grav's native Media handling on local development environments.

## Features

- **Automatic CDN Optimization**: Leverages Cloudflare's global CDN for optimized image delivery
- **Smart Environment Detection**: Automatically uses Grav's Media handling on localhost
- **Complete HTML Generation**: Generate full `<img>` and `<picture>` tags with proper attributes
- **Responsive Images**: Built-in support for srcset and responsive image generation
- **Format Conversion**: Automatic WebP/AVIF delivery to supported browsers
- **On-the-fly Transformations**: No need to pre-generate multiple image sizes
- **Backward Compatible**: Works with existing Grav Media objects

## Installation

### GPM Installation (Preferred)

```bash
bin/gpm install cloudflare-images
```

### Manual Installation

1. Download the latest release from [GitHub](https://github.com/trilbymedia/grav-plugin-cloudflare-images)
2. Unzip the archive into `/your-site/user/plugins/cloudflare-images`
3. Run `composer install` in the plugin directory

## Configuration

Before using this plugin, you need to configure it. You can do this via the Admin Panel or by copying the `cloudflare-images.yaml` file to your `user/config/plugins/` folder and modifying it:

```yaml
enabled: true
cloudflare_domains:                 # List of domains with Cloudflare enabled
  - testing.dev
  - production-site.com
default_quality: 85                 # Default image quality (1-100)
default_format: auto                # Default format: auto|webp|avif|json
default_fit: scale-down             # Default fit: scale-down|contain|cover|crop|pad
local_domains:                      # Additional domains to treat as local
  - localhost
  - 127.0.0.1
force_cloudflare: false             # Force Cloudflare even on local (for testing)
```

### Simple Domain Detection

The plugin automatically detects if the current domain is in the `cloudflare_domains` list and enables Cloudflare transformations accordingly. No complex mapping needed - Grav's environment system handles the domain-to-environment relationship automatically.

### Multi-Environment Support

Perfect for sites that need to work across multiple environments:

```yaml
cloudflare_domains:
  - testing.dev
  - production-site.com
```

The plugin will automatically enable Cloudflare transformations when the current domain exactly matches any domain in the list, and fall back to Grav's Media handling otherwise.

## Usage

### Basic Image URL

Get a transformed image URL:

```twig
{{ cf_image('hero.jpg', {width: 800, quality: 90}) }}
```

### Complete Image Tag

Generate a complete `<img>` tag with transformations and HTML attributes:

```twig
{{ cf_img_tag('villa.jpg', {
    width: 800,
    height: 600,
    quality: 85,
    class: 'rounded-lg shadow-xl',
    loading: 'lazy',
    alt: 'Beautiful Villa'
}) }}
```

Output:
```html
<img src="https://yourdomain.com/cdn-cgi/image/w=800,h=600,q=85,f=auto,fit=scale-down/..." 
     class="rounded-lg shadow-xl" 
     loading="lazy" 
     alt="Beautiful Villa" />
```

### Responsive Images

Generate responsive images with automatic srcset:

```twig
{{ cf_img_tag('hero.jpg', {
    sizes: [640, 768, 1024, 1536],
    quality: 90,
    class: 'w-full h-auto',
    loading: 'lazy',
    alt: 'Hero image'
}) }}
```

### Picture Element

Create a `<picture>` element with art direction:

```twig
{{ cf_picture_tag('hero.jpg', [
    {
        media: '(min-width: 1024px)',
        sizes: [1024, 1536, 2048],
        options: {quality: 90, format: 'webp'}
    },
    {
        media: '(min-width: 768px)',
        sizes: [768, 1024],
        options: {quality: 85}
    }
], {
    width: 640,
    class: 'hero-image',
    loading: 'lazy',
    alt: 'Responsive hero'
}) }}
```

### Working with Media Objects

The plugin works seamlessly with Grav's Media objects:

```twig
{{ cf_img_tag(page.media['hero.jpg'], {
    width: 1200,
    height: 600,
    fit: 'cover',
    class: 'page-hero',
    loading: 'eager'
}) }}
```

### Theme Streams

Support for theme:// streams:

```twig
{{ cf_image('theme://images/logo.png', {width: 200}) }}
```

## Transformation Options

### Cloudflare/Grav Transformations
- `width` - Image width in pixels
- `height` - Image height in pixels
- `quality` - Quality (1-100)
- `format` - Output format (auto, webp, avif, json)
- `fit` - How to fit (scale-down, contain, cover, crop, pad)
- `dpr` - Device pixel ratio
- `sharpen` - Sharpen amount
- `blur` - Blur radius
- `brightness` - Brightness adjustment (-100 to 100)
- `contrast` - Contrast adjustment (-100 to 100)
- `gamma` - Gamma correction (0.01 to 9.99)
- `rotate` - Rotation angle (0, 90, 180, 270, 360)

### HTML Attributes
All standard HTML attributes are preserved:
- `alt`, `title`, `class`, `id`, `style`
- `loading`, `decoding`, `fetchpriority`
- `crossorigin`, `referrerpolicy`
- `data-*` attributes
- `aria-*` attributes

## Environment Detection

### Automatic Domain Detection

The plugin uses a simple approach:

1. **Gets Current Domain**: Retrieves the domain from the current request via `Grav::instance()['uri']->host()`
2. **Checks Domain List**: Compares against the `cloudflare_domains` array using exact match
3. **Enable/Disable**: If domain is found in the list, use Cloudflare; otherwise use Grav Media

### Local Environment Detection

The plugin automatically detects local development environments:
- Hosts: `localhost`, `127.0.0.1`, `::1`
- Domains ending in: `.local`, `.test`, `.dev`
- Custom domains configured in `local_domains`

When a local environment is detected, the plugin uses Grav's native Media handling unless `force_cloudflare` is enabled.

### Integration with Grav's Environment System

Since Grav's environments are based on domains, this plugin leverages that by simply checking if the current domain is in the Cloudflare domains list. No need for complex environment mapping - if your domain is configured for Cloudflare, it will be used automatically.

## Examples

### Hero Image
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

### Gallery with Lightbox
```twig
{% for image in page.media.images %}
    {{ cf_img_tag(image, {
        width: 400,
        height: 300,
        fit: 'cover',
        quality: 80,
        class: 'gallery-thumb cursor-pointer',
        loading: 'lazy',
        'data-lightbox': 'gallery',
        alt: loop.index
    }) }}
{% endfor %}
```

### User Avatar
```twig
{{ cf_img_tag(user.avatar ?? 'theme://images/default-avatar.png', {
    width: 128,
    height: 128,
    fit: 'cover',
    class: 'rounded-full',
    alt: user.fullname ~ ' avatar'
}) }}
```

## Requirements

- Grav CMS v1.7.0 or higher
- PHP 7.3.6 or higher
- Cloudflare account with your domain proxied through Cloudflare
- Image Transformation enabled in Cloudflare dashboard - https://developers.cloudflare.com/images/get-started/#enable-transformations-on-your-zone

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For bugs and feature requests, please [open an issue](https://github.com/trilbymedia/grav-plugin-cloudflare-images/issues) on GitHub.