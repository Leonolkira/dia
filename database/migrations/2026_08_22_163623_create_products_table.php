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
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->decimal('compare_price', 10, 2)->nullable(); // prix barré (promo)
        $table->integer('stock')->default(0);
        $table->string('sku')->unique()->nullable(); // référence produit
        $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // le vendeur qui a créé le produit
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
