<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\UI\Extension;

Extension::load('ui.notification');
?>

<!-- Live поиск с автодополнением -->
<div class="live-search-container" id="<?= $arResult['SEARCH_ID'] ?>">
    <div class="search-input-wrapper">
        <input
            type="text"
            class="search-input"
            placeholder="Начните вводить для поиска..."
            autocomplete="off"
        >
        <div class="search-loader" style="display: none;">
            <svg class="spinner" viewBox="0 0 50 50">
                <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
        </div>
    </div>

    <!-- Результаты поиска -->
    <div class="search-results" style="display: none;">
        <div class="results-list"></div>
        <div class="results-footer" style="display: none;">
            <span class="results-count"></span>
        </div>
    </div>

    <!-- Сообщения -->
    <div class="search-message" style="display: none;"></div>
</div>

<style>
.live-search-container {
    position: relative;
    max-width: 600px;
    margin: 20px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.search-input-wrapper {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 45px 12px 16px;
    font-size: 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    outline: none;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.search-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.search-loader {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
}

.spinner {
    width: 24px;
    height: 24px;
    animation: rotate 2s linear infinite;
}

.spinner .path {
    stroke: #007bff;
    stroke-linecap: round;
    animation: dash 1.5s ease-in-out infinite;
}

@keyframes rotate {
    100% { transform: rotate(360deg); }
}

@keyframes dash {
    0% {
        stroke-dasharray: 1, 150;
        stroke-dashoffset: 0;
    }
    50% {
        stroke-dasharray: 90, 150;
        stroke-dashoffset: -35;
    }
    100% {
        stroke-dasharray: 90, 150;
        stroke-dashoffset: -124;
    }
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 8px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
}

.results-list {
    padding: 8px 0;
}

.result-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.2s;
    text-decoration: none;
    color: inherit;
}

.result-item:hover {
    background-color: #f5f5f5;
}

.result-picture {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: cover;
    margin-right: 12px;
    flex-shrink: 0;
}

.result-picture-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    font-size: 20px;
    color: white;
    font-weight: bold;
}

.result-content {
    flex: 1;
    min-width: 0;
}

.result-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.result-preview {
    font-size: 13px;
    color: #666;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.results-footer {
    padding: 12px 16px;
    border-top: 1px solid #e0e0e0;
    background-color: #f9f9f9;
    text-align: center;
    font-size: 13px;
    color: #666;
}

.search-message {
    margin-top: 8px;
    padding: 12px 16px;
    background-color: #f0f0f0;
    border-radius: 6px;
    font-size: 14px;
    color: #666;
    text-align: center;
}

.no-results {
    padding: 24px 16px;
    text-align: center;
    color: #999;
    font-size: 14px;
}

/* Подсветка найденного текста */
.highlight {
    background-color: #fff3cd;
    font-weight: 600;
    padding: 0 2px;
    border-radius: 2px;
}
</style>

<script>
BX.ready(function() {
    // Инициализация поиска
    var container = BX('<?= $arResult['SEARCH_ID'] ?>');
    var input = container.querySelector('.search-input');
    var resultsDiv = container.querySelector('.search-results');
    var resultsList = container.querySelector('.results-list');
    var resultsFooter = container.querySelector('.results-footer');
    var resultsCount = container.querySelector('.results-count');
    var loader = container.querySelector('.search-loader');
    var messageDiv = container.querySelector('.search-message');

    var minQueryLength = <?= $arResult['MIN_QUERY_LENGTH'] ?>;
    var searchTimeout = null;
    var currentQuery = '';
    var isLoading = false;

    // Обработчик ввода с debounce
    BX.bind(input, 'input', function(e) {
        var query = e.target.value.trim();

        // Очищаем предыдущий таймер
        clearTimeout(searchTimeout);

        // Если запрос короткий - очищаем результаты
        if (query.length < minQueryLength) {
            hideResults();
            return;
        }

        // Запускаем поиск с задержкой (debounce)
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300); // 300ms задержка
    });

    // Закрытие результатов при клике вне
    BX.bind(document, 'click', function(e) {
        if (!container.contains(e.target)) {
            hideResults();
        }
    });

    // Очистка при нажатии Escape
    BX.bind(input, 'keydown', function(e) {
        if (e.key === 'Escape') {
            hideResults();
            input.blur();
        }
    });

    /**
     * Выполнение поиска через AJAX
     */
    function performSearch(query) {
        if (isLoading) {
            return;
        }

        currentQuery = query;
        isLoading = true;

        // Показываем loader
        loader.style.display = 'block';
        messageDiv.style.display = 'none';

        // AJAX запрос к компоненту
        BX.ajax.runComponentAction(
            'bitrix:module.14.2.live-search',
            'search',
            {
                mode: 'class',
                data: {
                    query: query,
                    limit: <?= $arResult['MAX_RESULTS'] ?>
                }
            }
        ).then(
            function(response) {
                isLoading = false;
                loader.style.display = 'none';

                if (response.data.success) {
                    displayResults(response.data);
                }
            },
            function(response) {
                isLoading = false;
                loader.style.display = 'none';

                console.error('Ошибка поиска:', response);
                showMessage('Ошибка при выполнении поиска');
            }
        );
    }

    /**
     * Отображение результатов
     */
    function displayResults(data) {
        resultsList.innerHTML = '';

        if (data.items.length === 0) {
            resultsList.innerHTML = '<div class="no-results">Ничего не найдено по запросу "' +
                escapeHtml(data.query) + '"</div>';
            resultsFooter.style.display = 'none';
        } else {
            // Выводим результаты
            data.items.forEach(function(item) {
                var itemElement = createResultItem(item, data.query);
                resultsList.appendChild(itemElement);
            });

            // Показываем счетчик
            resultsCount.textContent = 'Найдено результатов: ' + data.count;
            resultsFooter.style.display = 'block';
        }

        // Показываем блок результатов
        resultsDiv.style.display = 'block';
    }

    /**
     * Создание элемента результата
     */
    function createResultItem(item, query) {
        var link = BX.create('a', {
            props: { className: 'result-item', href: item.url },
            events: {
                click: function(e) {
                    // Генерируем событие при клике
                    BX.onCustomEvent('LiveSearch:itemSelected', [item]);
                }
            }
        });

        // Картинка или placeholder
        if (item.picture) {
            var img = BX.create('img', {
                props: { className: 'result-picture', src: item.picture, alt: item.name }
            });
            link.appendChild(img);
        } else {
            var placeholder = BX.create('div', {
                props: { className: 'result-picture-placeholder' },
                text: item.name.charAt(0).toUpperCase()
            });
            link.appendChild(placeholder);
        }

        // Контент
        var content = BX.create('div', {
            props: { className: 'result-content' }
        });

        var title = BX.create('div', {
            props: { className: 'result-title' },
            html: highlightQuery(item.name, query)
        });

        var preview = BX.create('div', {
            props: { className: 'result-preview' },
            html: highlightQuery(item.preview, query)
        });

        content.appendChild(title);
        if (item.preview) {
            content.appendChild(preview);
        }

        link.appendChild(content);

        return link;
    }

    /**
     * Подсветка найденного текста
     */
    function highlightQuery(text, query) {
        if (!text) return '';

        var escapedText = escapeHtml(text);
        var escapedQuery = escapeRegex(query);
        var regex = new RegExp('(' + escapedQuery + ')', 'gi');

        return escapedText.replace(regex, '<span class="highlight">$1</span>');
    }

    /**
     * Экранирование HTML
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Экранирование для регулярного выражения
     */
    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Показ сообщения
     */
    function showMessage(text) {
        messageDiv.textContent = text;
        messageDiv.style.display = 'block';
        resultsDiv.style.display = 'none';
    }

    /**
     * Скрытие результатов
     */
    function hideResults() {
        resultsDiv.style.display = 'none';
        messageDiv.style.display = 'none';
    }

    // Публичный API компонента
    window.LiveSearchComponent = {
        getQuery: function() {
            return currentQuery;
        },
        clear: function() {
            input.value = '';
            hideResults();
        },
        focus: function() {
            input.focus();
        }
    };
});
</script>
