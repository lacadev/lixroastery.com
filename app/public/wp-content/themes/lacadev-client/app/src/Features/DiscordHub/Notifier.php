<?php

namespace App\Features\DiscordHub;

/**
 * Điểm vào duy nhất cho monitor: lọc → queue → đánh dấu cần flush.
 */
final class Notifier
{
    /**
     * @param string      $event     Mã sự kiện (vd "post.transition") — để filter lọc/tuỳ biến.
     * @param string|null $dedupeKey Gộp sự kiện trùng khi đang chờ gửi.
     * @param int         $delay     Hoãn gửi (giây) — kết hợp $dedupeKey để gom nhiều lần lưu thành 1 tin.
     * @param bool        $replace   Gộp thì giữ nội dung MỚI NHẤT (vd tổng thay đổi của 1 bài sau nhiều lần lưu).
     */
    public static function send(string $channel, string $event, Embed $embed, ?string $dedupeKey = null, int $delay = 0, bool $replace = false): void
    {
        if (!Settings::canSend($channel)) {
            return;
        }

        // Hoạt động quản trị → vòng đệm để đối chiếu khi có lỗi fatal (ghi ngay, kể cả tin gửi trễ).
        if (Settings::CHANNEL_AUDIT === $channel || str_starts_with($event, 'file.editor')) {
            ActivityLog::record((string) ($embed->toArray()['title'] ?? $event), Context::actor());
        }

        /**
         * Cho phép theme/plugin sửa hoặc chặn sự kiện. Trả về false để bỏ qua.
         *
         * @param array  $embed
         * @param string $event
         * @param string $channel
         */
        $data = apply_filters('laca_discord_hub/embed', $embed->toArray(), $event, $channel);
        if (!is_array($data)) {
            return;
        }

        if (Queue::push($channel, $data, $dedupeKey, $delay, $replace)) {
            Dispatcher::markDirty();
        }
    }
}
