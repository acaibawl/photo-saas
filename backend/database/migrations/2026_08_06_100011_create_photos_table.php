<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'photos';

    private const HISTORY_COLUMNS = [
        'id', 'kindergarten_id', 'album_id', 'storage_path', 'preview_path', 'price',
        'file_key', 'preview_status', 'uploaded_by_staff_id', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->foreignUlid('album_id')->nullable()->constrained('albums');
            $table->string('storage_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->integer('price')->nullable();
            $table->string('file_key')->unique();
            $table->enum('preview_status', ['queued', 'processing', 'ready', 'failed'])->default('queued');
            $table->foreignUlid('uploaded_by_staff_id')->constrained('kindergarten_staff');
            $table->timestamps();
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('kindergarten_id')->nullable();
            $table->ulid('album_id')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->integer('price')->nullable();
            $table->string('file_key')->nullable();
            $table->string('preview_status', 20)->nullable();
            $table->ulid('uploaded_by_staff_id')->nullable();
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
