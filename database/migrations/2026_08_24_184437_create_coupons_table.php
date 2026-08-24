<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // 'pourcentage' ou 'montant_fixe'
            $table->decimal('value', 10, 2); // ex: 10 (pour 10%) ou 15.00 (pour 15€ de réduction)
            $table->decimal('min_purchase', 10, 2)->nullable(); // montant minimum d'achat pour utiliser le coupon
            $table->integer('usage_limit')->nullable(); // nombre max d'utilisations total (null = illimité)
            $table->integer('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
