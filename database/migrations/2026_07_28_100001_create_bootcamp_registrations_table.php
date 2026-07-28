<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bootcamp_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('profession', 120)->nullable();
            $table->string('organization', 120)->nullable();
            $table->string('module_choice')->default('premium'); // module_1..7 | premium
            $table->string('payment_method', 30);               // moncash | natcash | paypal | zelle | unibank | chase
            $table->string('payment_proof_path')->nullable();    // uploaded file
            $table->text('motivation')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bootcamp_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('method', 30)->unique();
            $table->string('label');
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bootcamp_registrations');
        Schema::dropIfExists('bootcamp_payment_settings');
    }
};
