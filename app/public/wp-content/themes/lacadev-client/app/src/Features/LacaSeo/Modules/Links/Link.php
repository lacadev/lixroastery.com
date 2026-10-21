<?php

namespace App\Features\LacaSeo\Modules\Links;

defined('ABSPATH') || exit;

/**
 * Link entity representing a single hyperlinked URL found in post content.
 */
class Link
{
    private string $url;
    private int $targetPostId;
    private string $type;
    private string $anchorText;
    private string $anchorType;
    private int $isNofollow;
    private int $targetBlank;
    private string $urlHash;

    public function __construct(
        string $url,
        int $targetPostId = 0,
        string $type = 'internal',
        string $anchorText = '',
        string $anchorType = 'HPLNK',
        int $isNofollow = 0,
        int $targetBlank = 0
    ) {
        $this->url          = $url;
        $this->targetPostId = $targetPostId;
        $this->type         = $type;
        $this->anchorText   = $anchorText;
        $this->anchorType   = $anchorType;
        $this->isNofollow   = $isNofollow;
        $this->targetBlank  = $targetBlank;
        $this->urlHash      = md5($url);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTargetPostId(): int
    {
        return $this->targetPostId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAnchorText(): string
    {
        return $this->anchorText;
    }

    public function getAnchorType(): string
    {
        return $this->anchorType;
    }

    public function getIsNofollow(): int
    {
        return $this->isNofollow;
    }

    public function getTargetBlank(): int
    {
        return $this->targetBlank;
    }

    public function getUrlHash(): string
    {
        return $this->urlHash;
    }
}
