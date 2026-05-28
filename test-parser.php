<?php

require_once __DIR__ . '/vendor/autoload.php';

$parser = new App\ChordPro\Parser();

$text = "{title: Великий Бог}\n{key: C}\n\n{c: Куплет 1}\n[C]Господь Вели[F]кий\n\nПросто текст без аккордов";

$result = $parser->parse($text);

print_r($result);