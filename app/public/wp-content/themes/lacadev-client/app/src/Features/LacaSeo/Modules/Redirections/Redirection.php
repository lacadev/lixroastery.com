<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Redirection data entity (1:1 with Rank Math Redirection).
 */
class Redirection
{
    private array $data;
    private array $cache = [];
    private bool $nocache = false;
    private ?string $domain = null;
    private bool $isNew = true;

    public function __construct(array $data, bool $nocache = false)
    {
        $this->data = $data;
        $this->nocache = $nocache;
        if (!empty($data['id'])) {
            $this->isNew = false;
        }
    }

    public static function create(int $id = 0): self
    {
        $data = [
            'id'          => 0,
            'sources'     => [],
            'url_to'      => '',
            'header_code' => 301,
            'hits'        => 0,
            'status'      => 'active',
            'created'     => '',
            'updated'     => '',
        ];

        if ($id > 0 && ($object = DB::getRedirectionById($id))) {
            $object['id'] = absint($object['id']);
            unset($object['last_accessed']);
            $data = $object;
        }

        return new self($data);
    }

    public static function from(array $data): self
    {
        $sources = [];
        if (isset($data['sources'])) {
            $sources = $data['sources'];
            unset($data['sources']);
        }

        $object = new self($data);
        $object->addSources($sources);

        if (isset($data['url_to'])) {
            $object->addDestination($data['url_to']);
        }

        return $object;
    }

    public function getId(): int
    {
        return (int) ($this->data['id'] ?? 0);
    }

    public function setId(int $id): void
    {
        $this->data['id'] = $id;
    }

    public function setStatus(string $status): void
    {
        $this->data['status'] = $status;
    }

    public function setNocache(bool $nocache): void
    {
        $this->nocache = $nocache;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function hasSources(): bool
    {
        return !empty($this->data['sources']) && is_array($this->data['sources']);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function addSources(array $sources): void
    {
        foreach ($sources as $value) {
            $comparison = empty($value['comparison']) ? 'exact' : $value['comparison'];
            $ignore = !empty($value['ignore']) ? 'case' : '';
            $this->addSource($value['pattern'] ?? '', $comparison, $ignore);
        }
    }

    public function addSource(string $pattern, string $comparison, string $ignore = ''): void
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return;
        }

        // Limit length security check
        if (strlen($pattern) > 2000) {
            return;
        }

        $pattern = $this->sanitizeSource(wp_strip_all_tags($pattern, true), $comparison);
        if (!$pattern) {
            return;
        }

        if (!isset($this->data['sources'])) {
            $this->data['sources'] = [];
        }

        $this->data['sources'][] = [
            'ignore'     => $ignore,
            'pattern'    => $pattern,
            'comparison' => $comparison,
        ];
    }

    public function addDestination(string $url): void
    {
        // Strip carriage returns and newlines to prevent header injection
        $processed = str_replace(["\r", "\n"], '', trim(wp_strip_all_tags($url, true)));

        if (!empty($processed) && self::isRelative($processed)) {
            $processed = home_url($processed);
        }

        $this->data['url_to'] = $processed;
    }

    public static function isRelative(string $url): bool
    {
        return !empty($url) && $url[0] === '/';
    }

    private function sanitizeSource(string $pattern, string $comparison): string|false
    {
        if ('exact' === $comparison) {
            $pattern = $this->sanitizeSourceUrl($pattern);
            if ($pattern && false === $this->nocache) {
                $this->preRedirectionCache($pattern);
            }
            return $pattern;
        } elseif ('regex' === $comparison) {
            return $this->sanitizeSourceRegex($pattern);
        }

        return filter_var($pattern, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_BACKTICK);
    }

    private function sanitizeSourceUrl(string $url): string|false
    {
        if (empty($url) || '/' === $url) {
            return false;
        }

        if ('#' === $url[0] || '/' === $url[0]) {
            return ltrim($url, '/');
        }

        $original = $url;
        $domain   = $this->getHomeDomain();
        $url      = trailingslashit($url);
        $url      = str_replace($domain . '#', $domain . '/#', $url);
        $domain   = trailingslashit($domain);
        $search   = [
            'http://' . $domain,
            'http://www.' . $domain,
            'https://' . $domain,
            'https://www.' . $domain,
            'www.' . $domain,
        ];
        $url = str_replace($search, '', $url);
        $url = preg_replace('/^' . preg_quote($domain, '/') . '/s', '', $url);

        if (empty($url) || 0 === strpos($url, 'http://') || 0 === strpos($url, 'https://')) {
            return false;
        }

        if ('/' !== substr($original, -1)) {
            $url = untrailingslashit($url);
        }

        return urldecode(self::stripSubdirectory($url));
    }

    private function sanitizeSourceRegex(string $pattern): string|false
    {
        $pattern = preg_replace("/[\r\n\t].*?$/s", '', $pattern);
        $pattern = preg_replace('/[^\PC\s]/u', '', $pattern);

        // Security / length guard
        if (strlen($pattern) > 2000) {
            return false;
        }

        if (@preg_match('@' . $pattern . '@', '') === false) {
            return false;
        }

        return $pattern;
    }

    private function preRedirectionCache(string $slug): void
    {
        global $wpdb;

        $postId = url_to_postid(home_url($slug));
        if ($postId) {
            $this->cache[] = [
                'from_url'    => $slug,
                'object_id'   => $postId,
                'object_type' => 'post',
            ];
            return;
        }

        $terms = $wpdb->get_results($wpdb->prepare("SELECT term_id FROM {$wpdb->terms} WHERE slug = %s", $slug));
        if ($terms) {
            foreach ($terms as $term) {
                $this->cache[] = [
                    'from_url'    => $slug,
                    'object_id'   => (int) $term->term_id,
                    'object_type' => 'term',
                ];
            }
            return;
        }

        $user = get_user_by('slug', $slug);
        if ($user) {
            $this->cache[] = [
                'from_url'    => $slug,
                'object_id'   => (int) $user->ID,
                'object_type' => 'user',
            ];
        }
    }

    public function save(): int|false
    {
        if (false === $this->hasSources()) {
            return false;
        }

        $id = DB::updateIff($this->data);
        $this->setId($id);

        if (false === $this->nocache) {
            $this->saveRedirectionCache();
        }

        return $id;
    }

    private function saveRedirectionCache(): void
    {
        if (!$this->getId() || empty($this->cache)) {
            return;
        }

        foreach ($this->cache as $item) {
            $item['redirection_id'] = $this->getId();
            Cache::add($item);
        }
    }

    private function getHomeDomain(): string
    {
        if ($this->domain !== null) {
            return $this->domain;
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $this->domain = preg_replace('/^www\./', '', (string) $host);
        return $this->domain;
    }

    public static function stripSubdirectory(string $url): string
    {
        $homeDir = ltrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
        return $homeDir ? str_replace(trailingslashit($homeDir), '', $url) : $url;
    }

    public static function getFullUri(): string
    {
        $serverUri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = str_replace(home_url('/'), '', $serverUri);
        $uri = urldecode($uri);
        $uri = trim(self::stripSubdirectory($uri), '/');

        return $uri;
    }
}
