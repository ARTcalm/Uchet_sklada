# Склад — веб-приложение учёта (PHP + MySQL + JS)

MVP для учёта склада: пользователи, товары, операции (приход/расход/списание), отчёты.
Стек: **чистый PHP 8+**, **чистый JS (ES6)**, **MySQL 8**. Без Composer, npm, Docker.

## Требования

- PHP 8.0+ с расширениями `pdo_mysql`, `session`
- MySQL 8.0+ (или MariaDB 10.4+)
- Веб-сервер: Apache/Nginx + PHP-FPM, либо встроенный `php -S`
- Рекомендуется: XAMPP 8.x / OpenServer / Laragon

## Структура

```
/warehouse
├── /public                  ← DocumentRoot
│   ├── index.php            ← точка входа (?page=...)
│   ├── /api                 ← JSON-эндпоинты
│   ├── /assets              ← style.css, app.js, /pages/*
│   ├── /views               ← PHP-шаблоны страниц
│   └── /partials            ← layout_header.php, layout_footer.php
├── /src
│   ├── db.php, auth.php, helpers.php, config.php (опционально)
│   └── /models              ← products.php, operations.php, users.php, reports.php
├── schema.sql
├── seed.sql
└── README.md
```

## Развёртывание на XAMPP (Windows/macOS)

1. **Скачать XAMPP 8.x** и запустить Apache + MySQL из панели управления.
2. **Скопировать проект** в `C:\xampp\htdocs\warehouse` (или `/Applications/XAMPP/htdocs/warehouse`).
3. **Создать БД и импортировать схему.**
   - Открыть `http://localhost/phpmyadmin`
   - Вкладка **SQL** → выполнить `schema.sql`
   - Затем выполнить `seed.sql`
4. **Проверить доступ к БД.** По умолчанию `src/db.php` ждёт:
   - host `127.0.0.1`, порт `3306`
   - user `root`, пароль ``
   - БД `warehouse`
   
   Если у вас другие настройки — создайте `src/config.php`:
   ```php
   <?php
   return [
       'host'     => '127.0.0.1',
       'port'     => '3306',
       'database' => 'warehouse',
       'username' => 'root',
       'password' => '',
       'charset'  => 'utf8mb4',
   ];
   ```
5. **Открыть приложение:** `http://localhost/warehouse/public/`
   
   > Если хотите без `/public` в URL — сделайте `public/` корнем виртуального хоста. В XAMPP это можно через `httpd-vhosts.conf` или `.htaccess` + `RewriteRule`.

## Развёртывание на OpenServer

1. Установить OpenServer (PHP 8+).
2. Скопировать проект в `OSPanel/domains/warehouse/`.
3. В настройках домена указать корень — папку **`public`**.
4. Создать БД `warehouse` (utf8mb4), импортировать `schema.sql` и `seed.sql` через phpMyAdmin.
5. Если пароль root не пустой — поправить `src/config.php`.

## Запуск через встроенный PHP-сервер (без Apache)

```bash
cd warehouse/public
php -S 127.0.0.1:8080
```

Открыть: `http://127.0.0.1:8080/`

> Убедитесь, что MySQL запущен и БД создана. `DocumentRoot` — именно `/public`.

## Вход

| Логин   | Пароль    | Роль    | Что может |
|---------|-----------|---------|-----------|
| admin   | admin     | admin   | всё |
| manager | manager   | manager | товары (CRUD), операции, отчёты |
| worker  | worker    | worker  | операции и просмотр остатков |

> ⚠️ Если после импорта `seed.sql` вход не проходит — хеши bcrypt не совпали с ожидаемыми. Запустите **один раз** скрипт `fix_passwords.php` (см. блок «Восстановление паролей» ниже) и удалите его.

### Восстановление паролей (одноразово)

Создать в корне проекта `fix_passwords.php`:

```php
<?php
require __DIR__ . '/src/db.php';
$pdo = db();
foreach (['admin', 'manager', 'worker'] as $login) {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE login = :l');
    $stmt->execute([':h' => password_hash($login, PASSWORD_BCRYPT), ':l' => $login]);
}
echo "Пароли обновлены: admin/admin, manager/manager, worker/worker\n";
```

Открыть `http://localhost/warehouse/fix_passwords.php` один раз и **удалить файл**.

## Быстрая проверка работоспособности

1. Войти как `admin` → дашборд показывает 4 карточки (товары, стоимость, ниже минимума, операции за сегодня).
2. **Товары → Добавить товар**: SKU `SKU-9001`, имя `Тест`, единица `шт`, цена `100`.
3. **Операции → Приход**: выбрать `Тест`, кол-во `10` → остаток станет `10`.
4. **Операции → Расход**: `3` → остаток `7`.
5. **Операции → Расход**: `100` → ошибка **«Недостаточно товара»**, остаток не меняется.
6. Войти как `worker`:
   - пункт «Пользователи» отсутствует в меню
   - `?page=users` → страница **403**
   - `api/users.php` → `{"ok":false,"error":"Доступ запрещён"}` (403)
7. Войти как `manager`: «Отчёты» работают, «Пользователи» — нет.
8. **Отчёты → Остатки → Экспорт CSV** — скачивается `stock_YYYYMMDD_HHMMSS.csv` с BOM (Excel не ломает кириллицу).

## Безопасность

- Все запросы к БД — через **PDO prepared statements** (`ATTR_EMULATE_PREPARES = false`).
- Пароли — **`password_hash()` (bcrypt)**, проверка — `password_verify()`.
- Вывод в HTML — через `htmlspecialchars()`.
- **CSRF**: токен в `$_SESSION['csrf']`, ротация после логина/логаута, проверка во всех POST/PUT/DELETE.
- **Проверка ролей** — на уровне API (`require_role([...])`) и на уровне страниц (`index.php`).
- **Транзакции** для операций: `beginTransaction` → `SELECT ... FOR UPDATE` → `UPDATE products` → `INSERT operations` → `commit` / `rollBack`.
- **Аудит**: все ключевые действия (login, create/update/delete товаров и операций, изменения пользователей) пишутся в `audit_log`.

## Что упрощено (намеренно)

- Один SQL-файл схемы, без миграций.
- Нет JWT — только PHP-сессии.
- Нет партий, локаций, резервов, штрихкодов.
- Нет тестов, Swagger, CI.
- UI — минимальный CSS, `<dialog>` нативный.

## Формат API

Единый JSON во всех эндпоинтах:

**Успех:**
```json
{ "ok": true, "data": { ... } }
```

**Ошибка:**
```json
{ "ok": false, "error": "Текст ошибки" }
```

HTTP-коды: `200` — успех, `400` — бизнес-ошибка, `401` — не авторизован, `403` — нет прав / CSRF, `405` — метод, `422` — валидация, `500` — серверная.

## Лицензия

Учебный проект. Делайте что хотите.