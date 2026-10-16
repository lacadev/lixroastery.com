<?php

namespace App\Features\DiscordHub;

/**
 * Vòng đệm các hoạt động quản trị gần nhất (ai làm gì, lúc nào) — để cảnh báo lỗi fatal
 * kèm được "hoạt động trong 15 phút trước lỗi", trả lời ngay câu hỏi "ai vừa làm gì mà sập".
 *
 * Lưu trong 1 option không autoload, tối đa MAX mục, bỏ mục cũ hơn 1 giờ.
 */
final class ActivityLog
{
    private const OPTION = 'laca_dh_recent_activity';
    private const MAX = 40;
    private const MAX_AGE = HOUR_IN_SECONDS;

    public static function record(string $title, string $actor): void
    {
        $now = time();
        $items = self::all();
        $items[] = ['t' => $now, 'title' => mb_substr($title, 0, 150), 'actor' => mb_substr($actor, 0, 80)];
        $items = array_values(array_filter($items, static fn ($i) => $now - (int) $i['t'] <= self::MAX_AGE));
        update_option(self::OPTION, array_slice($items, -self::MAX), false);
    }

    /**
     * @return array<int,array{t:int,title:string,actor:string}> mới nhất trước
     */
    public static function since(int $seconds): array
    {
        $from = time() - $seconds;
        return array_reverse(array_values(array_filter(self::all(), static fn ($i) => (int) $i['t'] >= $from)));
    }

    private static function all(): array
    {
        $items = get_option(self::OPTION, []);
        return is_array($items) ? $items : [];
    }
}
