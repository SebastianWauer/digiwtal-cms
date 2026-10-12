<?php
declare(strict_types=1);

namespace App\Services;

final class SvgSanitizer
{
    private const SVG_NS = 'http://www.w3.org/2000/svg';
    private const MAX_BYTES = 10485760;
    private const ELEMENTS = ['svg','g','defs','symbol','use','path','rect','circle','ellipse','line','polyline','polygon','text','tspan','textPath','title','desc','image','style','clipPath','mask','pattern','marker','linearGradient','radialGradient','stop','filter','feBlend','feColorMatrix','feComponentTransfer','feComposite','feConvolveMatrix','feDiffuseLighting','feDisplacementMap','feDistantLight','feDropShadow','feFlood','feFuncA','feFuncB','feFuncG','feFuncR','feGaussianBlur','feImage','feMerge','feMergeNode','feMorphology','feOffset','fePointLight','feSpecularLighting','feSpotLight','feTile','feTurbulence'];

    public static function sanitize(string $svg, int $depth = 0): string
    {
        if ($depth > 3 || strlen($svg) > self::MAX_BYTES || preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $svg)) {
            throw new \RuntimeException('SVG ist zu gross oder enthaelt unzulässige XML-Definitionen.');
        }
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        try {
            if (!$document->loadXML($svg, LIBXML_NONET) || !$document->documentElement
                || $document->documentElement->localName !== 'svg'
                || $document->documentElement->namespaceURI !== self::SVG_NS) {
                throw new \RuntimeException('SVG ungueltig.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        self::cleanElement($document->documentElement, $depth);
        return (string)$document->saveXML($document->documentElement);
    }

    private static function cleanElement(\DOMElement $element, int $depth): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->localName);
            $value = trim($attribute->value);
            if ($attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') continue;
            if (str_starts_with($name, 'on') || ($attribute->namespaceURI !== null
                && !in_array($attribute->namespaceURI, ['http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace'], true))
                || ($attribute->namespaceURI === 'http://www.w3.org/XML/1998/namespace' && $name === 'base')) {
                $element->removeAttributeNode($attribute);
                continue;
            }
            if ($name === 'href') {
                $image = in_array($element->localName, ['image', 'feImage'], true);
                $safe = $image ? self::safeImage($value, $depth) : (preg_match('/^#[a-zA-Z_][a-zA-Z0-9_.:-]*$/D', $value) ? $value : null);
                if ($safe === null) $element->removeAttributeNode($attribute);
                else $attribute->value = $safe;
            } elseif (!self::safeStyle($value)) {
                $element->removeAttributeNode($attribute);
            }
        }
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                if ($child->namespaceURI !== self::SVG_NS || !in_array($child->localName, self::ELEMENTS, true)) {
                    $element->removeChild($child);
                    continue;
                }
                if ($child->localName === 'style' && !self::safeStyle($child->textContent)) {
                    $element->removeChild($child);
                    continue;
                }
                self::cleanElement($child, $depth);
            } elseif ($child instanceof \DOMProcessingInstruction) {
                $element->removeChild($child);
            }
        }
        if (in_array($element->localName, ['image', 'feImage', 'use'], true)
            && !$element->hasAttribute('href') && !$element->hasAttributeNS('http://www.w3.org/1999/xlink', 'href')) {
            $element->parentNode?->removeChild($element);
        }
    }

    private static function safeStyle(string $value): bool
    {
        // Keine CSS-Escapes, Imports oder Quellen ausser lokalen SVG-Definitionen.
        if (preg_match('/[\\\\@]|javascript\s*:|expression\s*\(|behavior\s*:|-moz-binding/i', $value)) return false;
        $withoutLocalUrls = preg_replace('/url\(\s*([\'"]?)#[a-zA-Z_][a-zA-Z0-9_.:-]*\1\s*\)/i', '', $value);
        return !preg_match('/\burl\b/i', $withoutLocalUrls ?? $value);
    }

    private static function safeImage(string $value, int $depth): ?string
    {
        if (!preg_match('#^data:(image/(?:png|jpeg|webp|svg\+xml));base64,([a-zA-Z0-9+/=\s]+)$#iD', $value, $match)) return null;
        $bytes = base64_decode(preg_replace('/\s+/', '', $match[2]), true);
        if (!is_string($bytes) || strlen($bytes) > self::MAX_BYTES) return null;
        $mime = strtolower($match[1]);
        if ($mime === 'image/svg+xml') {
            try { $bytes = self::sanitize($bytes, $depth + 1); }
            catch (\RuntimeException) { return null; }
        } else {
            $size = @getimagesizefromstring($bytes);
            if (!$size || ($size['mime'] ?? '') !== $mime) return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
