<?php

namespace App\Features\PostAudio;

defined('ABSPATH') || exit;

/**
 * Trích xuất và làm sạch văn bản bài viết để chuẩn bị tổng hợp âm thanh (TTS).
 */
class TextExtractor
{
    /**
     * Danh sách các block không phù hợp để đọc bằng giọng nói.
     */
    private const EXCLUDED_BLOCKS = [
        'core/code',
        'core/preformatted',
        'core/table',
        'core/embed',
        'core/video',
        'core/audio',
        'core/html',
        'core/shortcode',
        'core/form',
        'core/separator',
        'core/spacer',
        'core/navigation',
        'core/search',
        'lacadev/contact-form',
        'lacadev/exit-intent-popup',
    ];

    /**
     * Trích xuất văn bản hoàn chỉnh (tiêu đề + nội dung) từ Post ID.
     */
    public function extract(int $postId): string
    {
        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return '';
        }

        $title = trim(html_entity_decode((string) get_the_title($postId), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = wp_strip_all_tags($title);

        $rawContent = (string) $post->post_content;
        $bodyText   = $this->cleanContent($rawContent);

        if ($title === '' && $bodyText === '') {
            return '';
        }

        if ($title === '') {
            return $bodyText;
        }

        if ($bodyText === '') {
            return $title;
        }

        // Đảm bảo tiêu đề kết thúc bằng dấu chấm để câu ngắt nghỉ tự nhiên khi đọc
        $punctuation = preg_match('/[.!?…]$/u', $title) ? ' ' : '. ';

        return trim($title . $punctuation . $bodyText);
    }

    /**
     * Làm sạch nội dung bài viết: lọc bỏ block không hợp lệ, tag HTML, entity, URL.
     */
    public function cleanContent(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        // 1. Nếu có blocks Gutenberg, duyệt cây block và loại bỏ block loại trừ
        if (has_blocks($content)) {
            $parsedBlocks = parse_blocks($content);
            $content      = $this->extractBlocksText($parsedBlocks);
        }

        // 2. Loại bỏ shortcode
        $content = strip_shortcodes($content);

        // 3. Loại bỏ thẻ mã code, bảng, script, style nếu còn sót lại
        $content = preg_replace('/<(pre|code|table|script|style)[^>]*>.*?<\/\1>/is', '', $content) ?? $content;

        // 4. Loại bỏ toàn bộ thẻ HTML
        $content = wp_strip_all_tags($content);

        // 5. Giải mã HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 6. Loại bỏ URL thô (tránh đọc cả đường link loằng ngoằng)
        $content = preg_replace('~https?://[^\s]+~u', '', $content) ?? $content;

        // 7. Chuẩn hoá khoảng trắng và ngắt dòng
        $content = preg_replace('/[ \t]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/(\r?\n)+/u', "\n", $content) ?? $content;

        return trim($content);
    }

    /**
     * Đệ quy duyệt cây block và trích xuất nội dung từ các block đọc được.
     *
     * @param array $blocks
     * @return string
     */
    private function extractBlocksText(array $blocks): string
    {
        $output = '';

        foreach ($blocks as $block) {
            $blockName = $block['blockName'] ?? null;

            // Bỏ qua block rỗng không có tên (khoảng trắng giữa các block)
            if ($blockName === null && trim((string) ($block['innerHTML'] ?? '')) === '') {
                continue;
            }

            // Bỏ qua các block nằm trong danh sách loại trừ
            if ($blockName && in_array($blockName, self::EXCLUDED_BLOCKS, true)) {
                continue;
            }

            // Nếu block có các block con lồng bên trong
            if (!empty($block['innerBlocks'])) {
                $output .= ' ' . $this->extractBlocksText($block['innerBlocks']);
                continue;
            }

            // Trích xuất nội dung từ innerHTML của block
            $inner = (string) ($block['innerHTML'] ?? '');
            if ($inner !== '') {
                $output .= ' ' . $inner;
            }
        }

        return $output;
    }

    /**
     * Chia nhỏ văn bản thành các đoạn (chunks) theo câu để không vượt quá giới hạn byte/ký tự của Provider.
     *
     * @param string $text Toàn bộ văn bản.
     * @param int $maxSize Giới hạn tối đa (byte đối với Google, ký tự đối với FPT).
     * @param bool $byBytes True nếu tính theo byte UTF-8 (Google TTS: 5000 byte), false nếu tính theo ký tự.
     * @return string[] Danh sách các đoạn văn bản.
     */
    public function splitChunks(string $text, int $maxSize = 4000, bool $byBytes = true): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $length = $byBytes ? strlen($text) : mb_strlen($text);
        if ($length <= $maxSize) {
            return [$text];
        }

        // Tách câu dựa trên dấu chấm, chấm than, hỏi chấm hoặc xuống dòng
        $rawSentences = preg_split('/(?<=[.!?…\n])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($rawSentences === false || empty($rawSentences)) {
            $rawSentences = [$text];
        }

        $chunks       = [];
        $currentChunk = '';

        foreach ($rawSentences as $sentence) {
            $sentenceLen = $byBytes ? strlen($sentence) : mb_strlen($sentence);

            // Nếu 1 câu đơn lẻ mà dài vượt quá $maxSize, chia tiếp theo dấu phẩy hoặc khoảng trắng
            if ($sentenceLen > $maxSize) {
                if ($currentChunk !== '') {
                    $chunks[]     = trim($currentChunk);
                    $currentChunk = '';
                }

                $subClauses = preg_split('/(?<=,)\s+|\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY);
                $subChunk   = '';

                foreach ($subClauses as $word) {
                    $testSub = $subChunk === '' ? $word : $subChunk . ' ' . $word;
                    $testLen = $byBytes ? strlen($testSub) : mb_strlen($testSub);

                    if ($testLen > $maxSize) {
                        if ($subChunk !== '') {
                            $chunks[] = trim($subChunk);
                        }
                        $subChunk = $word;
                    } else {
                        $subChunk = $testSub;
                    }
                }

                if ($subChunk !== '') {
                    $currentChunk = $subChunk;
                }
                continue;
            }

            $testChunk = $currentChunk === '' ? $sentence : $currentChunk . ' ' . $sentence;
            $testLen   = $byBytes ? strlen($testChunk) : mb_strlen($testChunk);

            if ($testLen > $maxSize) {
                if ($currentChunk !== '') {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence;
            } else {
                $currentChunk = $testChunk;
            }
        }

        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }
}

