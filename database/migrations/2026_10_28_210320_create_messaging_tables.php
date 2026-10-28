<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('custom_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['from_admin', 'read_at']);
        });

        // Validité des devis sur mesure
        Schema::table('custom_requests', function (Blueprint $table) {
            $table->timestamp('valid_until')->nullable()->after('quoted_at');
        });
    }

    public function down(): void
    {
        Schema::table('custom_requests', fn (Blueprint $table) => $table->dropColumn('valid_until'));
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};