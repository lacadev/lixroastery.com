<?php

namespace App\Settings;

use Faker\Factory;

class FakerData
{
    /**
     * @var \Faker\Generator
     */
    private $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function createSamplePosts($postType = 'post', $postCount = 10, $metas = [])
    {
        $metaInputs = [];
        foreach ($metas as $metaKey => $metaValue) {
            $metaInputs[$metaKey] = $metaValue;
        }
        for ($p = 1; $p < $postCount; $p++) {
            wp_insert_post([
                'post_type'    => $postType,
                'post_title'   => $this->faker->sentence(mt_rand(5, 10)),
                'post_content' => $this->createSampleContent(mt_rand(10, 15)),
                'post_status'  => 'publish',
                'meta_input'   => $metaInputs,
            ]);
        }
    }

    private function createSampleContent($paragraphCount)
    {
        $content = '';
        for ($i = 1; $i <= $paragraphCount; $i++) {
            if ($this->faker->boolean() === true) {
                $content .= '<img src="' . $this->faker->imageUrl(1280, 600) . '" />';
            } else {
                $content .= '<p>' . $this->faker->paragraph(mt_rand(3, 30)) . '</p>';
            }
        }

        return $content;
    }

    public function createSampleCategory()
    {
        wp_insert_term(
            $this->faker->text(70), // the term
            'category', // the taxonomy
            [
                // 'description' => '',
                // 'slug'        => 'apple',
                // 'parent'      => $parent_term['term_id']  // get numeric term id
            ]
        );
    }
}
