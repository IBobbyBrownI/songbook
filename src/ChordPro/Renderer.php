<?php

namespace App\ChordPro;

class Renderer
{

    public function render(array $ast): string
    {
        $html = '';

        foreach ($ast['sections'] as $line)
        {
            if ($line['type'] === 'blank')
            {
                $html .= '<div class="chordpro-blank"></div>';
            }
            elseif ($line['type'] === 'comment')
            {
                $html .= '<div class="chordpro-comment">' . htmlspecialchars($line['text'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
            elseif ($line['type'] === 'line')
            {
                $html .= '<div class="chordpro-line">';

                foreach ($line['parts'] as $part) {
                    $html .= '<span class="chord-block">';
                    $html .= '<span class="chord">' . htmlspecialchars($part['chord'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
                    $html .= '<span class="text">' . htmlspecialchars($part['text'], ENT_QUOTES, 'UTF-8') . '</span>';
                    $html .= '</span>';
                }

                $html .= '</div>';
            }
        }


    return $html;
    }

}