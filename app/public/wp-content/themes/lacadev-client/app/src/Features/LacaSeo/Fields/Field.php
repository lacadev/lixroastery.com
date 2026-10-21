<?php

namespace App\Features\LacaSeo\Fields;

defined('ABSPATH') || exit;

/**
 * Value object representing a single SEO setting field.
 * Serves as the single source of truth for UI, sanitization, defaults, and export.
 */
class Field
{
    private string $id;
    private string $type;
    private string $label;
    private mixed $default = null;
    private array $choices = [];
    private ?array $showIf = null;
    private array $note = [
        'what'   => '',
        'how'    => '',
        'result' => '',
    ];
    private bool $translatable = false;
    private ?string $group = null;
    private ?string $tab = null;
    private array $attributes = [];
    /** @var callable|null */
    private $sanitizationCb = null;

    public function __construct(string $id, string $type, string $label)
    {
        $this->id = $id;
        $this->type = $type;
        $this->label = $label;
    }

    public static function make(string $id, string $type, string $label): self
    {
        return new self($id, $type, $label);
    }

    public function default(mixed $default): self
    {
        $this->default = $default;
        return $this;
    }

    public function choices(array $choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    public function showIf(string $fieldId, mixed $value, string $compare = '='): self
    {
        $this->showIf = [
            'field'   => $fieldId,
            'value'   => $value,
            'compare' => $compare,
        ];
        return $this;
    }

    public function note(string $what, string $how, string $result): self
    {
        $this->note = [
            'what'   => $what,
            'how'    => $how,
            'result' => $result,
        ];
        return $this;
    }

    public function translatable(bool $translatable = true): self
    {
        $this->translatable = $translatable;
        return $this;
    }

    public function group(string $group): self
    {
        $this->group = $group;
        return $this;
    }

    public function tab(string $tab): self
    {
        $this->tab = $tab;
        return $this;
    }

    public function attributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function sanitizeWith(callable $cb): self
    {
        $this->sanitizationCb = $cb;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getChoices(): array
    {
        return $this->choices;
    }

    public function getShowIf(): ?array
    {
        return $this->showIf;
    }

    public function getNote(): array
    {
        return $this->note;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Sanitize an incoming value for this field.
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->sanitizationCb !== null) {
            return ($this->sanitizationCb)($value);
        }

        return match ($this->type) {
            'toggle' => ($value === 'on' || $value === 1 || $value === '1' || $value === true) ? 'on' : 'off',
            'number' => is_numeric($value) ? (int) $value : 0,
            'textarea', 'variable_textarea' => sanitize_textarea_field((string) $value),
            'code' => (string) $value, // Don't strip newlines or special chars for robots/htaccess
            'multicheck', 'robots' => is_array($value) ? array_map('sanitize_text_field', $value) : [],
            'group' => is_array($value) ? array_values($value) : [],
            'advanced_robots' => is_array($value) ? [
                'max-snippet'       => isset($value['max-snippet']) ? (int) $value['max-snippet'] : -1,
                'max-video-preview' => isset($value['max-video-preview']) ? (int) $value['max-video-preview'] : -1,
                'max-image-preview' => sanitize_text_field($value['max-image-preview'] ?? 'large'),
            ] : [],
            'image' => is_array($value) ? [
                'id'  => absint($value['id'] ?? 0),
                'url' => esc_url_raw($value['url'] ?? ''),
            ] : sanitize_text_field((string) $value),
            'address' => is_array($value) ? [
                'streetAddress'   => sanitize_text_field($value['streetAddress'] ?? ($value['street'] ?? '')),
                'addressLocality' => sanitize_text_field($value['addressLocality'] ?? ($value['locality'] ?? '')),
                'addressRegion'   => sanitize_text_field($value['addressRegion'] ?? ($value['region'] ?? '')),
                'postalCode'      => sanitize_text_field($value['postalCode'] ?? ($value['postcode'] ?? '')),
                'addressCountry'  => sanitize_text_field($value['addressCountry'] ?? ($value['country'] ?? 'VN')),
            ] : [],
            'html', 'notice' => '',
            default => sanitize_text_field((string) $value),
        };
    }
}
