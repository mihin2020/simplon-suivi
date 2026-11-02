<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allow-list sanitizer for HTML that comes from outside (received e-mails) or from rich-text fields
 * and is rendered with v-html. Unknown tags are unwrapped, dangerous ones removed with their content,
 * and every attribute is dropped except a vetted link target and image source.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del', 'sub', 'sup', 'code', 'pre', 'blockquote',
        'p', 'div', 'span', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    ];

    private const REMOVED_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'svg', 'math', 'noscript', 'template', 'link', 'meta', 'base', 'head',
        'title', 'audio', 'video', 'source', 'canvas', 'dialog',
    ];

    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const IMAGE_SCHEMES = ['http', 'https'];

    /** @param  bool  $allowImages  Remote images track readers: only allow them for content written on the platform. */
    public static function clean(?string $html, bool $allowImages = false): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $dom->getElementById('__root') : null;

        if (! $root instanceof DOMElement) {
            return e(strip_tags($html));
        }

        self::cleanChildren($root, $allowImages);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanChildren(DOMNode $parent, bool $allowImages): void
    {
        // Snapshot: the list changes while nodes are removed or unwrapped.
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                $parent->removeChild($node); // comments, processing instructions, CDATA

                continue;
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::REMOVED_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            if ($tag === 'img') {
                self::cleanImage($parent, $node, $allowImages);

                continue;
            }

            self::cleanChildren($node, $allowImages);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($node);

                continue;
            }

            $href = $tag === 'a' ? self::safeUrl($node->getAttribute('href'), self::LINK_SCHEMES) : null;

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->nodeName);
            }

            if ($href !== null) {
                $node->setAttribute('href', $href);
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }

    private static function cleanImage(DOMNode $parent, DOMElement $image, bool $allowImages): void
    {
        $src = $allowImages ? self::safeUrl($image->getAttribute('src'), self::IMAGE_SCHEMES) : null;

        if ($src === null) {
            $parent->removeChild($image);

            return;
        }

        $alt = $image->getAttribute('alt');

        foreach (iterator_to_array($image->attributes) as $attribute) {
            $image->removeAttribute($attribute->nodeName);
        }

        $image->setAttribute('src', $src);
        $image->setAttribute('alt', $alt);
    }

    /**
     * @param  array<int, string>  $schemes
     */
    private static function safeUrl(string $url, array $schemes): ?string
    {
        // Browsers ignore whitespace and control characters inside a scheme ("java\tscript:").
        $compact = preg_replace('/[\x00-\x20\x7f]+/', '', $url);

        if ($compact === null || ! preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $match)) {
            return null;
        }

        return in_array(strtolower($match[1]), $schemes, true) ? $compact : null;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        while ($element->firstChild !== null) {
            $parent?->insertBefore($element->firstChild, $element);
        }

        $parent?->removeChild($element);
    }
}
