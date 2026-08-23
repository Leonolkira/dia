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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // le client
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete(); // le vendeur concerné
            $table->string('status')->default('en_attente'); // en_attente, confirmee, expediee, livree, annulee
            $table->string('payment_method'); // en_ligne, whatsapp
            $table->string('payment_status')->default('non_paye'); // non_paye, paye, echoue
            $table->decimal('total', 10, 2);
            $table->string('shipping_address');
            $table->string('shipping_city');
            $table->string('shipping_phone');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
