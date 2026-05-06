<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uniqueIndex = $this->getSingleColumnUniqueIndexName('garden_plans', 'user_id');

        if ($uniqueIndex) {
            Schema::table('garden_plans', function (Blueprint $table) use ($uniqueIndex) {
                $table->dropUnique($uniqueIndex);
            });
        }
    }

    public function down(): void
    {
        $uniqueIndex = $this->getSingleColumnUniqueIndexName('garden_plans', 'user_id');

        if (! $uniqueIndex) {
            Schema::table('garden_plans', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }
    }

    private function getSingleColumnUniqueIndexName(string $table, string $column): ?string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                $indexName = (string) ($index->name ?? '');

                if ((int) ($index->unique ?? 0) !== 1 || $indexName === '') {
                    continue;
                }

                $columns = DB::select("PRAGMA index_info('{$indexName}')");
                if (count($columns) === 1 && (string) ($columns[0]->name ?? '') === $column) {
                    return $indexName;
                }
            }

            return null;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $rows = DB::select(
            'SELECT s.INDEX_NAME
             FROM information_schema.STATISTICS s
             JOIN (
                 SELECT INDEX_NAME, COUNT(*) AS column_count
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                 GROUP BY INDEX_NAME
             ) grouped ON grouped.INDEX_NAME = s.INDEX_NAME
             WHERE s.TABLE_SCHEMA = DATABASE()
               AND s.TABLE_NAME = ?
               AND s.COLUMN_NAME = ?
               AND s.NON_UNIQUE = 0
               AND s.INDEX_NAME <> ?
               AND grouped.column_count = 1
             LIMIT 1',
            [$table, $table, $column, 'PRIMARY'],
        );

        return isset($rows[0]) ? (string) $rows[0]->INDEX_NAME : null;
    }
};
