<?php

require_once __DIR__ . '/vendor/autoload.php';

echo App\ChordPro\Fingerprint::compute(
    'Великий Бог',
    "{title: Великий Бог}\n[C]Господь Вели[F]кий",
    'Карл Боберг'
);