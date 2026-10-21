<?php

namespace App\Features\LacaSeo\Schema\Pro;

use App\Features\LacaSeo\Schema\JsonLd;
use App\Features\LacaSeo\Schema\Snippet;

defined('ABSPATH') || exit;

class BlockParser implements Snippet
{
    public function process(array $data, JsonLd $jsonld): array
    {
        if (!is_singular()) {
            return $data;
        }

        global $post;
        if (!$post instanceof \WP_Post || empty($post->post_content)) {
            return $data;
        }

        if (!has_blocks($post->post_content)) {
            return $data;
        }

        $blocks = parse_blocks($post->post_content);
        return $this->parseBlocks($blocks, $data);
    }

    private function parseBlocks(array $blocks, array $data): array
    {
        foreach ($blocks as $block) {
            $blockName = $block['blockName'] ?? '';

            if (in_array($blockName, ['rank-math/faq-block', 'lacadev/seo-faq-block'], true)) {
                $data = $this->addFaqGraph($data, $block);
            } elseif (in_array($blockName, ['rank-math/howto-block', 'lacadev/seo-howto-block'], true)) {
                $data = $this->addHowToGraph($data, $block);
            } elseif (in_array($blockName, ['rank-math/toc-block', 'lacadev/seo-toc-block'], true)) {
                $data = $this->addTocGraph($data, $block);
            }

            if (!empty($block['innerBlocks'])) {
                $data = $this->parseBlocks($block['innerBlocks'], $data);
            }
        }

        return $data;
    }

    private function addFaqGraph(array $data, array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        $questions = $attrs['questions'] ?? [];
        if (empty($questions) || !is_array($questions)) {
            return $data;
        }

        if (!isset($data['faqs'])) {
            $data['faqs'] = [
                '@type'      => 'FAQPage',
                'mainEntity' => [],
            ];
        }

        $permalink = get_permalink() . '#';
        foreach ($questions as $q) {
            if (empty($q['title']) || empty($q['content']) || (isset($q['visible']) && !$q['visible'])) {
                continue;
            }

            $id = !empty($q['id']) ? $q['id'] : 'faq-' . md5($q['title']);
            $data['faqs']['mainEntity'][] = [
                '@type'          => 'Question',
                'url'            => esc_url($permalink . $id),
                'name'           => wp_strip_all_tags($q['title']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($q['content']),
                ],
            ];
        }

        return $data;
    }

    private function addHowToGraph(array $data, array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        $steps = $attrs['steps'] ?? [];
        if (empty($steps) || !is_array($steps)) {
            return $data;
        }

        if (!isset($data['howto'])) {
            $data['howto'] = [
                '@type'       => 'HowTo',
                'name'        => get_the_title(),
                'description' => !empty($attrs['description']) ? wp_strip_all_tags($attrs['description']) : '',
                'totalTime'   => $attrs['totalTime'] ?? '',
                'step'        => [],
            ];
        }

        $permalink = get_permalink() . '#';
        $pos = 1;
        foreach ($steps as $s) {
            if (empty($s['title']) || empty($s['content']) || (isset($s['visible']) && !$s['visible'])) {
                continue;
            }

            $id = !empty($s['id']) ? $s['id'] : 'step-' . md5($s['title']);
            $data['howto']['step'][] = [
                '@type'    => 'HowToStep',
                'url'      => esc_url($permalink . $id),
                'name'     => wp_strip_all_tags($s['title']),
                'position' => $pos++,
                'itemListElement' => [
                    [
                        '@type' => 'HowToDirection',
                        'text'  => wp_strip_all_tags($s['content']),
                    ]
                ],
            ];
        }

        return $data;
    }

    private function addTocGraph(array $data, array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        $headings = $attrs['headings'] ?? [];
        if (empty($headings) || !is_array($headings)) {
            return $data;
        }

        if (!isset($data['toc'])) {
            $data['toc'] = [];
        }

        foreach ($headings as $h) {
            if (!empty($h['disable']) || empty($h['content'])) {
                continue;
            }

            $data['toc'][] = [
                '@context' => 'https://schema.org',
                '@type'    => 'SiteNavigationElement',
                '@id'      => '#rank-math-toc',
                'name'     => esc_html($h['content']),
                'url'      => esc_url(get_permalink() . ($h['link'] ?? '')),
            ];
        }

        if (empty($data['toc'])) {
            unset($data['toc']);
        }

        return $data;
    }
}
