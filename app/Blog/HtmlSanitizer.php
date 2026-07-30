<?php

namespace App\Blog;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u',
        'a', 'ul', 'ol', 'li', 'blockquote', 'hr', 'figure', 'figcaption', 'img',
    ];

    public function sanitize(?string $html): string
    {
        if (! filled($html)) {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="post-content">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('post-content');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        return collect(iterator_to_array($root->childNodes))
            ->map(fn (DOMNode $node): string => $document->saveHTML($node) ?: '')
            ->implode('');
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                    $node->parentNode?->removeChild($node);

                    continue;
                }

                while ($node->firstChild !== null) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);

                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $allowed = match ($tag) {
                    'a' => in_array($name, ['href', 'title'], true),
                    'img' => in_array($name, ['src', 'alt', 'title', 'width', 'height'], true),
                    default => false,
                };
                if (! $allowed || str_starts_with($name, 'on')) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (! preg_match('/^(https?:|mailto:|tel:|\/|#)/i', $href)) {
                    $node->removeAttribute('href');
                } else {
                    $node->setAttribute('rel', 'noopener noreferrer nofollow');
                }
            }
            if ($tag === 'img' && ! preg_match('/^(https?:|\/)/i', trim($node->getAttribute('src')))) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            $this->cleanChildren($node);
        }
    }
}
