<?php

namespace App\Features\LacaSeo\Schema;

defined('ABSPATH') || exit;

interface Snippet
{
    /**
     * Process schema snippet data.
     *
     * @param array $data Existing schema graph data
     * @param JsonLd $jsonld JsonLd processor instance
     * @return array
     */
    public function process(array $data, JsonLd $jsonld): array;
}
