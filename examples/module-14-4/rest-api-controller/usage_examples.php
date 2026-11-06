<?php
/**
 * Примеры использования REST API контроллера для динамических типов
 *
 * Демонстрирует все доступные методы REST API
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

// Проверка авторизации
global $USER;
if (!$USER->IsAuthorized()) {
    die('Требуется авторизация');
}

echo '<h1>Примеры использования REST API для динамических типов</h1>';

// Замените на ваш Entity Type ID
$entityTypeId = 128;

echo "<p><strong>Entity Type ID:</strong> {$entityTypeId}</p>";

// ============================================
// Вспомогательная функция для REST запросов
// ============================================

function restRequest($method, $params = [])
{
    $url = '/rest/' . $method;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $_SERVER['HTTP_HOST'] . $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Добавить авторизацию
    global $USER;
    $sessionId = bitrix_sessid();
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . session_name() . '=' . session_id()
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// ============================================
// Пример 1: Получить описание полей
// ============================================

echo '<h2>Пример 1: Получить описание полей</h2>';

$result = restRequest('mycompany.restapi.dynamic.fields', [
    'entityTypeId' => $entityTypeId,
]);

if (!empty($result['result']['fields'])) {
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Поле</th><th>Название</th><th>Тип</th><th>Обязательное</th></tr>';

    foreach ($result['result']['fields'] as $fieldName => $fieldInfo) {
        echo '<tr>';
        echo "<td>{$fieldName}</td>";
        echo "<td>{$fieldInfo['title']}</td>";
        echo "<td>{$fieldInfo['type']}</td>";
        echo "<td>" . ($fieldInfo['required'] ? 'Да' : 'Нет') . "</td>";
        echo '</tr>';
    }

    echo '</table>';
} else {
    echo '<pre>' . print_r($result, true) . '</pre>';
}

// ============================================
// Пример 2: Получить список стадий
// ============================================

echo '<h2>Пример 2: Получить список стадий</h2>';

$result = restRequest('mycompany.restapi.dynamic.stages', [
    'entityTypeId' => $entityTypeId,
]);

if (!empty($result['result']['stages'])) {
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>ID</th><th>Название</th><th>Сортировка</th><th>Цвет</th><th>Семантика</th></tr>';

    foreach ($result['result']['stages'] as $stage) {
        echo '<tr>';
        echo "<td>{$stage['STATUS_ID']}</td>";
        echo "<td>{$stage['NAME']}</td>";
        echo "<td>{$stage['SORT']}</td>";
        echo "<td style='background-color: {$stage['COLOR']};'>{$stage['COLOR']}</td>";
        echo "<td>{$stage['SEMANTICS']}</td>";
        echo '</tr>';
    }

    echo '</table>';
} else {
    echo '<pre>' . print_r($result, true) . '</pre>';
}

// ============================================
// Пример 3: Добавить элемент
// ============================================

echo '<h2>Пример 3: Добавить элемент</h2>';

$result = restRequest('mycompany.restapi.dynamic.add', [
    'entityTypeId' => $entityTypeId,
    'fields' => [
        'TITLE' => 'Тестовый элемент через REST API',
        'STAGE_ID' => 'NEW',
        'ASSIGNED_BY_ID' => $USER->GetID(),
        'BEGINDATE' => date('Y-m-d'),
        // Добавьте пользовательские поля вашего типа
        // 'UF_CUSTOM_FIELD' => 'значение',
    ],
]);

if (!empty($result['result']['item'])) {
    $newItemId = $result['result']['item']['ID'];
    echo "<p style='color: green;'>✓ Элемент создан! ID: {$newItemId}</p>";
    echo '<pre>' . print_r($result['result']['item'], true) . '</pre>';
} else {
    echo "<p style='color: red;'>✗ Ошибка создания</p>";
    echo '<pre>' . print_r($result, true) . '</pre>';
}

// ============================================
// Пример 4: Получить элемент по ID
// ============================================

if (isset($newItemId)) {
    echo '<h2>Пример 4: Получить элемент по ID</h2>';

    $result = restRequest('mycompany.restapi.dynamic.get', [
        'entityTypeId' => $entityTypeId,
        'id' => $newItemId,
    ]);

    if (!empty($result['result']['item'])) {
        echo '<table border="1" cellpadding="5">';
        foreach ($result['result']['item'] as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            echo "<tr><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
        }
        echo '</table>';
    } else {
        echo '<pre>' . print_r($result, true) . '</pre>';
    }
}

// ============================================
// Пример 5: Обновить элемент
// ============================================

if (isset($newItemId)) {
    echo '<h2>Пример 5: Обновить элемент</h2>';

    $result = restRequest('mycompany.restapi.dynamic.update', [
        'entityTypeId' => $entityTypeId,
        'id' => $newItemId,
        'fields' => [
            'TITLE' => 'Обновлённый элемент',
            'STAGE_ID' => 'IN_WORK',
        ],
    ]);

    if (!empty($result['result']['item'])) {
        echo "<p style='color: green;'>✓ Элемент обновлён</p>";
        echo '<pre>' . print_r($result['result']['item'], true) . '</pre>';
    } else {
        echo "<p style='color: red;'>✗ Ошибка обновления</p>";
        echo '<pre>' . print_r($result, true) . '</pre>';
    }
}

// ============================================
// Пример 6: Получить список элементов
// ============================================

echo '<h2>Пример 6: Получить список элементов</h2>';

$result = restRequest('mycompany.restapi.dynamic.list', [
    'entityTypeId' => $entityTypeId,
    'filter' => [],
    'order' => ['ID' => 'DESC'],
    'limit' => 10,
    'offset' => 0,
]);

if (!empty($result['result']['items'])) {
    echo "<p>Найдено элементов: {$result['result']['total']}</p>";
    echo "<p>Показано: " . count($result['result']['items']) . "</p>";

    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>ID</th><th>Название</th><th>Стадия</th><th>Ответственный</th><th>Создан</th></tr>';

    foreach ($result['result']['items'] as $item) {
        echo '<tr>';
        echo "<td>{$item['ID']}</td>";
        echo "<td>{$item['TITLE']}</td>";
        echo "<td>{$item['STAGE_ID']}</td>";
        echo "<td>{$item['ASSIGNED_BY_ID']}</td>";
        echo "<td>{$item['CREATED_TIME']}</td>";
        echo '</tr>';
    }

    echo '</table>';
} else {
    echo '<pre>' . print_r($result, true) . '</pre>';
}

// ============================================
// Пример 7: Фильтрация элементов
// ============================================

echo '<h2>Пример 7: Фильтрация элементов</h2>';

$result = restRequest('mycompany.restapi.dynamic.list', [
    'entityTypeId' => $entityTypeId,
    'filter' => [
        '=STAGE_ID' => 'NEW',
        '>ID' => 0,
    ],
    'order' => ['ID' => 'DESC'],
    'limit' => 5,
]);

if (!empty($result['result']['items'])) {
    echo "<p>Найдено элементов с фильтром: {$result['result']['total']}</p>";

    echo '<ul>';
    foreach ($result['result']['items'] as $item) {
        echo "<li>#{$item['ID']}: {$item['TITLE']} ({$item['STAGE_ID']})</li>";
    }
    echo '</ul>';
} else {
    echo '<p>Элементы не найдены или ошибка</p>';
    echo '<pre>' . print_r($result, true) . '</pre>';
}

// ============================================
// Пример 8: Удалить элемент (закомментировано)
// ============================================

echo '<h2>Пример 8: Удалить элемент</h2>';
echo '<p><em>Закомментировано для безопасности</em></p>';

/*
if (isset($newItemId)) {
    $result = restRequest('mycompany.restapi.dynamic.delete', [
        'entityTypeId' => $entityTypeId,
        'id' => $newItemId,
    ]);

    if (!empty($result['result']['success'])) {
        echo "<p style='color: green;'>✓ Элемент #{$newItemId} удалён</p>";
    } else {
        echo "<p style='color: red;'>✗ Ошибка удаления</p>";
        echo '<pre>' . print_r($result, true) . '</pre>';
    }
}
*/

// ============================================
// Примеры cURL запросов
// ============================================

echo '<h2>Примеры cURL запросов</h2>';

echo '<h3>1. Получить список (GET)</h3>';
echo '<pre>';
echo 'curl -X GET "https://your-domain.com/rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=10" \\' . "\n";
echo '  -H "Cookie: BITRIX_SM_LOGIN=admin; BITRIX_SM_UIDH=..."';
echo '</pre>';

echo '<h3>2. Добавить элемент (POST)</h3>';
echo '<pre>';
echo 'curl -X POST "https://your-domain.com/rest/mycompany.restapi.dynamic.add" \\' . "\n";
echo '  -H "Content-Type: application/x-www-form-urlencoded" \\' . "\n";
echo '  -H "Cookie: BITRIX_SM_LOGIN=admin; BITRIX_SM_UIDH=..." \\' . "\n";
echo '  -d "entityTypeId=128" \\' . "\n";
echo '  -d "fields[TITLE]=Новый элемент" \\' . "\n";
echo '  -d "fields[STAGE_ID]=NEW"';
echo '</pre>';

echo '<h3>3. Обновить элемент (POST)</h3>';
echo '<pre>';
echo 'curl -X POST "https://your-domain.com/rest/mycompany.restapi.dynamic.update" \\' . "\n";
echo '  -H "Content-Type: application/x-www-form-urlencoded" \\' . "\n";
echo '  -H "Cookie: BITRIX_SM_LOGIN=admin; BITRIX_SM_UIDH=..." \\' . "\n";
echo '  -d "entityTypeId=128" \\' . "\n";
echo '  -d "id=1" \\' . "\n";
echo '  -d "fields[TITLE]=Обновлённое название"';
echo '</pre>';

echo '<h3>4. Получить элемент (GET)</h3>';
echo '<pre>';
echo 'curl -X GET "https://your-domain.com/rest/mycompany.restapi.dynamic.get?entityTypeId=128&id=1" \\' . "\n";
echo '  -H "Cookie: BITRIX_SM_LOGIN=admin; BITRIX_SM_UIDH=..."';
echo '</pre>';

echo '<h3>5. Удалить элемент (POST)</h3>';
echo '<pre>';
echo 'curl -X POST "https://your-domain.com/rest/mycompany.restapi.dynamic.delete" \\' . "\n";
echo '  -H "Content-Type: application/x-www-form-urlencoded" \\' . "\n";
echo '  -H "Cookie: BITRIX_SM_LOGIN=admin; BITRIX_SM_UIDH=..." \\' . "\n";
echo '  -d "entityTypeId=128" \\' . "\n";
echo '  -d "id=1"';
echo '</pre>';

// ============================================
// Примеры JavaScript fetch
// ============================================

echo '<h2>Примеры JavaScript (fetch)</h2>';

echo '<h3>1. Получить список</h3>';
echo '<pre>';
echo htmlspecialchars("
fetch('/rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=10')
  .then(response => response.json())
  .then(data => {
    console.log('Total:', data.result.total);
    console.log('Items:', data.result.items);
  });
");
echo '</pre>';

echo '<h3>2. Добавить элемент</h3>';
echo '<pre>';
echo htmlspecialchars("
const formData = new FormData();
formData.append('entityTypeId', 128);
formData.append('fields[TITLE]', 'Новый элемент');
formData.append('fields[STAGE_ID]', 'NEW');

fetch('/rest/mycompany.restapi.dynamic.add', {
  method: 'POST',
  body: formData
})
  .then(response => response.json())
  .then(data => {
    console.log('Created item:', data.result.item);
  });
");
echo '</pre>';

echo '<h3>3. Обновить элемент</h3>';
echo '<pre>';
echo htmlspecialchars("
const formData = new FormData();
formData.append('entityTypeId', 128);
formData.append('id', 1);
formData.append('fields[TITLE]', 'Обновлённый элемент');

fetch('/rest/mycompany.restapi.dynamic.update', {
  method: 'POST',
  body: formData
})
  .then(response => response.json())
  .then(data => {
    console.log('Updated item:', data.result.item);
  });
");
echo '</pre>';

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
