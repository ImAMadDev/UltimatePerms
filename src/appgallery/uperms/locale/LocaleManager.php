<?php

declare(strict_types=1);

namespace appgallery\uperms\locale;

use appgallery\uperms\Loader;

use pocketmine\utils\TextFormat;

final class LocaleManager{
    private const DEFAULT_LOCALE = 'en_US';

    /** @var array<string, array<string, string>> */
    private static array $locales = [];

    private static string $locale = self::DEFAULT_LOCALE;

    public static function init(string $selectedLocale = self::DEFAULT_LOCALE): void{
        $loader = Loader::getInstance();
        $baseDir = $loader->getDataFolder() . 'locale';

        if(!is_dir($baseDir)){
            mkdir($baseDir, 0777, true);
        }

        foreach($loader->getResources() as $resource){
            if($resource->getExtension() === 'ini'){
                $loader->saveResource('locale' . DIRECTORY_SEPARATOR . $resource->getFilename());
            }
        }

        foreach(glob($baseDir . DIRECTORY_SEPARATOR . '*.ini') ?: [] as $filePath){
            $localeName = basename($filePath, '.ini');
            $localeData = parse_ini_file($filePath);

            if(is_array($localeData)){
                /** @var array<string, string> $localeData */
                self::$locales[$localeName] = $localeData;
                $loader->getLogger()->info("Locale ($localeName) loaded.");
            }
        }

        self::$locale = isset(self::$locales[$selectedLocale])
            ? $selectedLocale
            : self::DEFAULT_LOCALE;
    }

    /**
     * @param array<string, string> $params
     */
    public static function get(string $key, array $params = []): string{
        $message = self::$locales[self::$locale][$key] ?? $key;

        foreach($params as $k => $v){
            $message = str_replace('{' . $k . '}', $v, $message);
        }

        return TextFormat::colorize($message);
    }
}