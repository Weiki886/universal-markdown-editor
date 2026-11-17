<?php
declare(strict_types=1);

namespace UniversalMarkdownEditor\Post;

final class GutenbergImporter
{
    public const MARKER_PATTERN = '/<!-- ume-preserved-block:(\d+) -->/';

    /** @return array{source: string, blocks: array<string, string>}|null */
    public function import(string $content): ?array
    {
        if (strpos($content, '<!-- wp:') === false || !function_exists('parse_blocks')) {
            return null;
        }

        $rawBlocks = $this->extractRawBlocks($content);
        if ($rawBlocks === null) {
            return null;
        }

        $blocks = parse_blocks($content);
        $namedBlockCount = count(array_filter($blocks, static function (array $block): bool {
            return is_string($block['blockName'] ?? null);
        }));
        if ($namedBlockCount !== count($rawBlocks)) {
            return null;
        }

        $parts = [];
        $preserved = [];
        $rawBlockIndex = 0;
        foreach ($blocks as $block) {
            if (!is_string($block['blockName'] ?? null) && trim((string) ($block['innerHTML'] ?? '')) === '') {
                continue;
            }

            $markdown = $this->convertBlock($block);
            if ($markdown === null) {
                $index = (string) count($preserved);
                $blockName = $block['blockName'] ?? null;
                $preserved[$index] = is_string($blockName)
                    ? $rawBlocks[$rawBlockIndex]
                    : serialize_block($block);
                $parts[] = '<!-- ume-preserved-block:' . $index . ' -->';
            } else {
                $parts[] = $markdown;
            }

            if (is_string($block['blockName'] ?? null)) {
                ++$rawBlockIndex;
            }
        }

        return ['source' => implode("\n\n", $parts), 'blocks' => $preserved];
    }

    /** @return string[]|null */
    private function extractRawBlocks(string $content): ?array
    {
        preg_match_all(
            '/<!--\s*(\/?)wp:([a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)?)\b(.*?)-->/s',
            $content,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        $stack = [];
        $start = null;
        $rawBlocks = [];

        foreach ($matches as $match) {
            $closing = $match[1][0] === '/';
            $name = $match[2][0];
            $comment = $match[0][0];
            $offset = $match[0][1];
            $end = $offset + strlen($comment);

            if ($closing) {
                if ($stack === [] || array_pop($stack) !== $name) {
                    return null;
                }

                if ($stack === []) {
                    $rawBlocks[] = substr($content, $start, $end - $start);
                    $start = null;
                }
                continue;
            }

            if ($stack === []) {
                $start = $offset;
            }

            if (preg_match('/\/\s*-->$/', $comment) === 1) {
                if ($stack === []) {
                    $rawBlocks[] = substr($content, $start, $end - $start);
                    $start = null;
                }
                continue;
            }

            $stack[] = $name;
        }

        return $stack === [] ? $rawBlocks : null;
    }

    /** @param array<string, mixed> $block */
    private function convertBlock(array $block): ?string
    {
        if (($block['innerBlocks'] ?? []) !== []) {
            return null;
        }

        $name = $block['blockName'] ?? null;
        $attributes = $block['attrs'] ?? [];
        $html = trim((string) ($block['innerHTML'] ?? ''));

        if ($name === 'core/paragraph' && $attributes === []) {
            return $this->plainTextElement($html, 'p');
        }

        if ($name === 'core/heading' && array_diff(array_keys($attributes), ['level']) === []) {
            preg_match('/^<h([1-6])(?: class="wp-block-heading")?>(.*)<\/h\1>$/s', $html, $matches);
            if (!isset($matches[1], $matches[2]) || preg_match('/<[^>]+>/', $matches[2]) === 1) {
                return null;
            }

            $level = (int) $matches[1];
            if (isset($attributes['level']) && (int) $attributes['level'] !== $level) {
                return null;
            }

            return str_repeat('#', $level) . ' ' . html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private function plainTextElement(string $html, string $tag): ?string
    {
        if (preg_match('/^<' . $tag . '>(.*)<\/' . $tag . '>$/s', $html, $matches) !== 1) {
            return null;
        }
        if (preg_match('/<[^>]+>/', $matches[1]) === 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
