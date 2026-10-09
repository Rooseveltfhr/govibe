<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('strategic'); // clé finpo.partner_categories
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('approved'); // pending|approved|rejected
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level')->default('gold');      // clé finpo.sponsor_levels
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('approved'); // pending|approved|rejected
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('booths', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // A-01, B-12…
            $table->string('zone')->default('A');
            $table->string('size')->default('3x3');        // 3x3, 6x3, 9x6…
            $table->unsignedBigInteger('price');           // HTG
            $table->string('status')->default('available'); // available|reserved|sold
            $table->timestamps();
        });

        Schema::create('exhibitors', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('slug')->unique();
            $table->string('sector')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->text('description')->nullable();
            $table->text('products')->nullable();
            $table->text('services')->nullable();
            $table->string('website')->nullable();
            $table->json('socials')->nullable();           // {facebook, instagram, linkedin…}
            $table->string('video_url')->nullable();
            $table->string('brochure_url')->nullable();
            $table->foreignId('booth_id')->nullable()->constrained('booths')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('status')->default('approved'); // pending|approved|rejected
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitors');
        Schema::dropIfExists('booths');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('partners');
    }
};
