<?php
require __DIR__ . '/src/db.php';
$pdo = db();
foreach (['admin', 'manager', 'worker'] as $login) {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE login = :l');
    $stmt->execute([':h' => password_hash($login, PASSWORD_BCRYPT), ':l' => $login]);
}
echo "Пароли обновлены: admin/admin, manager/manager, worker/worker\n";