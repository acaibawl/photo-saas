<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'upload_requests';

    private const STATUSES = [
        'accepted', 'processing', 'previewing', 'metadata_persisted', 'completed', 'failed', 'retrying',
    ];

    private const HISTORY_COLUMNS = [
        'id', 'kindergarten_id', 'album_id', 'price', 'child_ids', 'status', 'total_files',
        'accepted_count', 'requested_by_staff_id', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->foreignUlid('album_id')->nullable()->constrained('albums');
            $table->integer('price')->nullable();
            $table->json('child_ids')->nullable();
            $table->enum('status', self::STATUSES)->default('accepted');
            $table->integer('total_files');
            $table->integer('accepted_count');
            $table->foreignUlid('requested_by_staff_id')->constrained('kindergarten_staff');
            $table->timestamps();
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('kindergarten_id')->nullable();
            $table->ulid('album_id')->nullable();
            $table->integer('price')->nullable();
            $table->json('child_ids')->nullable();
            $table->string('status', 20)->nullable();
            $table->integer('total_files')->nullable();
            $table->integer('accepted_count')->nullable();
            $table->ulid('requested_by_staff_id')->nullable();
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
