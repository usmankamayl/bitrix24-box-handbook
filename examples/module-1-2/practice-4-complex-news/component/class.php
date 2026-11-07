<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\Component\Tools;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * study:news — комплексный компонент.
 *
 * Задача компонента: определить страницу по адресу и отдать её шаблону.
 * Данные получают дочерние bitrix:news.list и bitrix:news.detail —
 * переписывать их выборку своими руками не нужно и вредно.
 *
 * Что здесь принципиально:
 *  - адрес разбирает CComponentEngine, а не регулярка в компоненте;
 *  - шаблоны URL берутся из $arParams['SEF_URL_TEMPLATES'] с подстановкой
 *    значений по умолчанию;
 *  - неизвестный маршрут — 404, а не «тихо показать список»;
 *  - родитель не кешируется: кеш живёт в дочерних компонентах (см. README).
 */
class StudyNewsComponent extends CBitrixComponent
{
    private const DEFAULT_URL_TEMPLATES = [
        'list' => '',
        'detail' => '#ELEMENT_ID#/',
    ];

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['IBLOCK_TYPE'] = trim((string)($arParams['IBLOCK_TYPE'] ?? ''));

        $arParams['NEWS_COUNT'] = (int)($arParams['NEWS_COUNT'] ?? 10);
        if ($arParams['NEWS_COUNT'] <= 0 || $arParams['NEWS_COUNT'] > 100) {
            $arParams['NEWS_COUNT'] = 10;
        }

        $arParams['SEF_MODE'] = ($arParams['SEF_MODE'] ?? 'Y') === 'Y';
        $arParams['SEF_FOLDER'] = $this->normalizeSefFolder((string)($arParams['SEF_FOLDER'] ?? '/'));

        if (!isset($arParams['SEF_URL_TEMPLATES']) || !is_array($arParams['SEF_URL_TEMPLATES'])) {
            $arParams['SEF_URL_TEMPLATES'] = [];
        }
        if (!isset($arParams['VARIABLE_ALIASES']) || !is_array($arParams['VARIABLE_ALIASES'])) {
            $arParams['VARIABLE_ALIASES'] = [];
        }

        $arParams['SET_STATUS_404'] = ($arParams['SET_STATUS_404'] ?? 'Y') === 'Y';
        $arParams['SHOW_404'] = ($arParams['SHOW_404'] ?? 'N') === 'Y';
        $arParams['FILE_404'] = trim((string)($arParams['FILE_404'] ?? ''));

        $arParams['CACHE_TYPE'] = (string)($arParams['CACHE_TYPE'] ?? 'A');
        if (!in_array($arParams['CACHE_TYPE'], ['A', 'Y', 'N'], true)) {
            $arParams['CACHE_TYPE'] = 'A';
        }
        $arParams['CACHE_TIME'] = max(0, (int)($arParams['CACHE_TIME'] ?? 3600));
        $arParams['CACHE_GROUPS'] = ($arParams['CACHE_GROUPS'] ?? 'Y') === 'Y';

        return $arParams;
    }

    public function executeComponent(): void
    {
        if ($this->arParams['IBLOCK_ID'] <= 0) {
            ShowError(Loc::getMessage('STUDY_NEWS_ERR_NO_IBLOCK'));
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError(Loc::getMessage('STUDY_NEWS_ERR_NO_MODULE'));
            return;
        }

        // Шаблоны URL: значения из настроек компонента поверх значений по умолчанию.
        $urlTemplates = CComponentEngine::makeComponentUrlTemplates(
            self::DEFAULT_URL_TEMPLATES,
            $this->arParams['SEF_URL_TEMPLATES']
        );

        $variables = [];
        $engine = new CComponentEngine($this);

        if ($this->arParams['SEF_MODE']) {
            // guessComponentPath отрезает SEF_FOLDER от запрошенного адреса
            // и раскладывает остаток по шаблонам. Ничего из БД он не читает.
            $componentPage = $engine->guessComponentPath(
                $this->arParams['SEF_FOLDER'],
                $urlTemplates,
                $variables
            );
        } else {
            // Режим без ЧПУ: переменные берутся из query-строки.
            CComponentEngine::initComponentVariables(
                false,
                ['ELEMENT_ID'],
                $this->arParams['VARIABLE_ALIASES'],
                $variables
            );

            $componentPage = isset($variables['ELEMENT_ID']) ? 'detail' : 'list';
        }

        if ($componentPage === false) {
            $this->show404();
            return;
        }

        if ($componentPage === 'detail') {
            // Из адреса пришла строка. «15», «15abc», «1e3», «-5» — не ID.
            $elementId = filter_var(
                $variables['ELEMENT_ID'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
            );

            if ($elementId === false) {
                $this->show404();
                return;
            }

            $variables['ELEMENT_ID'] = $elementId;
        }

        $this->arResult = [
            'FOLDER' => $this->arParams['SEF_FOLDER'],
            'URL_TEMPLATES' => $urlTemplates,
            'VARIABLES' => $variables,
        ];

        // Заголовок ставит дочерний компонент (SET_TITLE => 'Y' в list.php):
        // у него есть имя инфоблока, у родителя — нет.
        $this->includeComponentTemplate($componentPage);
    }

    /**
     * SEF_FOLDER приходит из настроек компонента, но нормализуется всё равно:
     * значение «../..» или «//» не должно разъехаться по всему сайту.
     */
    private function normalizeSefFolder(string $folder): string
    {
        $folder = str_replace('\\', '/', trim($folder));
        $folder = preg_replace('#/+#', '/', $folder) ?? '/';

        if ($folder === '' || !str_starts_with($folder, '/')) {
            $folder = '/' . ltrim($folder, '/');
        }

        if (preg_match('#(^|/)\.\.(/|$)#', $folder)) {
            return '/';
        }

        if (!str_ends_with($folder, '/')) {
            $folder .= '/';
        }

        return $folder;
    }

    private function show404(): void
    {
        Tools::process404(
            Loc::getMessage('STUDY_NEWS_404'),
            true,
            $this->arParams['SET_STATUS_404'],
            $this->arParams['SHOW_404'],
            $this->arParams['FILE_404']
        );
    }
}
