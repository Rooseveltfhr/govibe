<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modèles de contrats réutilisables
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 60); // coworking | erp | formation | developpement | nda | autre
            $table->text('description')->nullable();
            $table->longText('body'); // HTML avec {{variables}} ex: {{client_name}}, {{start_date}}
            $table->json('variables')->nullable(); // ['client_name','start_date','montant',...]
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Abonnements clients aux plans
        Schema::create('client_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('plan_slug', 40);   // starter | business | ong | academy | enterprise
            $table->string('plan_name', 80);
            $table->string('billing_cycle', 10)->default('monthly'); // monthly | yearly
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled', 'trial'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        // Ajout colonnes sur contracts existant
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()
                ->after('client_id')
                ->constrained('contract_templates')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('signed_at');
            $table->foreignId('sent_by')->nullable()
                ->after('sent_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->after('sent_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropConstrainedForeignId('sent_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['sent_at']);
        });
        Schema::dropIfExists('client_subscriptions');
        Schema::dropIfExists('contract_templates');
    }
};
