<?php

declare(strict_types=1);

namespace App\ChordPro;

class Parser
{
    public function parse(string $text): array
    {
        $result = [
            'metadata' => [],
            'sections' => [],
        ];

        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $result['sections'][] = ['type' => 'blank'];
                continue;
            }

            if (preg_match('/^\{(\w+):\s*(.+)\}$/u', $trimmed, $matches)) {
                $directive = $matches[1];
                $value = $matches[2];

                if ($directive === 'title' || $directive === 'key') {
                    $result['metadata'][$directive] = $value;
                } elseif ($directive === 'c') {
                    $result['sections'][] = ['type' => 'comment', 'text' => $value];
                }
                continue;
            }

            $parts = preg_split('/(\[[^\]]+\])/u', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE);
            $lineParts = [];
            $currentChord = null;

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (preg_match('/^\[([^\]]+)\]$/u', $part, $m)) {
                    $currentChord = $m[1];
                } else {
                    $lineParts[] = ['chord' => $currentChord, 'text' => $part];
                    $currentChord = null;
                }
            }

            $result['sections'][] = ['type' => 'line', 'parts' => $lineParts];
        }

        return $result;
    }
}
