<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('beds', 'garden_plan_id')) {
            Schema::table('beds', function (Blueprint $table) {
                $table->unsignedBigInteger('garden_plan_id')->nullable()->after('user_id');
            });
        }

        if (! $this->hasForeignKey('beds', 'garden_plan_id')) {
            Schema::table('beds', function (Blueprint $table) {
                $table->foreign('garden_plan_id')->references('id')->on('garden_plans')->cascadeOnDelete();
            });
        }

        $beds = DB::table('beds')->select('id', 'user_id')->get();
        foreach ($beds as $bed) {
            if (! $bed->user_id) {
                continue;
            }

            $userExists = DB::table('users')->where('id', $bed->user_id)->exists();
            if (! $userExists) {
                continue;
            }

            $planId = DB::table('garden_plans')
                ->where('user_id', $bed->user_id)
                ->orderBy('id')
                ->value('id');

            if (! $planId) {
                $planId = DB::table('garden_plans')->insertGetId([
                    'user_id' => $bed->user_id,
                    'name' => 'Minu aed',
                    'width' => 1200,
                    'height' => 800,
                    'unit' => 'cm',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('beds')->where('id', $bed->id)->update(['garden_plan_id' => $planId]);
        }

        $uniqueIndex = $this->getUniqueIndexName('garden_plans', 'user_id');
        if ($uniqueIndex) {
            Schema::table('garden_plans', function (Blueprint $table) use ($uniqueIndex) {
                $table->dropUnique($uniqueIndex);
            });
        }

        $nullCount = DB::table('beds')->whereNull('garden_plan_id')->count();
        if ($nullCount === 0) {
            Schema::table('beds', function (Blueprint $table) {
                $table->unsignedBigInteger('garden_plan_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $uniqueIndex = $this->getUniqueIndexName('garden_plans', 'user_id');
        if (! $uniqueIndex) {
            Schema::table('garden_plans', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }

        if (Schema::hasColumn('beds', 'garden_plan_id')) {
            Schema::table('beds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('garden_plan_id');
            });
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS cnt
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column],
        );

        return (int) ($row->cnt ?? 0) > 0;
    }

    private function getUniqueIndexName(string $table, string $column): ?string
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                $isUnique = (int) ($index->unique ?? 0) === 1;
                $indexName = (string) ($index->name ?? '');
                if (! $isUnique || $indexName === '') {
                    continue;
                }

                $indexColumns = DB::select("PRAGMA index_info('{$indexName}')");
                foreach ($indexColumns as $indexColumn) {
                    if ((string) ($indexColumn->name ?? '') === $column) {
                        return $indexName;
                    }
                }
            }

            return null;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Column_name = ? AND Non_unique = 0", [$column]);
        foreach ($rows as $row) {
            $keyName = (string) ($row->Key_name ?? '');
            if ($keyName !== 'PRIMARY') {
                return $keyName;
            }
        }

        return null;
    }
};
