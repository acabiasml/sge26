<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;

class OfficialDocumentContent
{
    public function sanitize(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $imageBytes = 0;
        $render = function (DOMNode $node) use (&$render, &$imageBytes): string {
            if ($node->nodeType === XML_TEXT_NODE) {
                return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            if (! $node instanceof DOMElement) {
                return '';
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'], true)) {
                return '';
            }
            $children = '';
            foreach ($node->childNodes as $child) {
                $children .= $render($child);
            }
            if (! in_array($tag, ['p', 'div', 'br', 'span', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'img', 'font', 'a', 's', 'strike', 'sub', 'sup', 'hr'], true)) {
                return $children;
            }
            $attributes = '';
            $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (preg_match('/^(?:https?:\/\/[^\s<>]+|mailto:[^\s<>]+|tel:[+0-9() .-]+)$/iD', $href)) {
                    $attributes .= ' href="'.$escape($href).'"';
                    if ($node->getAttribute('target') === '_blank') {
                        $attributes .= ' target="_blank" rel="noopener noreferrer"';
                    }
                }
            }
            if ($tag === 'font') {
                $tag = 'span';
                $node->setAttribute('style', 'color: '.$node->getAttribute('color').';'.$node->getAttribute('style'));
            }
            if ($tag === 'img') {
                $src = $node->getAttribute('src');
                if (! preg_match('#^data:image/(png|jpeg);base64,([a-zA-Z0-9+/=]+)$#D', $src, $match)) {
                    $this->invalidImage();
                }
                $bytes = base64_decode($match[2], true);
                $size = $bytes !== false && strlen($bytes) <= 1048576 ? @getimagesizefromstring($bytes) : false;
                $imageBytes += strlen($bytes ?: '');
                if (! $size || $size['mime'] !== 'image/'.$match[1] || $size[0] > 4096 || $size[1] > 4096 || $size[0] * $size[1] > 8388608 || $imageBytes > 4194304) {
                    $this->invalidImage();
                }
                $attributes .= ' src="'.$escape($src).'" alt="'.$escape(mb_substr($node->getAttribute('alt'), 0, 500)).'"';
            }
            if (in_array($tag, ['td', 'th'], true)) {
                foreach (['rowspan', 'colspan'] as $attribute) {
                    $value = $node->getAttribute($attribute);
                    if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 100) {
                        $attributes .= ' '.$attribute.'="'.(int) $value.'"';
                    }
                }
            }
            $style = $this->sanitizeStyle(' style="'.$escape($node->getAttribute('style')).'" align="'.$escape($node->getAttribute('align')).'"');
            if ($style !== '') {
                $attributes .= ' style="'.$escape($style).'"';
            }
            return '<'.$tag.$attributes.'>'.(in_array($tag, ['br', 'img', 'hr'], true) ? '' : $children.'</'.$tag.'>');
        };
        return trim($render($document->getElementsByTagName('body')->item(0)));
    }

    private function invalidImage(): never
    {
        throw ValidationException::withMessages(['content_html' => __('Use imagens PNG ou JPEG de até 1 MB e 4096 pixels por lado. O documento aceita até 4 MB de imagens.')]);
    }

    private function sanitizeStyle(string $attributes): string
    {
        $alignment = '';
        if (preg_match('/\salign\s*=\s*(?:["\'](left|center|right|justify)["\']|(left|center|right|justify)(?=\s|$))/i', $attributes, $align)) {
            $alignment = 'text-align: '.strtolower($align[1] ?: $align[2]);
        }

        if (! preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $attributes, $match)) {
            return $alignment;
        }

        $allowed = $alignment ? [$alignment] : [];
        $fontFamilies = ['Atkinson Hyperlegible Next', 'DejaVu Sans', 'DejaVu Serif', 'DejaVu Sans Mono'];

        $style = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            $property = strtolower(trim((string) $property));
            $value = trim((string) $value, " \t\n\r\0\x0B\"'");

            if ($property === 'font-family' && in_array($value, $fontFamilies, true)) {
                $allowed[] = 'font-family: '.$value;
            }

            if ($property === 'font-size' && preg_match('/^(?:8|9|10|11|12|14|16|18|20|24|28|32|36)(?:pt|px)$/', $value)) {
                $allowed[] = 'font-size: '.$value;
            }

            if (in_array($property, ['color', 'background-color'], true)) {
                if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iD', $value)) {
                    $allowed[] = $property.': '.$value;
                } elseif (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/iD', $value, $rgb) && max((int) $rgb[1], (int) $rgb[2], (int) $rgb[3]) <= 255) {
                    $allowed[] = $property.': '.sprintf('#%02x%02x%02x', $rgb[1], $rgb[2], $rgb[3]);
                } elseif ($property === 'background-color' && $value === 'transparent') {
                    $allowed[] = 'background-color: transparent';
                }
            }
            if ($property === 'line-height' && in_array($value, ['1', '1.0', '1.2', '1.4', '1.5', '1.6', '1.8', '2', '2.0', '3', '3.0'], true)) {
                $allowed[] = 'line-height: '.$value;
            }
            if ($property === 'text-decoration' && in_array($value, ['line-through', 'underline', 'none'], true)) {
                $allowed[] = 'text-decoration: '.$value;
            }
            if ($property === 'width'  && preg_match('/^(?:100|[1-9]?[0-9](?:\.[0-9]+)?)%$|^[1-9][0-9]{0,3}(?:\.[0-9]+)?px$/', $value)) {
                $allowed[] = 'width: '.$value;
            }
            if ($property === 'height' && $value === 'auto') {
                $allowed[] = 'height: auto';
            }
            if ($property === 'margin-left' && preg_match('/^(?:[0-9]|[1-9][0-9]|100)(?:px)$/', $value)) {
                $allowed[] = 'margin-left: '.$value;
            }
            if ($property === 'float' && in_array($value, ['left', 'right', 'none'], true)) {
                $allowed[] = 'float: '.$value;
            }
            if ($property === 'font-weight' && in_array($value, ['bold', 'normal', '700', '400'], true)) {
                $allowed[] = 'font-weight: '.$value;
            }
            if ($property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) {
                $allowed[] = 'text-align: '.$value;
            }
        }

        return implode('; ', $allowed);
    }

}
