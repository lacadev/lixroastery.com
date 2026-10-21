<?php

namespace App\Features\LacaSeo\Modules\LocalSeo;

defined('ABSPATH') || exit;

class Address
{
    public function getData(array $atts, array $schema): string
    {
        $data = $this->renderAddress($schema, $atts);

        $labels = [
            'telephone'        => [
                'key'   => 'telephone',
                'label' => __('Phone', 'lacadev'),
            ],
            'secondary_number' => [
                'key'   => 'secondary_number',
                'label' => __('Secondary phone', 'lacadev'),
            ],
            'fax'              => [
                'key'   => 'faxNumber',
                'label' => __('Fax', 'lacadev'),
            ],
            'email'            => [
                'key'   => 'email',
                'label' => __('Email', 'lacadev'),
            ],
            'url'              => [
                'key'   => 'url',
                'label' => __('URL', 'lacadev'),
            ],
            'vat_id'           => [
                'key'   => 'vatID',
                'label' => __('VAT ID', 'lacadev'),
            ],
            'tax_id'           => [
                'key'   => 'taxID',
                'label' => __('Tax ID', 'lacadev'),
            ],
            'coc_id'           => [
                'key'   => 'coc_id',
                'label' => __('Chamber of Commerce ID', 'lacadev'),
            ],
            'pricerange'       => [
                'key'   => 'priceRange',
                'label' => __('Price indication', 'lacadev'),
            ],
        ];

        foreach ($labels as $key => $label) {
            if (empty($atts["show_{$key}"]) || empty($schema[$label['key']])) {
                continue;
            }

            $value = esc_html($schema[$label['key']]);
            if ($key === 'email') {
                $value = '<a href="mailto:' . $value . '">' . $value . '</a>';
            } elseif (in_array($key, ['telephone', 'secondary_number'], true)) {
                $value = '<a href="tel:' . $value . '">' . $value . '</a>';
            }

            $data .= '<div class="laca-seo-contact-field rank-math-contact-field"><strong>' . $label['label'] . '</strong>: ' . $value . '</div>';
        }

        return $data;
    }

    private function renderAddress(array $schema, array $atts): string
    {
        if (empty($schema['address']) || !is_array($schema['address'])) {
            return '';
        }

        $addr = $schema['address'];
        $street   = $addr['streetAddress'] ?? '';
        $locality = $addr['addressLocality'] ?? '';
        $region   = !empty($atts['show_state']) ? ($addr['addressRegion'] ?? '') : '';
        $postal   = $addr['postalCode'] ?? '';
        $country  = !empty($atts['show_country']) ? ($addr['addressCountry'] ?? '') : '';

        $parts = array_filter([$street, $locality, $region, $postal, $country]);
        if (empty($parts)) {
            return '';
        }

        $oneLine = !empty($atts['show_on_one_line']);
        $sep = $oneLine ? ', ' : '<br/>';

        return '<div class="laca-seo-address rank-math-address"><address>' . implode($sep, array_map('esc_html', $parts)) . '</address></div>';
    }
}
