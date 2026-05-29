<?php

require_once __DIR__ . '/vendor/autoload.php';

$parser = new App\ChordPro\Parser();
$renderer = new App\ChordPro\Renderer();

$text = "{title: Великий Бог}\n{key: C}\n\n{c: Куплет 1}\n[C]Господь Вели[F]кий, как я возгла[C]шу,\n[G]Творец всего, [C]Тебе всю жизнь по[G]свящу.\n\n{c: Припев}\n[F]Тогда поёт мой [C]дух Тебе, Гос[Am]подь:\nВелик Ты, [F]Бог! Ве[G]лик Ты, [C]Бог!";

$ast = $parser->parse($text);
echo $renderer->render($ast);