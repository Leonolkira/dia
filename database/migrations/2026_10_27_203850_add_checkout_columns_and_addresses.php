<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('orders', function (Blueprint $table) {
        $table->string('delivery_mode', 20)->default('livraison')->after('payment_status');
        $table->string('shipping_name')->nullable()->after('delivery_mode');
        $table->decimal('shipping_fee', 10, 2)->default(0)->after('discount');
        $table->decimal('deposit_amount', 10, 2)->default(0)->after('shipping_fee');
    });

    Schema::create('addresses', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('label', 30)->default('Maison');
        $table->string('street');
        $table->string('city', 100);
        $table->string('phone', 30)->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('addresses');
    Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['delivery_mode', 'shipping_name', 'shipping_fee', 'deposit_amount']));
}
};
