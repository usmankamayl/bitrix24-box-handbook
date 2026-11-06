<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
/**
 * Шаблон компонента "Избранные товары".
 *
 * Статичный список + динамическая кнопка "Избранное".
 */

/** @var array $arResult */
/** @var FavoriteProductsComponent $component */

use Bitrix\Main\Page\Frame;
?>
<div class="products-grid">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <div class="product-card" data-product-id="<?= $item['ID'] ?>">
            <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                <img src="<?= $item['PREVIEW_PICTURE'] ?>"
                     alt="<?= htmlspecialcharsbx($item['NAME']) ?>">
                <h3><?= htmlspecialcharsbx($item['NAME']) ?></h3>
                <span class="price"><?= $item['PRICE'] ?> ₽</span>
            </a>

            <?php
            // ==========================================
            // ДИНАМИЧЕСКАЯ КНОПКА "В ИЗБРАННОЕ"
            // ==========================================
            // Состояние кнопки (активна/нет) зависит от пользователя
            // → выносим в frame

            $favFrame = new Frame('fav_btn_' . $item['ID']);
            // Placeholder — серая кнопка до загрузки
            $favFrame->setAnimation(true);
            $favFrame->startDynamicArea();
            ?>
                <?php
                $isFavorite = isProductInFavorites($item['ID']);
                $btnClass = $isFavorite ? 'favorite-btn active' : 'favorite-btn';
                $btnText  = $isFavorite ? '★ В избранном' : '☆ В избранное';
                ?>
                <button class="<?= $btnClass ?>"
                        data-product-id="<?= $item['ID'] ?>"
                        data-action="toggle-favorite">
                    <?= $btnText ?>
                </button>
            <?php $favFrame->finishDynamicArea(); ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
BX.ready(function() {
    // Делегируем обработчик на контейнер — после composite-замены
    // обработчики на кнопках не потеряются
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    grid.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action="toggle-favorite"]');
        if (!btn) return;

        var productId = btn.dataset.productId;
        var isActive  = btn.classList.contains('active');

        BX.ajax({
            url: '/local/ajax/favorites.php',
            data: {
                action: isActive ? 'remove' : 'add',
                product_id: productId,
                sessid: BX.bitrix_sessid()
            },
            method: 'POST',
            onsuccess: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    btn.classList.toggle('active');
                    btn.textContent = btn.classList.contains('active')
                        ? '★ В избранном'
                        : '☆ В избранное';
                }
            }
        });
    });
});
</script>

<?php
/**
 * Проверка "в избранном ли товар".
 * В реальном проекте — запрос к HL-блоку или свойству пользователя.
 *
 * ВАЖНО: эта функция вызывается ВНУТРИ frame — значит,
 * при composite-загрузке она выполнится на AJAX-запросе,
 * а не при генерации статической страницы.
 */
function isProductInFavorites(int $productId): bool
{
    global $USER;
    if (!$USER->IsAuthorized()) {
        return false;
    }

    // Упрощённо — в реальном проекте запрос к HL-блоку
    $favorites = \Bitrix\Main\UserFieldTable::getList([
        'filter' => [
            'USER_ID'    => $USER->GetID(),
            'FIELD_NAME' => 'UF_FAVORITES',
            'VALUE'      => $productId,
        ],
    ])->fetch();

    return !empty($favorites);
}
