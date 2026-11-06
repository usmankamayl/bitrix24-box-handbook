(function() {
    'use strict';

    /**
     * Класс для управления фильтрованным списком с Ajax
     */
    class FilteredList {
        constructor(container) {
            this.container = container;
            this.componentId = container.id;
            this.config = window.filteredListComponents[this.componentId];

            // Элементы
            this.form = container.querySelector('.filter-form');
            this.listContainer = container.querySelector('[data-list-container]');
            this.loader = container.querySelector('[data-loader]');
            this.resetButtons = container.querySelectorAll('[data-reset-filter]');

            // Состояние
            this.isLoading = false;
            this.abortController = null;

            this.init();
        }

        /**
         * Инициализация
         */
        init() {
            if (!this.config.ajax) {
                return; // Ajax отключен - работаем через обычную отправку формы
            }

            this.bindEvents();
        }

        /**
         * Привязка событий
         */
        bindEvents() {
            // Отправка формы через Ajax
            if (this.form) {
                this.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.applyFilter();
                });

                // Автоматическое применение при изменении полей
                const filterFields = this.form.querySelectorAll('[data-filter-field]');
                filterFields.forEach(field => {
                    field.addEventListener('change', () => {
                        this.applyFilter();
                    });
                });
            }

            // Кнопки сброса фильтра
            this.resetButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.resetFilter();
                });
            });

            // Перехват кликов по пагинации
            this.listContainer.addEventListener('click', (e) => {
                const link = e.target.closest('.bx-pagination a');
                if (link) {
                    e.preventDefault();
                    this.loadPage(link.href);
                }
            });

            // Обработка кнопки "Назад" браузера
            window.addEventListener('popstate', () => {
                this.applyFilter(false);
            });
        }

        /**
         * Применение фильтра
         */
        async applyFilter(updateHistory = true) {
            if (this.isLoading) {
                return;
            }

            // Отменяем предыдущий запрос
            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();

            // Собираем данные формы
            const formData = new FormData(this.form);
            const params = new URLSearchParams();

            for (const [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            // Добавляем ajax параметр
            params.append('ajax', 'Y');

            const url = this.config.url + '&' + params.toString();

            try {
                this.showLoader();

                const response = await fetch(url, {
                    signal: this.abortController.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success) {
                    this.updateContent(data.html);

                    // Обновляем URL в адресной строке
                    if (updateHistory) {
                        const newUrl = this.form.action + '?' + params.toString().replace('ajax=Y', '');
                        window.history.pushState({}, '', newUrl);
                    }

                    // Скроллим к началу списка
                    this.scrollToList();
                }

            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('Request aborted');
                } else {
                    console.error('Filter error:', error);
                    this.showError('Ошибка при загрузке данных');
                }
            } finally {
                this.hideLoader();
                this.abortController = null;
            }
        }

        /**
         * Загрузка страницы пагинации
         */
        async loadPage(url) {
            if (this.isLoading) {
                return;
            }

            try {
                this.showLoader();

                const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=Y';

                const response = await fetch(ajaxUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();

                if (data.success) {
                    this.updateContent(data.html);
                    window.history.pushState({}, '', url);
                    this.scrollToList();
                }

            } catch (error) {
                console.error('Pagination error:', error);
                this.showError('Ошибка при загрузке страницы');
            } finally {
                this.hideLoader();
            }
        }

        /**
         * Сброс фильтра
         */
        resetFilter() {
            if (this.form) {
                // Очищаем все поля
                this.form.reset();

                // Очищаем select'ы
                const selects = this.form.querySelectorAll('select');
                selects.forEach(select => {
                    select.selectedIndex = 0;
                });

                // Применяем пустой фильтр
                this.applyFilter();
            }
        }

        /**
         * Обновление контента
         */
        updateContent(html) {
            // Парсим HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector('[data-list-container]');

            if (newContent) {
                this.listContainer.innerHTML = newContent.innerHTML;
            }
        }

        /**
         * Показать loader
         */
        showLoader() {
            this.isLoading = true;
            this.container.classList.add('loading');
            if (this.loader) {
                this.loader.style.display = 'grid';
            }
        }

        /**
         * Скрыть loader
         */
        hideLoader() {
            this.isLoading = false;
            this.container.classList.remove('loading');
            if (this.loader) {
                this.loader.style.display = 'none';
            }
        }

        /**
         * Показать ошибку
         */
        showError(message) {
            // Простое уведомление через alert
            // В продакшене лучше использовать toast-уведомления
            alert(message);
        }

        /**
         * Скролл к списку
         */
        scrollToList() {
            const offset = 100;
            const top = this.listContainer.getBoundingClientRect().top + window.pageYOffset - offset;

            window.scrollTo({
                top: top,
                behavior: 'smooth'
            });
        }
    }

    /**
     * Инициализация всех компонентов на странице
     */
    function initFilteredLists() {
        const components = document.querySelectorAll('.filtered-list[data-ajax="true"]');

        components.forEach(component => {
            new FilteredList(component);
        });
    }

    // Запуск при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFilteredLists);
    } else {
        initFilteredLists();
    }

})();
