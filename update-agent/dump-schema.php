<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$appRoot = dirname(__DIR__);
$app = require $appRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql') {
    fwrite(STDERR, "Создание копии структуры поддерживает только MySQL.\n");
    exit(2);
}

$destination = $argv[1] ?? '';
if ($destination === '') {
    fwrite(STDERR, "Не задан путь для копии структуры.\n");
    exit(2);
}

$pdo = DB::connection()->getPdo();
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
$lines = ["SET FOREIGN_KEY_CHECKS=0;", ''];
foreach ($tables as $tableRow) {
    $table = (string) $tableRow[0];
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $statement = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);
    if (!$statement || !isset($statement[1])) {
        throw new RuntimeException("Не удалось прочитать структуру таблицы {$table}.");
    }
    $lines[] = "DROP TABLE IF EXISTS {$quoted};";
    $lines[] = $statement[1] . ';';
    $lines[] = '';
}
$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

if (file_put_contents($destination, implode("\n", $lines) . "\n", LOCK_EX) === false) {
    throw new RuntimeException('Не удалось сохранить копию структуры.');
}
