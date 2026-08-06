<?php

use Database\Migrations\Support\HistoryTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HistoryTrigger;

    private const TABLE = 'orders';

    private const HISTORY_COLUMNS = [
        'id', 'guardian_id', 'kindergarten_id', 'status', 'total_amount', 'platform_fee_amount',
        'stripe_payment_intent_id', 'stripe_checkout_session_id', 'created_at', 'updated_at',
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('guardian_id')->constrained('guardians');
            $table->foreignUlid('kindergarten_id')->constrained('kindergartens');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->integer('total_amount');
            $table->integer('platform_fee_amount');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->timestamps();
        });

        Schema::create(self::TABLE.'_histories', function (Blueprint $table) {
            $table->ulid('id')->index();
            $table->ulid('guardian_id')->nullable();
            $table->ulid('kindergarten_id')->nullable();
            $table->string('status', 20)->nullable();
            $table->integer('total_amount')->nullable();
            $table->integer('platform_fee_amount')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
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
