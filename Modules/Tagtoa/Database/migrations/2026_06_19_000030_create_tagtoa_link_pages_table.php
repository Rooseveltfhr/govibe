<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_link_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vcard_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('alias')->unique();
            $table->text('bio')->nullable();
            $table->string('theme', 20)->default('dark');
            $table->string('avatar_path')->nullable();
            $table->string('donation_label')->nullable();
            $table->unsignedBigInteger('pay_page_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_link_pages');
    }
};
