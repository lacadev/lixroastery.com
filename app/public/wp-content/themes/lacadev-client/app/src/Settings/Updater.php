<?php

namespace App\Settings;

use GuzzleHttp\Client;

class Updater
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    private $url = 'https://google.com.vn';

    public function __construct()
    {
        $this->client = new Client();
        $this->checkForUpdate();
    }

    private function checkForUpdate()
    {
        add_action('after_setup_theme', function () {
            $response = $this->client->request('GET', $this->url);
            echo $response->getStatusCode(); # 200
            echo $response->getHeaderLine('content-type'); # 'application/json; charset=utf8'
            echo $response->getBody(); # '{"id": 1420053, "name": "guzzle", ...}'
        });
    }

    private function downloadZipFile($url, $name, $extensions)
    {
        $path      = __DIR__ . '/download/' . $name . $extensions;
        $file_path = fopen($path, 'w');
        $response  = $this->client->get($url, ['save_to' => $file_path]);
        return ['response_code' => $response->getStatusCode(), 'name' => $name];
    }
}
