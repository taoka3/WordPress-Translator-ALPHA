<?php

namespace App\Services;

class CodePreservationService
{
    /**
     * Protect code blocks, pre tags, and scripts by replacing them with unique placeholders.
     *
     * @param string|null $html
     * @return array{text: string, blocks: array<int, string>, count: int}
     */
    public function protect(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return ['text' => '', 'blocks' => [], 'count' => 0];
        }

        $blocks = [];
        $counter = 0;

        // Combined pattern for code blocks, pre tags, Gutenberg code blocks, shortcodes, and scripts
        $patterns = [
            // Gutenberg code blocks: <!-- wp:code ... --> ... <!-- /wp:code -->
            '/<!--\s*wp:code[\s\S]*?<!--\s*\/wp:code\s*-->/i',

            // Pre with code or pre alone
            '/<pre[\s\S]*?<\/pre>/i',

            // Standalone code tags
            '/<code[\s\S]*?<\/code>/i',

            // Syntax shortcodes [code]...[/code], [crayon]...[/crayon], [php]...[/php]
            '/\[(code|crayon|syntaxhighlighter|bash|php|javascript|js|python|html|css)[^\]]*\][\s\S]*?\[\/\1\]/i',

            // Script and style blocks
            '/<script[\s\S]*?<\/script>/i',
            '/<style[\s\S]*?<\/style>/i',
        ];

        $protectedText = $html;

        foreach ($patterns as $pattern) {
            $protectedText = preg_replace_callback($pattern, function ($matches) use (&$blocks, &$counter) {
                $placeholder = "___SOURCE_CODE_BLOCK_{$counter}___";
                $blocks[$counter] = $matches[0];
                $counter++;
                return $placeholder;
            }, $protectedText);
        }

        return [
            'text' => $protectedText,
            'blocks' => $blocks,
            'count' => count($blocks),
        ];
    }

    /**
     * Restore protected placeholders back to original source code blocks.
     *
     * @param string|null $text
     * @param array<int, string> $blocks
     * @return string
     */
    public function restore(?string $text, array $blocks): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        if (empty($blocks)) {
            return $text;
        }

        // Replace placeholders with their original block content
        // Handle variations where LLM might introduce whitespace: ___SOURCE_CODE_BLOCK_0___ or ___ SOURCE_CODE_BLOCK_0 ___
        $restored = $text;
        foreach ($blocks as $index => $originalBlock) {
            $regex = '/___\s*SOURCE_CODE_BLOCK_' . $index . '\s*___/i';
            $restored = preg_replace($regex, $originalBlock, $restored);
        }

        return $restored;
    }
}
