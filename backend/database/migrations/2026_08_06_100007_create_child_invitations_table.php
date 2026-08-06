<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'child_invitations';

    private const HISTORY_COLUMNS = [
        'id', 'kindergarten_id', 'child_id', 'token_hash', 'label', 'expires_at', 'used_at',
        'used_by_guardian_id', 'revoked_at', 'created_by_staff_id', 'reissued_from_invitation_id',
        'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->foreignUlid('child_id')->constrained('children');
            $table->string('token_hash')->unique();
            $table->string('label', 50);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignUlid('used_by_guardian_id')->nullable()->constrained('guardians');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('created_by_staff_id')->constrained('kindergarten_staff');
            $table->ulid('reissued_from_invitation_id')->nullable();
            $table->timestamps();
        });

        // 自己参照FKは同一CREATE TABLE内では張れないため作成後に追加する
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->foreign('reissued_from_invitation_id')->references('id')->on(self::TABLE);
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('kindergarten_id')->nullable();
            $table->ulid('child_id')->nullable();
            $table->string('token_hash')->nullable();
            $table->string('label', 50)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->ulid('used_by_guardian_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->ulid('created_by_staff_id')->nullable();
            $table->ulid('reissued_from_invitation_id')->nullable();
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
