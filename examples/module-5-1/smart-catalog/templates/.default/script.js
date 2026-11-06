(function () {
    'use strict';

    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('sc-offer-select')) {
            return;
        }

        var select = e.target;
        var offerId = parseInt(select.value, 10);
        var card = select.closest('.sc-card');
        var priceEl = card.querySelector('.sc-price-value');
        var stockEl = card.querySelector('[class^="sc-card__stock"]');
        var buyBtn = card.querySelector('.sc-buy-btn');

        buyBtn.disabled = true;

        BX.ajax.runComponentAction('bitrix_course:smart.catalog', 'getOfferPrice', {
            mode: 'ajax',
            data: { offerId: offerId }
        }).then(function (response) {
            var data = response.data;

            if (priceEl && data.PRICE !== null) {
                priceEl.textContent = parseFloat(data.PRICE).toFixed(2);
            }

            stockEl.classList.toggle('sc-card__stock--in', data.IN_STOCK);
            stockEl.classList.toggle('sc-card__stock--out', !data.IN_STOCK);
            stockEl.textContent = data.IN_STOCK ? 'В наличии' : 'Нет в наличии';

            buyBtn.dataset.id = offerId;
            buyBtn.disabled = !data.IN_STOCK;
        }).catch(function (response) {
            console.error('smart.catalog: getOfferPrice failed', response);
            buyBtn.disabled = false;
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.classList.contains('sc-buy-btn') || e.target.disabled) {
            return;
        }

        var productId = e.target.dataset.id;

        BX.ajax.runAction('sale.basket.addProduct', {
            data: { fields: { PRODUCT_ID: productId, QUANTITY: 1 } }
        }).then(function () {
            BX.onCustomEvent('OnBasketChange');
        }).catch(function (response) {
            console.error('Не удалось добавить товар в корзину', response);
        });
    });
})();
