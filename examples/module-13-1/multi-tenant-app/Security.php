<?php

final class Security
{
    public static function assertValidDomain(string $domain): void
    {
        // Домен обязан быть на bitrix24-инфраструктуре, иначе кто угодно
        // может подставить произвольный domain= в install.php и получить
        // токен, который потом будет использоваться от имени "левого" портала
        if (!preg_match('/^[a-z0-9\-]+\.bitrix24\.(ru|com|by|kz|ua|de)$/i', $domain)) {
            throw new RuntimeException('Untrusted domain: ' . $domain);
        }
    }

    public static function assertApplicationToken(string $expected, array $authPayload): void
    {
        // application_token приходит в каждом событии (ONAPPUNINSTALL и др.)
        // и должен совпадать с тем, что сохранён при установке для этого member_id —
        // иначе кто-то дёргает чужой обработчик, зная только member_id (он не секретный)
        $received = $authPayload['application_token'] ?? null;

        if (!$received || !hash_equals($expected, $received)) {
            throw new RuntimeException('application_token mismatch — possible spoofing attempt');
        }
    }
}
