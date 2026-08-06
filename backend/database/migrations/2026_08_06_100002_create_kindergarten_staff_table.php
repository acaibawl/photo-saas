<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'kindergarten_staff';

    private const HISTORY_COLUMNS = [
        'id', 'kindergarten_id', 'name', 'email', 'email_normalized', 'password_hash',
        'role', 'last_login_at', 'invited_at', 'joined_at', 'deactivated_at',
        'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->string('name', 100);
            $table->string('email', 255);
            $table->string('email_normalized', 255);
            $table->string('password_hash');
            $table->enum('role', ['owner', 'staff']);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['kindergarten_id', 'email_normalized']);
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('kindergarten_id')->nullable();
            $table->string('name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('email_normalized', 255)->nullable();
            $table->string('password_hash')->nullable();
            $table->string('role', 20)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
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
