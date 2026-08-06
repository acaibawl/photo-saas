<?php

namespace Database\Migrations\Support;

use Illuminate\Support\Facades\DB;

trait HistoryTrigger
{
    /**
     * {$table} への insert/update/delete のたびに、その時点の行データを
     * {$table}_histories へ記録するトリガー関数・トリガーを作成する。
     *
     * DELETE時は行削除後に発火するため、履歴テーブル側は主キー制約や
     * 外部キー制約を持たせない（親行が既に存在しない状態で参照されるため）。
     */
    protected function installHistoryTrigger(string $table, array $columns): void
    {
        $historyTable = $table.'_histories';
        $functionName = $table.'_record_history';
        $triggerName = $table.'_history_trigger';
        $columnList = implode(', ', $columns);
        $newValues = implode(', ', array_map(fn (string $column) => "NEW.{$column}", $columns));
        $oldValues = implode(', ', array_map(fn (string $column) => "OLD.{$column}", $columns));

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION {$functionName}() RETURNS trigger AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    INSERT INTO {$historyTable} ({$columnList}, history_operation, history_recorded_at)
                    VALUES ({$oldValues}, 'delete', clock_timestamp());
                    RETURN OLD;
                ELSE
                    INSERT INTO {$historyTable} ({$columnList}, history_operation, history_recorded_at)
                    VALUES ({$newValues}, lower(TG_OP), clock_timestamp());
                    RETURN NEW;
                END IF;
            END;
            \$\$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS {$triggerName} ON {$table};

            CREATE TRIGGER {$triggerName}
                AFTER INSERT OR UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION {$functionName}();
        SQL);
    }

    protected function uninstallHistoryTrigger(string $table): void
    {
        $functionName = $table.'_record_history';
        $triggerName = $table.'_history_trigger';

        DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName} ON {$table};");
        DB::unprepared("DROP FUNCTION IF EXISTS {$functionName}();");
    }
}
