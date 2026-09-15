<?php
namespace App\Helpers;

class I18n {
    private static $translations = [];
    private static $locale = 'es';

    public static function init($locale = 'es') {
        self::$locale = $locale;
        self::loadTranslations($locale);
    }

    private static function loadTranslations($locale) {
        $file = __DIR__ . "/../Lang/$locale.php";
        if (file_exists($file)) {
            self::$translations = require $file;
        } else {
            self::$translations = require __DIR__ . '/../Lang/es.php';
        }
    }

    public static function t($key, $params = []) {
        $text = self::$translations[$key] ?? $key;
        
        // Reemplazar parámetros: "Hola {name}" + ['name' => 'Juan'] = "Hola Juan"
        foreach ($params as $k => $v) {
            $text = str_replace("{{$k}}", $v, $text);
        }
        
        return $text;
    }

    public static function setLocale($locale) {
        self::init($locale);
    }

    public static function getLocale() {
        return self::$locale;
    }
}