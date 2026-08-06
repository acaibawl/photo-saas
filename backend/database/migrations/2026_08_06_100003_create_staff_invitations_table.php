<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'staff_invitations';

    private const HISTORY_COLUMNS = [
        'id', 'kindergarten_id', 'name', 'email', 'email_normalized', 'role', 'token_hash',
        'expires_at', 'accepted_at', 'revoked_at', 'created_by_staff_id', 'accepted_staff_id',
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
            $table->string('role', 20);
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('created_by_staff_id')->constrained('kindergarten_staff');
            $table->foreignUlid('accepted_staff_id')->nullable()->constrained('kindergarten_staff');
            $table->timestamps();

            $table->index(['kindergarten_id', 'email_normalized']);
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('kindergarten_id')->nullable();
            $table->string('name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('email_normalized', 255)->nullable();
            $table->string('role', 20)->nullable();
            $table->string('token_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->ulid('created_by_staff_id')->nullable();
            $table->ulid('accepted_staff_id')->nullable();
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
