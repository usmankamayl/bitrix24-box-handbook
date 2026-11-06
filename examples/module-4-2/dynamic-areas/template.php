<?php
/**
 * Шаблон сайта с правильными Dynamic Areas.
 *
 * Показывает senior-подход к Composite:
 * - Шапка/футер — статика
 * - Имя пользователя, корзина — frame
 * - Рекомендации — deferred JS-виджет
 * - Мета-теги — статика (SEO!)
 */

/** @var \CMain $APPLICATION */
/** @var \CUser $USER */

use Bitrix\Main\Page\Frame;
?>
<!DOCTYPE html>
<html lang="<?= LANGUAGE_ID ?>">
<head>
    <?php
    // SEO-критичные теги — ВСЕГДА в статике
    $APPLICATION->ShowTitle();
    $APPLICATION->ShowMeta('description');
    $APPLICATION->ShowCSS();
    $APPLICATION->ShowHeadStrings();
    ?>
</head>
<body>
<?php $APPLICATION->ShowPanel(); ?>

<!-- ==========================================
     HEADER — статика, кешируется
     ========================================== -->
<header class="site-header">
    <div class="logo">
        <a href="/"><?= $APPLICATION->GetSiteProperty('site_name') ?></a>
    </div>

    <nav class="main-nav">
        <?php
        // Меню — статика, меняется редко
        $APPLICATION->IncludeComponent('bitrix:menu', 'top', [
            'ROOT_MENU_TYPE' => 'top',
            'CACHE_TYPE'     => 'A',
            'CACHE_TIME'     => '3600',
        ]);
        ?>
    </nav>

    <!-- ==========================================
         USER PANEL — динамическая область
         ========================================== -->
    <div class="user-panel">
        <?php
        // Frame с placeholder — то, что видно до загрузки динамики
        $frame = new Frame('user_panel');
        $frame->startDynamicArea();
        ?>
            <?php if ($USER->IsAuthorized()): ?>
                <span class="user-name"><?= htmlspecialcharsbx($USER->GetFullName()) ?></span>
                <a href="/personal/">Личный кабинет</a>
                <a href="?logout=yes">Выйти</a>
            <?php else: ?>
                <a href="/auth/">Войти</a>
                <a href="/register/">Регистрация</a>
            <?php endif; ?>
        <?php $frame->finishDynamicArea(); ?>
    </div>

    <!-- ==========================================
         CART — динамическая область с фолбэком
         ========================================== -->
    <div class="cart-widget">
        <?php
        $cartFrame = new Frame('cart_widget');
        // Placeholder — показывается до загрузки реальных данных
        $cartFrame->setAnimation(true); // покажет спиннер при загрузке
        $cartFrame->startDynamicArea();
        ?>
            <a href="/cart/" class="cart-link">
                <span class="cart-icon">🛒</span>
                <span class="cart-count"><?= getCartCount() ?></span>
                <span class="cart-total"><?= getCartTotal() ?> ₽</span>
            </a>
        <?php $cartFrame->finishDynamicArea(); ?>
    </div>
</header>

<!-- ==========================================
     MAIN CONTENT — статика (статья, каталог)
     ========================================== -->
<main class="content">
    <?php $APPLICATION->IncludeComponent('my:breadcrumbs', ''); ?>

    <div class="page-body">
        <?php
        // Основной контент — статьи, каталог и т.д.
        // Кешируется целиком
        echo $APPLICATION->GetContent();
        ?>
    </div>
</main>

<!-- ==========================================
     RECOMMENDATIONS — deferred JS-виджет
     ========================================== -->
<aside class="recommendations">
    <h3>Вам может быть интересно</h3>
    <div id="recommendations-widget" data-user-id="<?= $USER->GetID() ?>">
        <!-- Загружается JS'ом ПОСЛЕ полной загрузки страницы -->
        <div class="loading-placeholder">Загрузка рекомендаций...</div>
    </div>
</aside>

<!-- ==========================================
     FOOTER — статика
     ========================================== -->
<footer class="site-footer">
    <?php $APPLICATION->IncludeComponent('bitrix:menu', 'bottom', [
        'ROOT_MENU_TYPE' => 'bottom',
        'CACHE_TYPE'     => 'A',
        'CACHE_TIME'     => '3600',
    ]); ?>
    <p>&copy; <?= date('Y') ?> Company</p>
</footer>

<script>
// Загрузка рекомендаций после composite
BX.ready(function() {
    var widget = document.getElementById('recommendations-widget');
    if (!widget) return;

    // Ждём загрузки composite-динамики, потом грузим виджет
    BX.addCustomEvent('onCompositeReady', function() {
        BX.ajax({
            url: '/local/ajax/recommendations.php',
            data: { user_id: widget.dataset.userId },
            method: 'POST',
            onsuccess: function(html) {
                widget.innerHTML = html;
            }
        });
    });
});
</script>

</body>
</html>

<?php
/**
 * Вспомогательные функции для динамических областей.
 * В реальном проекте — методы сервисов модуля.
 */
function getCartCount(): int
{
    // Текущая корзина пользователя — динамическая
    if (!\Bitrix\Main\Loader::includeModule('sale')) {
        return 0;
    }
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );
    return count($basket);
}

function getCartTotal(): float
{
    if (!\Bitrix\Main\Loader::includeModule('sale')) {
        return 0.0;
    }
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(),
        \Bitrix\Main\Context::getCurrent()->getSite()
    );
    $total = 0;
    foreach ($basket as $item) {
        $total += $item->getPrice() * $item->getQuantity();
    }
    return $total;
}
