<?php
/**
 * Legacy-класс без namespace — такие в модулях до сих пор живут.
 * Модульный префикс к ним не применяется, путь задаётся картой в include.php.
 */

class CMyCompanyToolsString
{
    public static function upper(string $text): string
    {
        return mb_strtoupper($text, 'UTF-8');
    }
}
