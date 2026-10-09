<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection(config('database.default'));

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $accessTypeExpr = $this->resolveAccessTypeIndexExpression('passage', 'AccessType');

        // AccessType может быть TEXT/BLOB в некоторых БД клиента.
        // Для MySQL в таком случае нужен префикс длины: AccessType(191).
        $this->createIndexIfMissing(
            'passage',
            'idx_passage_access_time_person_type_door',
            "`AccessTime`, `IdMergedVisitControl`, {$accessTypeExpr}, `DoorID`"
        );
        $this->createIndexIfMissing('passage', 'idx_passage_person_access_time', '`IdMergedVisitControl`, `AccessTime`');

        $this->createIndexIfMissing('mergedvisitcontrol', 'idx_mvc_department', '`Department`');
        $this->createIndexIfMissing('mergedvisitcontrol', 'idx_mvc_position', '`Position`');
    }

    private function resolveAccessTypeIndexExpression(string $tableName, string $columnName): string
    {
        $connection = DB::connection(config('database.default'));
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$database, $tableName, $columnName]
        );

        $dataType = strtolower((string) data_get($row, 'DATA_TYPE', ''));

        // Для text/blob колонок в MySQL индексируется только префикс.
        if (in_array($dataType, ['tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true)) {
            return "`{$columnName}`(191)";
        }

        return "`{$columnName}`";
    }

    public function down(): void
    {
        $connection = DB::connection(config('database.default'));

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropIndexIfExists('passage', 'idx_passage_access_time_person_type_door');
        $this->dropIndexIfExists('passage', 'idx_passage_person_access_time');

        $this->dropIndexIfExists('mergedvisitcontrol', 'idx_mvc_department');
        $this->dropIndexIfExists('mergedvisitcontrol', 'idx_mvc_position');
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $connection = DB::connection(config('database.default'));
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $tableName, $indexName]
        );

        return $row !== null;
    }

    private function createIndexIfMissing(string $tableName, string $indexName, string $columnsSql): void
    {
        if ($this->hasIndex($tableName, $indexName)) {
            return;
        }

        DB::connection(config('database.default'))
            ->statement("CREATE INDEX `{$indexName}` ON `{$tableName}` ({$columnsSql})");
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->hasIndex($tableName, $indexName)) {
            return;
        }

        DB::connection(config('database.default'))
            ->statement("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
    }
};

