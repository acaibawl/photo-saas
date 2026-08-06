<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'guardian_child';

    private const HISTORY_COLUMNS = [
        'id', 'guardian_id', 'child_id', 'kindergarten_id', 'label', 'linked_at',
        'unlinked_at', 'unlinked_by_staff_id', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('guardian_id')->constrained('guardians');
            $table->foreignUlid('child_id')->constrained('children');
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->string('label', 50);
            $table->timestamp('linked_at');
            $table->timestamp('unlinked_at')->nullable();
            $table->foreignUlid('unlinked_by_staff_id')->nullable()->constrained('kindergarten_staff');
            $table->timestamps();
        });

        // 同一 (guardian_id, child_id) の有効な紐づけ(unlinked_at IS NULL)は同時に1件までとする
        DB::statement(
            'CREATE UNIQUE INDEX guardian_child_active_link_unique ON guardian_child (guardian_id, child_id) WHERE unlinked_at IS NULL'
        );

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('guardian_id')->nullable();
            $table->ulid('child_id')->nullable();
            $table->ulid('kindergarten_id')->nullable();
            $table->string('label', 50)->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('unlinked_at')->nullable();
            $table->ulid('unlinked_by_staff_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('history_operation', 6);
            $table->timestamp('history_recorded_at');
        });

        $this->installHistoryTrigger(self::TABLE, self::HISTORY_COLUMNS);
    }

    public function down(): void
    {
        $this->uninstallHistoryTrigger(self::TABLE);
        Schema::dropIfExists(self::TABLE.'_histories');
        DB::statement('DROP INDEX IF EXISTS guardian_child_active_link_unique');
        Schema::dropIfExists(self::TABLE);
    }
};
