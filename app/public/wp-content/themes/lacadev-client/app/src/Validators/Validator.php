<?php


namespace App\Validators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * Class Validator
 *
 * Usage:
 * $validator = (new App\Validators\Validator())->make($data = [], $rules = []);
 * $validator->fails();
 * $validator->passes();
 * $validator->errors();
 *
 * @package App\Validators
 */
class Validator {

    private $factory;

    public function __construct() {
        $this->factory = new Factory(
            $this->loadTranslator()
        );
    }

    protected function loadTranslator() {
        $filesystem = new Filesystem();
        $loader     = new FileLoader($filesystem, $this->getLangPath());
        $loader->addNamespace('lang', $this->getLangPath());
        $loader->load('vi', 'validation', 'lang');
        return new Translator($loader, 'vi');
    }

    public function __call($method, $args) {
        return call_user_func_array([$this->factory, $method], $args);
    }

    private function getLangPath() {
        return APP_DIR . '/languages';
    }
}
