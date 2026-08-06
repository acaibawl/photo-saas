<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'guardians';

    private const HISTORY_COLUMNS = [
        'id', 'name', 'email', 'password_hash', 'email_verified_at', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->string('password_hash');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->string('name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('password_hash')->nullable();
            $table->timestamp('email_verified_at')->nullable();
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
