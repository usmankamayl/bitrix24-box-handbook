<?php
/**
 * Задача 3: Работа с Request
 *
 * Страница с формой поиска, валидацией и защитой от XSS
 */

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use Bitrix\Main\Context;
use Bitrix\Main\Application;

$request = Context::getCurrent()->getRequest();
$errors = [];
$results = [];
$searchQuery = '';

// Обработка формы поиска
if ($request->isPost() && check_bitrix_sessid()) {
    $searchQuery = $request->getPost('search');

    // Валидация
    if (empty($searchQuery)) {
        $errors[] = 'Поисковой запрос не может быть пустым';
    } elseif (mb_strlen($searchQuery) < 3) {
        $errors[] = 'Минимальная длина запроса - 3 символа';
    } elseif (mb_strlen($searchQuery) > 100) {
        $errors[] = 'Максимальная длина запроса - 100 символов';
    }

    // Очистка от опасных символов
    $searchQuery = htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8');
    $searchQuery = trim($searchQuery);

    if (empty($errors)) {
        // Выполняем поиск (пример с инфоблоками)
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $results = searchInIblocks($searchQuery);
        }
    }
}

/**
 * Функция поиска в инфоблоках
 */
function searchInIblocks($query) {
    $arFilter = [
        'IBLOCK_ID' => [1, 2], // ID инфоблоков для поиска
        'ACTIVE' => 'Y',
        '%NAME' => $query, // Поиск по названию
    ];

    $res = \CIBlockElement::GetList(
        ['SORT' => 'ASC'],
        $arFilter,
        false,
        ['nTopCount' => 10],
        ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_TEXT']
    );

    $items = [];
    while ($item = $res->Fetch()) {
        $items[] = $item;
    }

    return $items;
}

$APPLICATION->SetTitle("Поиск по сайту");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .search-form { margin-bottom: 30px; }
        .search-input { width: 70%; padding: 10px; font-size: 16px; }
        .search-button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
        .error { color: red; padding: 10px; background: #ffe6e6; margin: 10px 0; }
        .results { margin-top: 20px; }
        .result-item { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
        .result-item h3 { margin: 0 0 10px 0; }
        .no-results { color: #666; padding: 20px; text-align: center; }
    </style>
</head>
<body>
    <h1>Поиск по сайту</h1>

    <form method="post" class="search-form">
        <?= bitrix_sessid_post() ?>

        <input
            type="text"
            name="search"
            class="search-input"
            placeholder="Введите поисковой запрос..."
            value="<?= htmlspecialchars($searchQuery) ?>"
            required
            minlength="3"
            maxlength="100"
        >

        <button type="submit" class="search-button">Найти</button>
    </form>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <div class="error"><?= $error ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($request->isPost() && empty($errors)): ?>
        <div class="results">
            <h2>Результаты поиска: "<?= htmlspecialchars($searchQuery) ?>"</h2>

            <?php if (empty($results)): ?>
                <div class="no-results">По вашему запросу ничего не найдено</div>
            <?php else: ?>
                <?php foreach ($results as $item): ?>
                    <div class="result-item">
                        <h3>
                            <a href="<?= htmlspecialchars($item['DETAIL_PAGE_URL']) ?>">
                                <?= htmlspecialchars($item['NAME']) ?>
                            </a>
                        </h3>
                        <?php if (!empty($item['PREVIEW_TEXT'])): ?>
                            <p><?= htmlspecialchars($item['PREVIEW_TEXT']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>
