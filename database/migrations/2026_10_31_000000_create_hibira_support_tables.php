<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hibira_support_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('token_hash', 64);
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('status', 16)->default('open');
            $table->string('handled_by', 16)->default('assistant');
            $table->string('assigned_to')->nullable();
            $table->unsignedInteger('unread')->default(0);
            $table->unsignedInteger('ai_replies')->default(0);
            $table->text('ai_draft')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('hibira_support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('hibira_support_conversations')->cascadeOnDelete();
            $table->string('sender', 16);
            $table->string('user_id')->nullable();
            $table->string('author')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('hibira_support_settings', function (Blueprint $table) {
            $table->id();
            $table->string('ai_mode', 16)->default('off');
            $table->string('provider', 32)->default('openai');
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->text('instructions')->nullable();
            $table->longText('knowledge')->nullable();
            $table->unsignedInteger('daily_limit')->default(200);
            $table->string('title')->nullable();
            $table->string('greeting', 500)->nullable();
            $table->string('color', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hibira_support_messages');
        Schema::dropIfExists('hibira_support_conversations');
        Schema::dropIfExists('hibira_support_settings');
    }
};
