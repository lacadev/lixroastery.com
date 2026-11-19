<?php

namespace App\Features\FrontendChatbot;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FAQ ghim + câu hỏi gợi ý của chatbot.
 *
 * FAQ (option laca_chatbot_faq) nhập dạng văn bản cho dễ gõ:
 *   - Mỗi khối cách nhau 1 dòng trống.
 *   - Dòng đầu: câu hỏi (nhiều cách hỏi tách bằng "|").
 *   - Các dòng sau: câu trả lời chuẩn.
 * Câu khách khớp đủ nhiều từ khoá với 1 cách hỏi → trả ngay câu trả lời chuẩn,
 * không gọi AI (nhanh, không tốn quota, đúng câu admin muốn).
 *
 * Gợi ý (laca_chatbot_suggestions): mỗi dòng 1 câu, hiện thành nút khi mở chat.
 */
class ChatbotFaq
{
    /** Tỉ lệ từ khoá trùng tối thiểu để coi là cùng câu hỏi. */
    private const MATCH_RATIO = 0.7;

    /** @return array<int,array{questions:string[],answer:string}> */
    public static function entries(): array
    {
        $raw    = str_replace("\r\n", "\n", (string) get_option('laca_chatbot_faq', ''));
        $blocks = preg_split('/\n\s*\n/', trim($raw)) ?: [];
        $out    = [];
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), 'strlen'));
            if (count($lines) < 2) {
                continue;
            }
            $questions = array_values(array_filter(array_map('trim', explode('|', array_shift($lines))), 'strlen'));
            $answer    = trim(implode("\n", $lines));
            if ($questions && $answer !== '') {
                $out[] = ['questions' => $questions, 'answer' => $answer];
            }
        }
        return $out;
    }

    /** @return string[] */
    public static function suggestions(): array
    {
        $raw = str_replace("\r\n", "\n", (string) get_option('laca_chatbot_suggestions', ''));
        return array_slice(array_values(array_filter(array_map('trim', explode("\n", $raw)), 'strlen')), 0, 6);
    }

    /**
     * @param callable(string):string[] $tokenize Hàm tách từ khoá dùng chung với tìm kiếm.
     */
    public static function match(string $message, callable $tokenize): ?string
    {
        $asked = array_unique($tokenize($message));
        if (!$asked) {
            return null;
        }
        $best = null;
        $bestScore = 0.0;
        foreach (self::entries() as $entry) {
            foreach ($entry['questions'] as $q) {
                $want = array_unique($tokenize($q));
                if (!$want) {
                    continue;
                }
                // Tỉ lệ từ khoá của câu FAQ có mặt trong câu khách hỏi.
                $score = count(array_intersect($want, $asked)) / count($want);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best      = $entry['answer'];
                }
            }
        }
        return $bestScore >= self::MATCH_RATIO ? $best : null;
    }
}
