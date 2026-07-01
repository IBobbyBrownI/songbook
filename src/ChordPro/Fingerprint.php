<?php

declare(strict_types=1);

namespace App\ChordPro;

class Fingerprint
{
    public static function compute(string $title, string $lirycsChordPro, ?string $firstArtistName): string
    {

        $firstArtistName = $firstArtistName ?? '';
        $title = mb_strtolower(trim($title), 'UTF-8');
        $firstArtistName = mb_strtolower(trim($firstArtistName), 'UTF-8');

        $lines = explode("\n", $lirycsChordPro);
        $firstLine = $lines[0];
        $firstLine = preg_replace('/\[[^\]]+\]/u', '', $firstLine);
        $firstLine = preg_replace('/\{[^}]+\}/u', '', $firstLine);
        $firstLine = preg_replace('/\s+/u', ' ', $firstLine);

        $combined = $title . "\n" . $firstLine . "\n" . $firstArtistName;
        return hash('sha256', $combined);
    }
}
