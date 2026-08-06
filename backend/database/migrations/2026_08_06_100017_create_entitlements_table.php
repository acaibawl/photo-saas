<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'entitlements';

    private const HISTORY_COLUMNS = [
        'id', 'order_item_id', 'guardian_id', 'photo_id', 'granted_at', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_item_id')->unique()->constrained('order_items');
            $table->foreignUlid('guardian_id')->constrained('guardians');
            $table->foreignUlid('photo_id')->constrained('photos');
            $table->timestamp('granted_at');
            $table->timestamps();

            // ダウンロード可否判定のホットパス用複合インデックス（04_auth_and_authorization.md §5参照）
            $table->index(['guardian_id', 'photo_id']);
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('order_item_id')->nullable();
            $table->ulid('guardian_id')->nullable();
            $table->ulid('photo_id')->nullable();
            $table->timestamp('granted_at')->nullable();
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
        Schema::dropIfExists(self::TABLE);
    }
};
