<?php
declare(strict_types=1);

/**
 * Проверка целостности проекта по манифесту SHA-256.
 * Использование:
 *   php tools/check_integrity.php
 */

$manifestFile = __DIR__ . '/../manifest.sha256';
$root         = dirname(__DIR__);

if (!is_file($manifestFile)) {
    fwrite(STDERR, "Манифест не найден: $manifestFile\n");
    exit(2);
}

$lines = file($manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$ok      = 0;
$changed = 0;
$missing = 0;
$report  = [];

foreach ($lines as $line) {
    if (!preg_match('/^([0-9a-f]{64})\s+(.+)$/i', $line, $m)) {
        continue;
    }
    $expected = strtolower($m[1]);
    $path     = $m[2];
    $full     = $root . '/' . $path;

    if (!is_file($full)) {
        $report[] = ['MISSING', $path];
        $missing++;
        continue;
    }

    $actual = hash_file('sha256', $full);
    if ($actual === $expected) {
        $report[] = ['OK', $path];
        $ok++;
    } else {
        $report[] = ['CHANGED', $path];
        $changed++;
    }
}

printf("%-9s %s\n", 'STATUS', 'FILE');
echo str_repeat('-', 60), "\n";
foreach ($report as [$status, $path]) {
    printf("%-9s %s\n", $status, $path);
}
echo str_repeat('-', 60), "\n";
printf("Итого: OK=%d, CHANGED=%d, MISSING=%d\n", $ok, $changed, $missing);

exit($changed === 0 && $missing === 0 ? 0 : 1);