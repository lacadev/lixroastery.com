<?php

namespace App\Features\DiscordHub\Forms;

/**
 * Lead đã chuẩn hoá — mọi adapter đều trả về cấu trúc này.
 */
final class Lead
{
    /** @var string Tên plugin form (Contact Form 7, Elementor...) */
    public $source;

    /** @var string Tên form */
    public $formName;

    /** @var array<int,array{key:string,label:string,type:string,value:string}> */
    public $fields = [];

    /** @var string */
    public $pageUrl = '';

    /** @var string */
    public $entryId = '';

    /** @var string Ghi chú bất thường (vd: email thông báo gửi thất bại) */
    public $warning = '';

    public function __construct(string $source, string $formName)

    {
        $this->source   = $source;
        $this->formName = $formName;
    }

    /**
     * @param mixed $value
     */
    public function addField(string $key, string $label, string $type, $value): void
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map([self::class, 'flatten'], $value), 'strlen'));
        }
        if (!is_scalar($value)) {
            return;
        }
        $value = trim((string) $value);
        if ('' === $value) {
            return;
        }
        $this->fields[] = [
            'key'   => $key,
            'label' => '' !== trim($label) ? $label : $key,
            'type'  => strtolower($type),
            'value' => $value,
        ];
    }

    /**
     * @param mixed $value
     */
    private static function flatten($value): string
    {
        if (is_array($value)) {
            return implode(' ', array_filter(array_map([self::class, 'flatten'], $value), 'strlen'));
        }
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
