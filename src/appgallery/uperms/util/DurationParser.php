<?php

declare(strict_types=1);

namespace appgallery\uperms\util;

final class DurationParser{

    private const UNITS = [
        'y' => 31536000,
        'M' => 2592000,
        'w' => 604800,
        'd' => 86400,
        'h' => 3600,
        'm' => 60,
        's' => 1,
    ];

    /**
     * "1d12h" → int seconds | null si es permanente
     */
    public static function toSeconds(string $input): ?int{
        $input = trim(strtolower($input));

        if($input === '0' || $input === 'permanent' || $input === 'perm'){
            return null;
        }

        preg_match_all('/(\d+)([yMwdhms])/', $input, $matches, PREG_SET_ORDER);

        if(empty($matches)){
            return null;
        }

        $total = 0;
        foreach($matches as $match){
            $value = (int)$match[1];
            $unit = $match[2];
            $total += $value * (self::UNITS[$unit] ?? 0);
        }

        return $total > 0 ? $total : null;
    }

    /**
     * "1d12h" → Unix timestamp de expiración | null si permanente
     */
    public static function toTimestamp(string $input): ?int{
        $seconds = self::toSeconds($input);
        return $seconds !== null ? time() + $seconds : null;
    }

    /**
     * int seconds → "2d 3h 15m 4s"
     */
    public static function format(int $seconds): string{
        if($seconds <= 0) return "0s";

        $parts = [];
        foreach(self::UNITS as $unit => $size){
            if($seconds >= $size){
                $parts[] = intdiv($seconds, $size) . $unit;
                $seconds %= $size;
            }
        }

        return implode(' ', array_slice($parts, 0, 3)); // máximo 3 unidades
    }
}