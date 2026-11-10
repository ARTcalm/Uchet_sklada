<?php
declare(strict_types=1);
require __DIR__ . '/src/db.php';
$pdo = db();

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
        LIMIT 1
    ");
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (bool)$stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i
        LIMIT 1
    ");
    $stmt->execute([':t' => $table, ':i' => $index]);
    return (bool)$stmt->fetchColumn();
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1
    ");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
}

try {
    // ---------- 1. companies ----------
    if (!tableExists($pdo, 'companies')) {
        $pdo->exec("
            CREATE TABLE `companies` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `plan` ENUM('start','business','corporate') NOT NULL DEFAULT 'start',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_companies_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "✔ Создана таблица companies<br>";
    } else {
        echo "• Таблица companies уже существует<br>";
    }

    // Ищем/создаём «первую» компанию
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE email = 'admin@sklad.local' LIMIT 1");
    $stmt->execute();
    $companyId = (int)$stmt->fetchColumn();

    if ($companyId === 0) {
        $pdo->exec("
            INSERT INTO companies (name, email, plan)
            VALUES ('Первый склад', 'admin@sklad.local', 'business')
        ");
        $companyId = (int)$pdo->lastInsertId();
        echo "✔ Создана компания «Первый склад» (id={$companyId})<br>";
    } else {
        echo "• Компания «Первый склад» уже есть (id={$companyId})<br>";
    }

    // ---------- 2. products.company_id ----------
    if (!columnExists($pdo, 'products', 'company_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN company_id INT UNSIGNED NULL AFTER id;");
        $pdo->exec("UPDATE products SET company_id = {$companyId} WHERE company_id IS NULL;");
        $pdo->exec("
            ALTER TABLE products
            MODIFY COLUMN company_id INT UNSIGNED NOT NULL,
            ADD CONSTRAINT fk_products_company FOREIGN KEY (company_id)
                REFERENCES companies (id) ON UPDATE CASCADE ON DELETE CASCADE;
        ");
        echo "✔ products.company_id добавлен<br>";
    } else {
        $pdo->exec("UPDATE products SET company_id = {$companyId} WHERE company_id IS NULL;");
        echo "• products.company_id уже есть<br>";
    }

    // ---------- 3. operations.company_id ----------
    if (!columnExists($pdo, 'operations', 'company_id')) {
        $pdo->exec("ALTER TABLE operations ADD COLUMN company_id INT UNSIGNED NULL AFTER id;");
        $pdo->exec("UPDATE operations SET company_id = {$companyId} WHERE company_id IS NULL;");
        $pdo->exec("
            ALTER TABLE operations
            MODIFY COLUMN company_id INT UNSIGNED NOT NULL,
            ADD CONSTRAINT fk_operations_company FOREIGN KEY (company_id)
                REFERENCES companies (id) ON UPDATE CASCADE ON DELETE CASCADE;
        ");
        echo "✔ operations.company_id добавлен<br>";
    } else {
        $pdo->exec("UPDATE operations SET company_id = {$companyId} WHERE company_id IS NULL;");
        echo "• operations.company_id уже есть<br>";
    }

    // ---------- 4. audit_log.company_id ----------
    if (!columnExists($pdo, 'audit_log', 'company_id')) {
        $pdo->exec("ALTER TABLE audit_log ADD COLUMN company_id INT UNSIGNED NULL AFTER id;");
        $pdo->exec("UPDATE audit_log SET company_id = {$companyId} WHERE company_id IS NULL;");
        $pdo->exec("
            ALTER TABLE audit_log
            MODIFY COLUMN company_id INT UNSIGNED NOT NULL,
            ADD CONSTRAINT fk_audit_company FOREIGN KEY (company_id)
                REFERENCES companies (id) ON UPDATE CASCADE ON DELETE CASCADE;
        ");
        echo "✔ audit_log.company_id добавлен<br>";
    } else {
        $pdo->exec("UPDATE audit_log SET company_id = {$companyId} WHERE company_id IS NULL;");
        echo "• audit_log.company_id уже есть<br>";
    }

    // ---------- 5. users: company_id + email ----------
    if (!columnExists($pdo, 'users', 'company_id')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN company_id INT UNSIGNED NULL AFTER id;");
        echo "✔ users.company_id добавлен<br>";
    }

    if (!columnExists($pdo, 'users', 'email')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER company_id;");
        echo "✔ users.email добавлен<br>";
    }

    // Заполняем email из login (если login ещё существует)
    if (columnExists($pdo, 'users', 'login')) {
        $pdo->exec("
            UPDATE users
            SET email = CONCAT(login, '@sklad.local')
            WHERE email IS NULL OR email = ''
        ");
        echo "✔ email заполнен из login<br>";
    } else {
        // login уже нет — оставляем email, если он заполнен
        $pdo->exec("
            UPDATE users
            SET email = CONCAT('user', id, '@sklad.local')
            WHERE email IS NULL OR email = ''
        ");
        echo "• login уже удалён, email для существующих пользователей сохранён<br>";
    }

    $pdo->exec("UPDATE users SET company_id = {$companyId} WHERE company_id IS NULL;");

    // Доводим users до нужного состояния
    $pdo->exec("ALTER TABLE users MODIFY COLUMN company_id INT UNSIGNED NOT NULL;");
    $pdo->exec("ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL;");

    if (!indexExists($pdo, 'users', 'uq_users_email')) {
        $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_email (email);");
        echo "✔ индекс uq_users_email добавлен<br>";
    }

    if (columnExists($pdo, 'users', 'login')) {
        if (indexExists($pdo, 'users', 'uq_users_login')) {
            $pdo->exec("ALTER TABLE users DROP INDEX uq_users_login;");
        }
        $pdo->exec("ALTER TABLE users DROP COLUMN login;");
        echo "✔ Колонка users.login удалена<br>";
    }

    if (!indexExists($pdo, 'users', 'fk_users_company')) {
        try {
            $pdo->exec("
                ALTER TABLE users
                ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id)
                    REFERENCES companies (id) ON UPDATE CASCADE ON DELETE CASCADE;
            ");
            echo "✔ fk_users_company добавлен<br>";
        } catch (Throwable $e) {
            echo "• fk_users_company уже есть или не удалось добавить<br>";
        }
    }

    // ---------- 6. Пересоздаём пароли по умолчанию ----------
    $defaults = [
        'admin@sklad.local'   => 'admin',
        'manager@sklad.local' => 'manager',
        'worker@sklad.local'  => 'worker',
    ];
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE email = :e');
    foreach ($defaults as $email => $plain) {
        $stmt->execute([':h' => password_hash($plain, PASSWORD_BCRYPT), ':e' => $email]);
    }

    echo "<br><b>OK.</b> Компания #{$companyId}. Все таблицы приведены к новой схеме.<br>";
    echo "Логины: admin@sklad.local / admin, manager@sklad.local / manager, worker@sklad.local / worker<br>";
    echo "<b>Удали этот файл!</b>";
} catch (Throwable $e) {
    echo "<b style='color:red'>ОШИБКА:</b> " . htmlspecialchars($e->getMessage());
    echo "<br>Файл: " . htmlspecialchars($e->getFile());
    echo "<br>Строка: " . (int)$e->getLine();
    echo "<br>Частичные изменения могли остаться в БД (DDL не откатывается). Проверь таблицы и запусти миграцию снова — она идемпотентна.";
}