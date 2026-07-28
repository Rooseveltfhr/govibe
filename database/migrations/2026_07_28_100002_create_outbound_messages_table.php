<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);          // whatsapp | email | sms
            $table->string('recipient');             // phone or email
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();   // email subject / template name
            $table->text('body');
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->text('error')->nullable();
            $table->string('context_type')->nullable();       // BootcampRegistration | Invoice | Booking
            $table->unsignedBigInteger('context_id')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['context_type', 'context_id']);
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('target_audience')->nullable(); // PME, ONG, Freelance…
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->json('features');                // array of feature strings
            $table->json('limits')->nullable();      // {clients: 50, users: 3, …}
            $table->string('color', 20)->default('#DC2626');
            $table->string('badge')->nullable();     // "Populaire", "Recommandé"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('subscription_plans');
    }
};
