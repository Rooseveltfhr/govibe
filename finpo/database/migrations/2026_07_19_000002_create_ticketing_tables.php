<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catégories de billets — gérées par l'admin (prix, quota, période de vente…)
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Étudiant, Professionnel, VIP…
            $table->string('slug')->unique();
            $table->string('audience')->default('professional'); // clé finpo.attendee_categories
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');          // en gourdes (0 = gratuit)
            $table->string('currency', 3)->default('HTG');
            $table->unsignedInteger('quota')->nullable(); // null = illimité
            $table->timestamp('sales_start')->nullable();
            $table->timestamp('sales_end')->nullable();
            $table->string('color', 9)->default('#e8b931');
            $table->json('benefits')->nullable();         // liste des avantages
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('percent');   // percent|fixed
            $table->unsignedBigInteger('value');          // % ou montant HTG
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();           // FINPO26-000123
            $table->string('qr_token', 64)->unique();     // jeton du QR code
            $table->foreignId('ticket_category_id')->constrained('ticket_categories')->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('institution')->nullable();
            $table->string('position')->nullable();
            $table->string('country')->default('Haïti');
            $table->string('audience')->default('professional'); // clé finpo.attendee_categories
            $table->string('emergency_contact')->nullable();
            $table->unsignedBigInteger('amount');         // montant final dû (HTG)
            $table->string('currency', 3)->default('HTG');
            $table->string('payment_method')->nullable(); // clé finpo.payment_methods
            $table->string('payment_status')->default('pending'); // pending|paid|free|refunded
            $table->string('status')->default('confirmed');       // confirmed|cancelled
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->index(['email']);
            $table->index(['payment_status', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->string('method');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('HTG');
            $table->string('status')->default('pending'); // pending|paid|failed|refunded
            $table->string('reference')->nullable();      // n° transaction MonCash, Stripe id…
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('checkin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method')->default('qr');      // qr|manual
            $table->string('result')->default('ok');      // ok|already|refused
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->string('number')->unique();           // FINPO26-CERT-000123
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('checkin_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('ticket_categories');
    }
};
