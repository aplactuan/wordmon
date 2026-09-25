<?php

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
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('username');
            $table->text('application_password');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('wordpress_version')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->string('check_error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
