<?php

namespace App\Features\LacaSeo\Modules\ImageSeo;

use App\Features\LacaSeo\Core\Helper;
use App\Features\LacaSeo\Core\Settings;
use App\Features\LacaSeo\Core\Variables;
use stdClass;

defined('ABSPATH') || exit;

/**
 * Image SEO module: Automatically adds ALT, TITLE, Caption, and Description,
 * applies casing, search/replace, and adds avatar alt attributes.
 * Matches 1:1 with Rank Math & Rank Math Pro Image SEO behavior.
 */
class ImageSeo
{
    private string $isAlt = '';
    private string $isTitle = '';
    private string $altChangeCase = '';
    private string $titleChangeCase = '';
    private array $captions = [];

    public function register(): void
    {
        add_action('wp', [$this, 'initHooks'], 9999);
        add_action('rest_api_init', [$this, 'initHooks']);

        // Avatar ALT
        if (Settings::get('general.add_avatar_alt', 'off') === 'on') {
            add_filter('get_avatar', [$this, 'avatarAddMissingAlt'], 99, 6);
        }

        // Caption and Description
        if (Settings::get('general.add_img_caption', 'off') === 'on') {
            add_filter('shortcode_atts_caption', [$this, 'addCaption'], 99, 1);
            add_filter('the_content', [$this, 'contentAddCaption'], 99);
        }

        if (Settings::get('general.add_img_description', 'off') === 'on') {
            add_filter('the_content', [$this, 'addDescription'], 99);
        }

        // Replacements
        $replacements = array_filter((array) Settings::get('general.image_replacements', []));
        if (!empty($replacements)) {
            add_filter('the_content', [$this, 'attributeCaptionReplacements'], 100);
            add_filter('post_thumbnail_html', [$this, 'attributeCaptionReplacements'], 20);
            add_filter('woocommerce_single_product_image_thumbnail_html', [$this, 'attributeCaptionReplacements'], 20);
            add_filter('shortcode_atts_caption', [$this, 'captionReplacements'], 100, 3);
        }

        // Case changes
        if (Settings::get('general.img_caption_change_case', '')) {
            add_filter('shortcode_atts_caption', [$this, 'changeCaptionCase'], 110, 1);
            add_filter('the_content', [$this, 'changeContentCaptionCase'], 110);
        }

        if (Settings::get('general.img_description_change_case', '')) {
            add_filter('the_content', [$this, 'changeDescriptionCase'], 110);
        }
    }

    public function setTestConfig(array $config): void
    {
        if (isset($config['is_alt'])) {
            $this->isAlt = (string) $config['is_alt'];
        }
        if (isset($config['is_title'])) {
            $this->isTitle = (string) $config['is_title'];
        }
        if (isset($config['alt_case'])) {
            $this->altChangeCase = (string) $config['alt_case'];
        }
        if (isset($config['title_case'])) {
            $this->titleChangeCase = (string) $config['title_case'];
        }
    }

    public function changeAttributeCaseString(string $val, string $case): string
    {
        return self::changeCase($val, $case);
    }


    public function initHooks(): void
    {
        $this->isAlt = (Settings::get('general.add_img_alt', 'off') === 'on') && Settings::get('general.img_alt_format')
            ? trim((string) Settings::get('general.img_alt_format'))
            : '';

        $this->isTitle = (Settings::get('general.add_img_title', 'off') === 'on') && Settings::get('general.img_title_format')
            ? trim((string) Settings::get('general.img_title_format'))
            : '';

        if ($this->isAlt !== '' || $this->isTitle !== '') {
            add_filter('the_content', [$this, 'addImgAttributes'], 11);
            add_filter('post_thumbnail_html', [$this, 'addImgAttributes'], 11, 2);
            add_filter('woocommerce_single_product_image_thumbnail_html', [$this, 'addImgAttributes'], 11);
        }

        $this->altChangeCase = (string) Settings::get('general.img_alt_change_case', '');
        $this->titleChangeCase = (string) Settings::get('general.img_title_change_case', '');

        if ($this->altChangeCase !== '' || $this->titleChangeCase !== '') {
            add_filter('the_content', [$this, 'changeAttributeCase'], 30);
            add_filter('post_thumbnail_html', [$this, 'changeAttributeCase'], 30);
            add_filter('woocommerce_single_product_image_thumbnail_html', [$this, 'changeAttributeCase'], 30);
        }
    }

    /**
     * Add missing alt and title attributes.
     */
    public function addImgAttributes(string $content, mixed $postId = null): string
    {
        if (empty($content)) {
            return $content;
        }

        $stripped = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $content);
        if (!preg_match_all('/<img ([^>]+)\/?>/iU', $stripped, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        $post = $this->getPostObject($postId);

        foreach ($matches as $image) {
            $isDirty = false;
            $attrs = self::extractAttributes($image[0]);

            if (!isset($attrs['src']) && !isset($attrs['data-ct-lazy'])) {
                continue;
            }

            $post->filename = $attrs['data-ct-lazy'] ?? $attrs['src'];
            if (!empty($attrs['data-src'])) {
                $post->filename = $attrs['data-src'];
            } elseif (!empty($attrs['data-layzr'])) {
                $post->filename = $attrs['data-layzr'];
            } elseif (!empty($attrs['nitro-lazy-srcset'])) {
                $post->filename = $attrs['nitro-lazy-srcset'];
            }

            $post->alttext = $attrs['alt'] ?? '';
            $post->titletext = $attrs['title'] ?? '';

            if ($this->isAlt !== '' && empty($attrs['alt'])) {
                $isDirty = true;
                $attrs['alt'] = trim(Variables::replace($this->isAlt, $post));
            }

            if ($this->isTitle !== '' && empty($attrs['title'])) {
                $isDirty = true;
                $attrs['title'] = trim(Variables::replace($this->isTitle, $post));
            }

            if ($isDirty) {
                $newTag = '<img' . self::attributesToString($attrs) . '>';
                $content = str_replace($image[0], $newTag, $content);
            }
        }

        return $content;
    }

    /**
     * Change case of alt and title attributes.
     */
    public function changeAttributeCase(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        $stripped = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $content);
        if (!preg_match_all('/<img ([^>]+)\/?>/iU', $stripped, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        $altCase = $this->altChangeCase ?: (string) Settings::get('general.img_alt_change_case', '');
        $titleCase = $this->titleChangeCase ?: (string) Settings::get('general.img_title_change_case', '');

        foreach ($matches as $image) {
            $isDirty = false;
            $attrs = self::extractAttributes($image[0]);

            if (!isset($attrs['src'])) {
                continue;
            }

            if ($altCase !== '' && !empty($attrs['alt'])) {
                $isDirty = true;
                $attrs['alt'] = self::changeCase($attrs['alt'], $altCase);
            }

            if ($titleCase !== '' && !empty($attrs['title'])) {
                $isDirty = true;
                $attrs['title'] = self::changeCase($attrs['title'], $titleCase);
            }

            if ($isDirty) {
                $newTag = '<img' . self::attributesToString($attrs) . '>';
                $content = str_replace($image[0], $newTag, $content);
            }
        }

        return $content;
    }

    /**
     * Avatar add missing alt.
     */
    public function avatarAddMissingAlt(string $avatar, mixed $idOrEmail, int $size, string $default, string $alt, array $args = []): string
    {
        if (is_admin() || empty($avatar) || !empty($alt)) {
            return $avatar;
        }

        if (!preg_match('/<img ([^>]+)\/?>/iU', $avatar)) {
            return $avatar;
        }

        $attrs = self::extractAttributes($avatar);
        if (!empty($attrs['alt'])) {
            return $avatar;
        }

        $newAlt = '';
        if (is_a($idOrEmail, 'WP_Comment')) {
            $user = $idOrEmail->comment_author;
        } elseif (is_int($idOrEmail)) {
            $user = get_user_by('id', $idOrEmail);
        } elseif (is_string($idOrEmail)) {
            $user = get_user_by('email', $idOrEmail);
        } else {
            $user = null;
        }

        if (is_a($user, 'WP_User')) {
            $newAlt = $user->get('display_name');
        } elseif (is_string($user)) {
            $newAlt = $user;
        } else {
            return $avatar;
        }

        $attrs['alt'] = sprintf(__('Avatar of %s', 'lacadev'), $newAlt);
        $newTag = '<img' . self::attributesToString($attrs) . '>';

        $altCase = (string) Settings::get('general.img_alt_change_case', '');
        $titleCase = (string) Settings::get('general.img_title_change_case', '');
        if ($altCase !== '' || $titleCase !== '') {
            $newTag = $this->changeAttributeCase($newTag);
        }

        return $newTag;
    }

    /**
     * Add caption to [caption] shortcode.
     */
    public function addCaption(array $out): array
    {
        if (!empty($out['caption'])) {
            return $out;
        }

        $format = (string) Settings::get('general.img_caption_format', '%title%');
        $out['caption'] = trim(Variables::replace($format, $this->getPostObject()));
        return $out;
    }

    public function changeCaptionCase(array $out): array
    {
        if (empty($out['caption'])) {
            return $out;
        }

        $case = (string) Settings::get('general.img_caption_change_case', '');
        $out['caption'] = self::changeCase($out['caption'], $case);
        return $out;
    }

    public function contentAddCaption(string $content): string
    {
        return preg_replace_callback('/<figure([^>]*)>(.*?)<\/figure>/is', function ($matches) {
            $figureAttrs = $matches[1];
            $figureBody  = $matches[2];

            if (stripos($figureBody, '<figcaption') !== false) {
                return $matches[0];
            }

            if (stripos($figureAttrs, 'wp-block-image') === false && stripos($figureBody, '<img') === false) {
                return $matches[0];
            }

            $format = (string) Settings::get('general.img_caption_format', '%title%');
            $caption = trim(Variables::replace($format, $this->getPostObject($matches[0])));
            if ($caption === '') {
                $caption = 'Image Caption';
            }

            return "<figure{$figureAttrs}>{$figureBody}<figcaption class=\"wp-element-caption\">{$caption}</figcaption></figure>";
        }, $content);
    }


    public function changeContentCaptionCase(string $content): string
    {
        $case = (string) Settings::get('general.img_caption_change_case', '');
        return preg_replace_callback('/(<figure[^<]+class="([^"]+ )?(wp-block-image|wp-caption).+<figcaption[^>]*>)([^<]+)(<\/figcaption>)/sU', function ($m) use ($case) {
            return $m[1] . self::changeCase($m[4], $case) . $m[5];
        }, $content);
    }

    public function addDescription(string $content): string
    {
        if (get_post_type() !== 'attachment') {
            return $content;
        }

        $stripped = wp_strip_all_tags($content);
        if (!empty($stripped)) {
            return $content;
        }

        $format = (string) Settings::get('general.img_description_format', '%title%');
        return $content . trim(Variables::replace($format, $this->getPostObject()));
    }

    public function changeDescriptionCase(string $content): string
    {
        if (get_post_type() !== 'attachment') {
            return $content;
        }

        $case = (string) Settings::get('general.img_description_change_case', '');
        $parts = preg_split('/(<[^>]+>)/', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $new = '';
        foreach ($parts as $part) {
            if (str_starts_with(trim($part), '<')) {
                $new .= $part;
            } else {
                $new .= self::changeCase($part, $case);
            }
        }

        return $new;
    }

    /**
     * Search and replace for image attributes.
     */
    public function attributeCaptionReplacements(string $content): string
    {
        $replacements = array_filter((array) Settings::get('general.image_replacements', []));
        foreach ($replacements as $replacement) {
            if (empty($replacement['replace_in']) || empty($replacement['find'])) {
                continue;
            }

            foreach ($replacement['replace_in'] as $attr) {
                if ($attr === 'alt' || $attr === 'title') {
                    $content = $this->attributeReplacement($content, $replacement['find'], $replacement['replace'] ?? '', $attr);
                }
            }
        }
        return $content;
    }

    private function attributeReplacement(string $content, string $find, string $replace, string $attribute): string
    {
        $stripped = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $content);
        if (!preg_match_all('/<img ([^>]+)\/?>/iU', $stripped, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        foreach ($matches as $image) {
            $attrs = self::extractAttributes($image[0]);
            if (empty($attrs[$attribute])) {
                continue;
            }

            $repVal = Variables::replace($replace, $this->getPostObject($image[0]));
            $newAttr = str_replace($find, $repVal, $attrs[$attribute]);
            if ($newAttr !== $attrs[$attribute]) {
                $attrs[$attribute] = $newAttr;
                $newTag = '<img' . self::attributesToString($attrs) . '>';
                $content = str_replace($image[0], $newTag, $content);
            }
        }

        return $content;
    }

    /**
     * Helper to change case.
     */
    public static function changeCase(string $value, string $case): string
    {
        $casesHash = [
            'titlecase'    => MB_CASE_TITLE,
            'sentencecase' => MB_CASE_LOWER,
            'lowercase'    => MB_CASE_LOWER,
            'uppercase'    => MB_CASE_UPPER,
        ];

        if (!isset($casesHash[$case])) {
            return $value;
        }

        if ('sentencecase' === $case) {
            $sentences = preg_split('/([.?!]+)/', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $newString = '';
            foreach ($sentences as $k => $sentence) {
                $newString .= (($k & 1) === 0)
                    ? mb_strtoupper(mb_substr(trim($sentence), 0, 1)) . mb_strtolower(mb_substr(trim($sentence), 1))
                    : $sentence . ' ';
            }
            return trim($newString);
        }

        return mb_convert_case($value, $casesHash[$case]);
    }

    /**
     * Get or build post object for context.
     */
    private function getPostObject(mixed $imageOrId = null): object
    {
        $post = is_int($imageOrId) ? get_post($imageOrId) : get_post();
        if (empty($post)) {
            $post = new stdClass();
        }

        if (is_string($imageOrId) && str_contains($imageOrId, '<img')) {
            $attrs = self::extractAttributes($imageOrId);
            if (!empty($attrs['src'])) {
                $post->filename = $attrs['data-src'] ?? $attrs['data-layzr'] ?? $attrs['nitro-lazy-srcset'] ?? $attrs['src'];
            }
        }

        return $post;
    }

    /**
     * Extract attributes from HTML tag matching Rank Math HTML::extract_attributes.
     */
    public static function extractAttributes(string $elem): array
    {
        $regex = '#([^\s=]+)\s*=\s*(\'[^<\']*\'|"[^<"]*")#';
        preg_match_all($regex, $elem, $attributes, PREG_SET_ORDER);

        $new = [];
        $remaining = $elem;
        foreach ($attributes as $attribute) {
            $val = substr($attribute[2], 1, -1);
            $new[$attribute[1]] = $val;
            $remaining = str_replace($attribute[0], '', $remaining);
        }

        $remaining = preg_replace('/<[^\s]+/', '', $remaining, 1);
        $regex = '/([^<][\w\d:_-]+)[\s>]/i';
        preg_match_all($regex, $remaining, $attributes, PREG_SET_ORDER);
        foreach ($attributes as $attribute) {
            $new[trim($attribute[1])] = null;
        }

        return $new;
    }

    /**
     * Convert attributes array to string matching Rank Math HTML::attributes_to_string.
     */
    public static function attributesToString(array $attributes = [], string $prefix = ''): string
    {
        if (empty($attributes)) {
            return '';
        }

        $out = '';
        foreach ($attributes as $key => $value) {
            if (true === $value || false === $value) {
                $value = $value ? 'true' : 'false';
            }

            $out .= ' ' . esc_html($prefix . $key);
            if (null === $value) {
                continue;
            }
            $out .= sprintf('="%s"', esc_attr($value));
        }

        return $out;
    }
}
