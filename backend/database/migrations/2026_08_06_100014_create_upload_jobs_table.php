<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'upload_jobs';

    private const STATUSES = [
        'accepted', 'processing', 'previewing', 'metadata_persisted', 'completed', 'failed', 'retrying',
    ];

    private const HISTORY_COLUMNS = [
        'id', 'upload_request_id', 'file_key', 'original_filename', 'status', 'photo_id',
        'error_message', 'attempts', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('upload_request_id')->constrained('upload_requests');
            $table->string('file_key')->unique();
            $table->string('original_filename');
            $table->enum('status', self::STATUSES)->default('accepted');
            $table->foreignUlid('photo_id')->nullable()->constrained('photos');
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('upload_request_id')->nullable();
            $table->string('file_key')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('status', 20)->nullable();
            $table->ulid('photo_id')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->nullable();
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
