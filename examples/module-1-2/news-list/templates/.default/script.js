/**
 * JavaScript для компонента "Список новостей"
 */

(function() {
    'use strict';

    /**
     * Инициализация компонента при загрузке DOM
     */
    document.addEventListener('DOMContentLoaded', function() {
        const newsLists = document.querySelectorAll('[data-component="news-list"]');

        newsLists.forEach(function(newsList) {
            initNewsList(newsList);
        });
    });

    /**
     * Инициализация списка новостей
     * @param {HTMLElement} container
     */
    function initNewsList(container) {
        // Добавляем класс для инициализированного компонента
        container.classList.add('news-list--initialized');

        // Можно добавить дополнительную логику:
        // - Ленивую загрузку изображений
        // - Анимацию при появлении в viewport
        // - Ajax-подгрузку дополнительных элементов

        initLazyLoading(container);
        initAnimationOnScroll(container);
    }

    /**
     * Инициализация ленивой загрузки изображений
     * @param {HTMLElement} container
     */
    function initLazyLoading(container) {
        // Если браузер поддерживает Intersection Observer
        if ('IntersectionObserver' in window) {
            const images = container.querySelectorAll('img[loading="lazy"]');

            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        // Изображение уже загружается браузером благодаря loading="lazy"
                        // Здесь можно добавить дополнительную логику, например анимацию
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            });

            images.forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }

    /**
     * Анимация при появлении элементов в области видимости
     * @param {HTMLElement} container
     */
    function initAnimationOnScroll(container) {
        if ('IntersectionObserver' in window) {
            const items = container.querySelectorAll('.news-item');

            const itemObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('news-item--visible');
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });

            items.forEach(function(item, index) {
                // Добавляем задержку для последовательной анимации
                item.style.transitionDelay = (index * 0.1) + 's';
                itemObserver.observe(item);
            });
        }
    }

    /**
     * Пример: Ajax-подгрузка дополнительных элементов
     * (требует доработки на бэкенде)
     */
    function loadMoreNews(container, page) {
        // Получаем параметры компонента
        const componentParams = {
            page: page,
            // другие параметры...
        };

        // Ajax-запрос
        BX.ajax.runComponentAction('custom:news.list', 'loadMore', {
            mode: 'class',
            data: componentParams
        }).then(function(response) {
            if (response.status === 'success') {
                // Добавляем новые элементы в список
                appendNewsItems(container, response.data.items);
            }
        });
    }

    /**
     * Добавление элементов в список
     * @param {HTMLElement} container
     * @param {Array} items
     */
    function appendNewsItems(container, items) {
        const itemsContainer = container.querySelector('.news-list__items');

        items.forEach(function(item) {
            const itemHtml = createNewsItemHtml(item);
            itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
        });
    }

    /**
     * Создание HTML элемента новости
     * @param {Object} item
     * @return {string}
     */
    function createNewsItemHtml(item) {
        // Формируем HTML на основе данных
        return `
            <article class="news-item">
                <div class="news-item__content">
                    <h3 class="news-item__title">
                        <a href="${item.detailUrl}">${item.name}</a>
                    </h3>
                    ${item.date ? `<time class="news-item__date">${item.date}</time>` : ''}
                    ${item.previewText ? `<div class="news-item__text">${item.previewText}</div>` : ''}
                    <a href="${item.detailUrl}" class="news-item__link">Подробнее →</a>
                </div>
            </article>
        `;
    }

})();
