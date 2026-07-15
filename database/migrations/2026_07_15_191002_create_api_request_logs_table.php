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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path');
            $table->json('query')->nullable();
            $table->json('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->unsignedSmallInteger('status');
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45);
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
