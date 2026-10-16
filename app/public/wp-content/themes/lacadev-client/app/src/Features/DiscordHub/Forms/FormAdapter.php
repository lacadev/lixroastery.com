<?php

namespace App\Features\DiscordHub\Forms;

/**
 * Adapter cho 1 plugin form. Chỉ cần add_action vào hook của plugin đó —
 * plugin không cài thì hook không bao giờ bắn, không cần detect.
 */
interface FormAdapter
{
    /**
     * @param callable $emit function(Lead $lead): void
     */
    public function register(callable $emit): void;
}
