<?php

namespace App\Settings\LacaTools;

/**
 * AITranslationParser Class
 * Handles bóc tách nội dung Gutenberg blocks và điều phối dịch thuật.
 */
class AITranslationParser
{
    private $handler;

    public function __construct(AITranslationHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Translates a single block (sent as JSON from Gutenberg editor).
     *
     * @param array  $block_data  Array with keys: blockName, attrs, innerHTML
     * @param string $source_lang Source language code (or 'auto')
     * @param string $target_lang Target language code
     * @return array|\WP_Error    Returns [ 'attrs' => [...], 'innerHTML' => '...' ] or WP_Error
     */
    public function translateSingleBlock(array $block_data, string $source_lang, string $target_lang)
    {
        $block_name = $block_data['blockName'] ?? '';
        $attrs      = $block_data['attrs']     ?? [];
        $innerHTML  = $block_data['innerHTML'] ?? '';

        // Build source context for AI prompt
        $source_context = $source_lang !== 'auto'
            ? "Source language: {$this->getLanguageName($source_lang)}. Block type: {$block_name}"
            : "Block type: {$block_name}";

        // Build a minimal block structure compatible with existing logic
        $block = [
            'blockName'    => $block_name,
            'attrs'        => $attrs,
            'innerHTML'    => $innerHTML,
            'innerContent' => [$innerHTML],
            'innerBlocks'  => [],
        ];

        // A. Translate known block attributes (LaCa blocks + core/image)
        $block = $this->translateBlockAttributesWithContext($block, $target_lang, $source_context);
        if (is_wp_error($block)) return $block;

        // B. Translate innerHTML for core text blocks
        if ($this->shouldTranslateInnerContent($block_name)) {
            if (!empty(trim($innerHTML))) {
                $translated_inner = $this->handler->translateText($innerHTML, $target_lang, $source_context);
                if (is_wp_error($translated_inner)) return $translated_inner;
                $block['innerHTML']    = $translated_inner;
                $block['innerContent'] = [$translated_inner];
            }
        }

        return [
            'attrs'        => $block['attrs'],
            'innerHTML'    => $block['innerHTML'],
            'innerContent' => $block['innerContent'],
        ];
    }

    /**
     * Wrapper around translateBlockAttributes that passes additional source context.
     */
    private function translateBlockAttributesWithContext(array $block, string $target_lang, string $source_context = ''): array|\WP_Error
    {
        $name  = $block['blockName'];
        $attrs = &$block['attrs'];

        $translatable_map = [
            // LaCa custom blocks
            'lacadev/slogan-block'         => ['slogan'],
            'lacadev/about-laca-block'     => ['title', 'content', 'btn_text'],
            'lacadev/service-block'        => ['title', 'description', 'buttonText'],
            'lacadev/blog-block'           => ['title', 'description', 'buttonText'],
            'lacadev/staggered-blog-block' => ['title', 'description', 'buttonText'],
            'lacadev/project-block'        => ['title', 'description', 'buttonText'],
            'lacadev/button-block'         => ['text'],
            'lacadev/statement-block'      => ['title', 'subtitle'],
            'lacadev/process-block'        => ['title', 'description', 'steps'],
            'lacadev/marquee-block'        => ['brands'],
            'lacadev/tech-list-block'      => ['technologies'],
            'lacadev/workflow-block'       => ['subTitle', 'title', 'steps'],
            
            // WordPress core blocks that store text directly in attributes
            'core/paragraph'               => ['content'],
            'core/heading'                 => ['content'],
            'core/button'                  => ['text'],
            'core/quote'                   => ['citation'],
            'core/image'                   => ['alt', 'caption'],
            'core/list-item'               => ['content'],
        ];

        if (isset($translatable_map[$name])) {
            foreach ($translatable_map[$name] as $attr_key) {
                if (isset($attrs[$attr_key]) && !empty($attrs[$attr_key])) {
                    $attrs[$attr_key] = $this->translateRecursive($attrs[$attr_key], $target_lang, $source_context . " | Attribute: {$attr_key}");
                    if (is_wp_error($attrs[$attr_key])) return $attrs[$attr_key];
                }
            }
        }

        return $block;
    }

    /**
     * Recursively translates strings inside arrays (e.g. for repeater fields).
     */
    private function translateRecursive($value, string $target_lang, string $source_context)
    {
        if (is_string($value)) {
            if (trim($value) === '') return $value;
            // Dịch chuỗi nếu không rỗng
            return $this->handler->translateText($value, $target_lang, $source_context);
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                // Skip if it's a known non-translatable key like url, id, iconId
                if (in_array(strtolower($k), ['url', 'link', 'id', 'iconid', 'iconurl', 'imageurl', 'imageid'], true)) {
                    continue;
                }
                
                $result = $this->translateRecursive($v, $target_lang, $source_context . " | Key: {$k}");
                if (is_wp_error($result)) return $result;
                
                $value[$k] = $result;
            }
            return $value;
        }

        return $value;
    }

    /**
     * Returns the human-readable name for a language code.
     */
    private function getLanguageName(string $code): string
    {
        $langs = [
            'vi' => 'Vietnamese', 'en' => 'English', 'ja' => 'Japanese',
            'ko' => 'Korean',     'fr' => 'French',   'zh' => 'Chinese',
            'de' => 'German',     'es' => 'Spanish',
        ];
        return $langs[$code] ?? $code;
    }

    /**
     * Translates a post's content, title, and excerpt.
     */
    public function translatePost($post_id, $target_lang)
    {
        $post = get_post($post_id);
        if (!$post) return new \WP_Error('invalid_post', 'Bài viết không tồn tại.');

        // 1. Translate Title & Excerpt
        $translated_title = $this->handler->translateText($post->post_title, $target_lang, 'Post Title');
        if (is_wp_error($translated_title)) return $translated_title;

        $translated_excerpt = '';
        if (!empty($post->post_excerpt)) {
            $translated_excerpt = $this->handler->translateText($post->post_excerpt, $target_lang, 'Post Excerpt');
            if (is_wp_error($translated_excerpt)) return $translated_excerpt;
        }

        // 2. Parse & Translate Blocks
        $blocks = parse_blocks($post->post_content);
        $translated_blocks = $this->translateBlocks($blocks, $target_lang);
        if (is_wp_error($translated_blocks)) return $translated_blocks;

        $translated_content = serialize_blocks($translated_blocks);

        // 3. Return results for the orchestrator to save
        return [
            'post_title'   => $translated_title,
            'post_excerpt' => $translated_excerpt,
            'post_content' => $translated_content,
        ];
    }

    /**
     * Recursive function to translate blocks.
     */
    private function translateBlocks($blocks, $target_lang)
    {
        foreach ($blocks as &$block) {
            // Skip empty blocks (often standard text or whitespace)
            if (empty($block['blockName'])) {
                if (!empty(trim($block['innerHTML']))) {
                    $translated_inner = $this->handler->translateText($block['innerHTML'], $target_lang, 'HTML Content');
                    if (is_wp_error($translated_inner)) return $translated_inner;
                    $block['innerHTML'] = $translated_inner;
                    $block['innerContent'] = [$translated_inner];
                }
                continue;
            }

            // A. Translate specific Laca Blocks attributes
            $block = $this->translateBlockAttributes($block, $target_lang);
            if (is_wp_error($block)) return $block;

            // B. Translate inner content for core blocks (Paragraph, Heading, etc.)
            if ($this->shouldTranslateInnerContent($block['blockName'])) {
                // If it has innerBlocks, the actual translatable text is inside the innerBlocks.
                // We only translate innerHTML directly if there are NO innerBlocks.
                if (empty($block['innerBlocks']) && !empty(trim($block['innerHTML']))) {
                    $translated_inner = $this->handler->translateText($block['innerHTML'], $target_lang, $block['blockName']);
                    if (is_wp_error($translated_inner)) return $translated_inner;
                    
                    $block['innerHTML'] = $translated_inner;
                    $block['innerContent'] = [$translated_inner];
                }
            }

            // C. Recurse into innerBlocks
            if (!empty($block['innerBlocks'])) {
                $translated_inner_blocks = $this->translateBlocks($block['innerBlocks'], $target_lang);
                if (is_wp_error($translated_inner_blocks)) return $translated_inner_blocks;
                $block['innerBlocks'] = $translated_inner_blocks;
            }
        }
        return $blocks;
    }

    /**
     * Translates specific attributes for known custom blocks.
     */
    private function translateBlockAttributes($block, $target_lang)
    {
        $name = $block['blockName'];
        $attrs = &$block['attrs'];

        // Define which attributes are translatable for each block
        $translatable_map = [
            'lacadev/slogan-block' => ['slogan'],
            'lacadev/about-laca-block' => ['title', 'content', 'btn_text'],
            'lacadev/service-block' => ['title', 'excerpt'],
            'lacadev/button-block' => ['text'],
            'lacadev/statement-block' => ['content'],
            'core/image' => ['alt', 'caption'],
            // Add more as needed
        ];

        if (isset($translatable_map[$name])) {
            foreach ($translatable_map[$name] as $attr_key) {
                if (!empty($attrs[$attr_key])) {
                    $translated_val = $this->handler->translateText($attrs[$attr_key], $target_lang, "Block attribute: $attr_key");
                    if (is_wp_error($translated_val)) return $translated_val;
                    $attrs[$attr_key] = $translated_val;
                }
            }
        }

        return $block;
    }

    private function shouldTranslateInnerContent($block_name)
    {
        $core_text_blocks = [
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/list-item',
            'core/quote',
            'core/table',
            'core/button',
            'core/freeform',
            'core/html',
        ];
        return in_array($block_name, $core_text_blocks);
    }
}
