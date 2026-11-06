# Пример: Установка приложения из Маркета + обновление токенов

## Описание

Полный цикл установки публичного приложения Bitrix24.Market:

- `install.php` — обработчик установки (сохраняет токены, регистрирует виджеты и события)
- `refresh.php` — обновление `access_token` через `refresh_token`

## Ключевой момент про токены

При установке приложения Bitrix24 **сразу передаёт токены** в POST (`AUTH_ID`, `REFRESH_ID`, `member_id`) — это НЕ OAuth code flow. Код-флоу (`?code=...`) используется только если приложение само инициирует авторизацию без установки.

```
Установка из Маркета:
POST install.php
  member_id=xxx & AUTH_ID=yyy & REFRESH_ID=zzz & DOMAIN=portal.bitrix24.ru
         ↓
Сохранить токены → зарегистрировать виджеты/события → показать "OK"
```

## Настройка

1. В карточке приложения (dev.bitrix24.ru) укажи:
   - **URL для установки:** `https://your-domain.com/install-oauth/install.php`
   - **Scope:** `crm,placement,event`
2. Замени `APP_CLIENT_ID` / `APP_CLIENT_SECRET` в обоих файлах
3. Замени `your-domain.com` в `install.php` на реальные URL обработчиков

## Где хранить токены

В примере — JSON-файлы в `data/`. В реальном проекте — БД:

```sql
CREATE TABLE portals (
    member_id VARCHAR(64) PRIMARY KEY,
    domain VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    expires INT,
    installed_at DATETIME
);
```

## Что демонстрирует

- ✅ Параметры установки (`member_id`, `AUTH_ID`, `REFRESH_ID`, `DOMAIN`)
- ✅ Регистрация виджета и события при установке
- ✅ Обновление `access_token` через `oauth.bitrix.info`
- ✅ Ротация `refresh_token` (он тоже обновляется!)

## Для собеседования

- Чем установочный flow отличается от OAuth code flow
- Зачем сохранять `member_id` (мультипортальность — одно приложение на многих порталах)
- Почему `refresh_token` тоже нужно перезаписывать
