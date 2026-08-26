<?php

use App\Domain\Notifications\Enums\DeliveryStatus;
use App\Domain\Notifications\Enums\Priority;
use App\Domain\Notifications\Models\Notification;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('priority')->default(Priority::DEFAULT)->index();
            $table->text('message');
            $table->string('idempotency_key')->nullable();
            $table->unique(['idempotency_key', 'channel']);
            $table->timestamps();
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Notification::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('status')->default(DeliveryStatus::DEFAULT)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }
};
