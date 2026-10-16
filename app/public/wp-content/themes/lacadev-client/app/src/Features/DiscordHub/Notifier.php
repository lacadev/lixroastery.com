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
     */
    public static function send(string $channel, string $event, Embed $embed, ?string $dedupeKey = null): void
    {
        if (!Settings::canSend($channel)) {
            return;
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

        if (Queue::push($channel, $data, $dedupeKey)) {
            Dispatcher::markDirty();
        }
    }
}
