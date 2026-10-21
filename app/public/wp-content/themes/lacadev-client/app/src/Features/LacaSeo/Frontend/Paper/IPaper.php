<?php

namespace App\Features\LacaSeo\Frontend\Paper;

defined('ABSPATH') || exit;

/**
 * Interface IPaper matching Rank Math Paper architecture.
 */
interface IPaper
{
    /**
     * Retrieves the SEO title.
     */
    public function title(): string;

    /**
     * Retrieves the SEO description.
     */
    public function description(): string;

    /**
     * Retrieves the robots directives array.
     *
     * @return array<string, string>
     */
    public function robots(): array;

    /**
     * Retrieves the advanced robots directives array.
     *
     * @return array<string, string|int>
     */
    public function advanced_robots(): array;

    /**
     * Retrieves the canonical parts.
     *
     * @return array{canonical?:string|false,canonical_unpaged?:string|false,canonical_override?:string|false}
     */
    public function canonical(): array;

    /**
     * Retrieves the focus keywords.
     */
    public function keywords(): string;
}
