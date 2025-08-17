<?php
namespace Grav\Plugin\CloudflareImages\Twig;

use Grav\Common\Grav;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Utils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CloudflareImagesExtension extends AbstractExtension
{
    protected $grav;
    protected $config;
    
    public function __construct()
    {
        $this->grav = Grav::instance();
        $this->config = $this->grav['config'];
    }
    
    public function getFunctions()
    {
        return [
            new TwigFunction('cf_image', [$this, 'getCloudflareImage']),
            new TwigFunction('cf_responsive', [$this, 'getResponsiveImage']),
            new TwigFunction('cf_img_tag', [$this, 'getImageTag']),
            new TwigFunction('cf_picture_tag', [$this, 'getPictureTag']),
        ];
    }
    
    /**
     * Transform image URL for Cloudflare or fallback to Grav Media
     * 
     * @param mixed $image Image path, URL, or Media object
     * @param array $options All options - will be separated into transformations and attributes
     * @return string Transformed image URL
     */
    public function getCloudflareImage($image, array $options = [])
    {
        // Separate transformation options from HTML attributes
        list($transformOptions, $htmlAttributes) = $this->separateOptions($options);
        
        // Check if Cloudflare is enabled
        $cfEnabled = $this->config->get('plugins.cloudflare-images.enabled', false);
        $forceCloudflare = $this->config->get('plugins.cloudflare-images.force_cloudflare', false);
        
        // Check if current domain is in Cloudflare domains list
        $shouldUseCloudflare = $this->shouldUseCloudflare();
        $isLocal = $this->isLocalEnvironment();
        
        // Determine if we should use Cloudflare
        // Use Cloudflare if:
        // 1. Plugin is enabled AND
        // 2. Either force_cloudflare is true OR (not local AND domain matches)
        if (!$cfEnabled || (!$forceCloudflare && ($isLocal || !$shouldUseCloudflare))) {
            return $this->getGravMediaUrl($image, $transformOptions);
        }
        
        // Get the base image URL
        $imageUrl = $this->resolveImageUrl($image);
        
        // Build Cloudflare transformation parameters
        $cfParams = $this->buildCloudflareParams($transformOptions);
        
        // Construct Cloudflare URL as relative path
        // Format: /cdn-cgi/image/{options}/{image_path}
        // The image URL needs to be relative to the root
        $imagePath = $this->makeRelativePath($imageUrl);
        
        $cfUrl = sprintf(
            '/cdn-cgi/image/%s/%s',
            $cfParams,
            $imagePath
        );
        
        return $cfUrl;
    }
    
    /**
     * Generate responsive image with srcset for different sizes
     * 
     * @param mixed $image Image path, URL, or Media object
     * @param array $sizes Array of widths for srcset
     * @param array $baseOptions Base transformation options
     * @return array Array with 'src', 'srcset', and 'sizes' attributes
     */
    public function getResponsiveImage($image, array $sizes = [640, 768, 1024, 1536], array $baseOptions = [])
    {
        // Separate transformation options from HTML attributes
        list($transformOptions, $htmlAttributes) = $this->separateOptions($baseOptions);
        
        $srcset = [];
        $defaultSrc = '';
        
        foreach ($sizes as $index => $width) {
            $options = array_merge($transformOptions, ['width' => $width]);
            $url = $this->getCloudflareImage($image, $options);
            $srcset[] = "{$url} {$width}w";
            
            // Use middle size as default src
            if ($index === floor(count($sizes) / 2)) {
                $defaultSrc = $url;
            }
        }
        
        return [
            'src' => $defaultSrc,
            'srcset' => implode(', ', $srcset),
            'sizes' => $htmlAttributes['sizes'] ?? '(max-width: 640px) 100vw, (max-width: 768px) 100vw, (max-width: 1024px) 100vw, 1024px'
        ];
    }
    
    /**
     * Generate a complete <img> tag with Cloudflare transformations and HTML attributes
     * 
     * @param mixed $image Image path, URL, or Media object
     * @param array $options All options including transformations and HTML attributes
     * @return string HTML img tag
     */
    public function getImageTag($image, array $options = [])
    {
        // Separate transformation options from HTML attributes
        list($transformOptions, $htmlAttributes) = $this->separateOptions($options);
        
        // Get the transformed image URL
        $src = $this->getCloudflareImage($image, $transformOptions);
        
        // Handle responsive images if sizes are provided
        if (isset($options['sizes']) && is_array($options['sizes'])) {
            $responsive = $this->getResponsiveImage($image, $options['sizes'], $transformOptions);
            $htmlAttributes['src'] = $responsive['src'];
            $htmlAttributes['srcset'] = $responsive['srcset'];
            if (!isset($htmlAttributes['sizes'])) {
                $htmlAttributes['sizes'] = $responsive['sizes'];
            }
        } else {
            $htmlAttributes['src'] = $src;
        }
        
        // Build the img tag
        return $this->buildHtmlTag('img', $htmlAttributes);
    }
    
    /**
     * Generate a <picture> tag with multiple sources for art direction
     * 
     * @param mixed $image Image path, URL, or Media object
     * @param array $sources Array of source configurations
     * @param array $imgOptions Options for the fallback img tag
     * @return string HTML picture tag
     */
    public function getPictureTag($image, array $sources = [], array $imgOptions = [])
    {
        $html = '<picture>';
        
        // Add source elements
        foreach ($sources as $source) {
            $media = $source['media'] ?? '';
            $sizes = $source['sizes'] ?? [640, 768, 1024, 1536];
            $options = $source['options'] ?? [];
            
            list($transformOptions, ) = $this->separateOptions($options);
            
            $srcset = [];
            foreach ($sizes as $width) {
                $opts = array_merge($transformOptions, ['width' => $width]);
                $url = $this->getCloudflareImage($image, $opts);
                $srcset[] = "{$url} {$width}w";
            }
            
            $sourceAttrs = [
                'srcset' => implode(', ', $srcset),
                'media' => $media,
                'type' => $source['type'] ?? null,
            ];
            
            $html .= $this->buildHtmlTag('source', array_filter($sourceAttrs));
        }
        
        // Add fallback img tag
        $html .= $this->getImageTag($image, $imgOptions);
        $html .= '</picture>';
        
        return $html;
    }
    
    /**
     * Separate transformation options from HTML attributes
     * 
     * @param array $options
     * @return array [transformOptions, htmlAttributes]
     */
    protected function separateOptions(array $options)
    {
        // Cloudflare transformation parameters
        $transformKeys = [
            'width', 'height', 'quality', 'format', 'fit', 'dpr',
            'sharpen', 'blur', 'brightness', 'contrast', 'gamma', 'rotate'
        ];
        
        // HTML attributes that should not be processed as transformations
        $htmlKeys = [
            'alt', 'title', 'class', 'id', 'style', 'loading', 'decoding',
            'sizes', 'data', 'aria', 'role', 'tabindex', 'crossorigin',
            'referrerpolicy', 'fetchpriority', 'elementtiming', 'importance'
        ];
        
        $transformOptions = [];
        $htmlAttributes = [];
        
        foreach ($options as $key => $value) {
            // Check if it's a transformation option
            if (in_array($key, $transformKeys)) {
                $transformOptions[$key] = $value;
            }
            // Check if it's an HTML attribute or starts with data- or aria-
            elseif (in_array($key, $htmlKeys) || 
                    strpos($key, 'data-') === 0 || 
                    strpos($key, 'aria-') === 0) {
                $htmlAttributes[$key] = $value;
            }
            // Default to transformation option for unknown keys
            else {
                $transformOptions[$key] = $value;
            }
        }
        
        return [$transformOptions, $htmlAttributes];
    }
    
    /**
     * Build an HTML tag with attributes
     * 
     * @param string $tag Tag name
     * @param array $attributes HTML attributes
     * @return string HTML tag
     */
    protected function buildHtmlTag($tag, array $attributes)
    {
        $html = "<{$tag}";
        
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            
            if ($value === true) {
                $html .= " {$key}";
            } else {
                $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $html .= " {$key}=\"{$escapedValue}\"";
            }
        }
        
        if ($tag === 'img' || $tag === 'source') {
            $html .= ' />';
        } else {
            $html .= '>';
        }
        
        return $html;
    }
    
    /**
     * Convert an absolute URL to a relative path
     * 
     * @param string $url
     * @return string
     */
    protected function makeRelativePath($url)
    {
        // If it's already a relative path, return it
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            return ltrim($url, '/');
        }
        
        // Parse the URL and extract just the path
        $parsedUrl = parse_url($url);
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        
        // Remove leading slash for Cloudflare format
        return ltrim($path, '/');
    }
    
    /**
     * Check if Cloudflare should be used for the current domain
     * 
     * @return bool
     */
    protected function shouldUseCloudflare()
    {
        $currentHost = $this->grav['uri']->host();
        $cloudflareDomains = $this->config->get('plugins.cloudflare-images.cloudflare_domains', []);
        
        // Check if any configured Cloudflare domain is contained within the current host
        // This allows trilbyhost.com to match staging.trilbyhost.com, staging.tuscany.trilbyhost.com, etc.
        foreach ($cloudflareDomains as $domain) {
            if (strpos($currentHost, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if we're in a local development environment
     * 
     * @return bool
     */
    protected function isLocalEnvironment()
    {
        $uri = $this->grav['uri'];
        $host = $uri->host();
        
        // Get configured local domains
        $localDomains = $this->config->get('plugins.cloudflare-images.local_domains', []);
        $defaultLocalHosts = ['localhost', '127.0.0.1', '::1'];
        $localHosts = array_merge($defaultLocalHosts, $localDomains);
        
        // Check if host contains .local or is in the local hosts list
        return in_array($host, $localHosts) || 
               strpos($host, '.local') !== false ||
               strpos($host, '.test') !== false ||
               strpos($host, '.dev') !== false;
    }
    
    /**
     * Resolve image to a full URL
     * 
     * @param mixed $image
     * @return string
     */
    protected function resolveImageUrl($image)
    {
        // If it's already a full URL, return it
        if (is_string($image) && (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0)) {
            return $image;
        }
        
        // If it's a Media object, get its URL
        if ($image instanceof Medium) {
            return $image->url();
        }
        
        // If it's a relative path, make it absolute
        if (is_string($image)) {
            $page = $this->grav['page'];
            
            // Check if it's a stream
            if (Utils::startsWith($image, 'theme://')) {
                $locator = $this->grav['locator'];
                $path = $locator->findResource($image, false);
                if ($path) {
                    $base_url = $this->grav['base_url_absolute'];
                    $theme_path = str_replace(GRAV_ROOT, '', $path);
                    return $base_url . $theme_path;
                }
            }
            
            // Check if it's a page media
            if ($page) {
                $media = $page->media();
                if (isset($media[$image])) {
                    return $media[$image]->url();
                }
            }
            
            // Default to base URL + image path
            $base_url = $this->grav['base_url_absolute'];
            return $base_url . '/' . ltrim($image, '/');
        }
        
        return '';
    }
    
    /**
     * Build Cloudflare transformation parameters string
     * 
     * @param array $options
     * @return string
     */
    protected function buildCloudflareParams(array $options)
    {
        $params = [];
        
        // Map options to Cloudflare parameters
        $mapping = [
            'width' => 'w',
            'height' => 'h',
            'quality' => 'q',
            'format' => 'f',
            'fit' => 'fit',
            'dpr' => 'dpr',
            'sharpen' => 'sharpen',
            'blur' => 'blur',
            'brightness' => 'brightness',
            'contrast' => 'contrast',
            'gamma' => 'gamma',
            'rotate' => 'rotate'
        ];
        
        // Set defaults from plugin configuration
        $defaults = [
            'quality' => $this->config->get('plugins.cloudflare-images.default_quality', 85),
            'format' => $this->config->get('plugins.cloudflare-images.default_format', 'auto'),
            'fit' => $this->config->get('plugins.cloudflare-images.default_fit', 'scale-down')
        ];
        
        $options = array_merge($defaults, $options);
        
        foreach ($options as $key => $value) {
            if (isset($mapping[$key]) && $value !== null && $value !== '') {
                $params[] = $mapping[$key] . '=' . $value;
            }
        }
        
        return implode(',', $params);
    }
    
    /**
     * Fallback to Grav's media handling for local development
     * 
     * @param mixed $image
     * @param array $options
     * @return string
     */
    protected function getGravMediaUrl($image, array $options)
    {
        // If it's already a URL string, return it
        if (is_string($image) && (strpos($image, 'http://') === 0 || strpos($image, 'https://') === 0)) {
            return $image;
        }
        
        // If it's a Media object, apply transformations
        if ($image instanceof Medium) {
            $width = isset($options['width']) ? $options['width'] : null;
            $height = isset($options['height']) ? $options['height'] : null;
            $fit = isset($options['fit']) ? $options['fit'] : 'scale-down';
            
            // Map Cloudflare fit modes to Grav media methods
            // Cloudflare fit options: scale-down, contain, cover, crop, pad
            // Grav methods: resize, cropResize, cropZoom, crop
            
            if ($width && $height) {
                switch ($fit) {
                    case 'cover':
                        // Cover: fills the entire area, cropping if necessary (like CSS background-size: cover)
                        $image = $image->cropZoom($width, $height);
                        break;
                    case 'crop':
                        // Crop: crops to exact dimensions from center
                        $image = $image->cropResize($width, $height);
                        break;
                    case 'pad':
                        // Pad: resize and add padding (Grav doesn't have built-in padding, so we just resize)
                        $image = $image->resize($width, $height);
                        break;
                    case 'contain':
                    case 'scale-down':
                    default:
                        // Contain/scale-down: fit within bounds without cropping
                        $image = $image->resize($width, $height);
                        break;
                }
            } elseif ($width) {
                $image = $image->resize($width);
            } elseif ($height) {
                $image = $image->resize(null, $height);
            }
            
            if (isset($options['quality'])) {
                $image = $image->quality($options['quality']);
            }
            
            return $image->url();
        }
        
        // For string paths, try to resolve to Media object
        if (is_string($image)) {
            $page = $this->grav['page'];
            
            // Handle theme:// streams
            if (Utils::startsWith($image, 'theme://')) {
                return $this->resolveImageUrl($image);
            }
            
            // Try to get from page media
            if ($page) {
                $media = $page->media();
                if (isset($media[$image])) {
                    return $this->getGravMediaUrl($media[$image], $options);
                }
            }
            
            // Return the resolved URL
            return $this->resolveImageUrl($image);
        }
        
        return '';
    }
}