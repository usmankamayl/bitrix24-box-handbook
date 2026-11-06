<?php
/**
 * Тестовая страница для трассировки. Копировать в /trace-page.php
 * (корень сайта) — это обычная публичная страница через шаблон.
 *
 * Открывать: /trace-page.php?bx_trace=Y под администратором.
 */
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

$APPLICATION->SetTitle('Трассировка запроса');

// Заголовок и description ставятся ПОСЛЕ шапки — и всё равно попадут в <head>,
// потому что ShowHead()/ShowTitle() отложены до сброса буфера (см. теорию, п. 8).
$APPLICATION->SetPageProperty('description', 'Проверка фаз HTTP-запроса в Bitrix');
$APPLICATION->AddChainItem('Инструменты', '/tools/');
$APPLICATION->AddChainItem('Трассировка');

?>
<h1><?php $APPLICATION->ShowTitle(false); ?></h1>

<p>Открой эту страницу с параметром <code>?bx_trace=Y</code> под администратором —
внизу появится таблица фаз запроса.</p>

<p>Проверь в исходнике страницы: <code>&lt;meta name="description"&gt;</code>
содержит текст, заданный ниже по коду, а не пустой.</p>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
