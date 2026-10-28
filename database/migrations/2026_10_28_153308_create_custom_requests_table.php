<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->string('size_info', 255)->nullable();
            $table->string('color', 50)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->date('desired_date')->nullable();
            $table->json('images')->nullable();
            $table->string('status', 20)->default('en_attente');
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamps();
        });

        // Une commande sur mesure n'a pas de produit du catalogue : product_id doit accepter NULL
        $column = collect(Schema::getColumns('order_items'))->firstWhere('name', 'product_id');

        if ($column && ! $column['nullable']) {
            Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign(['product_id']));
            Schema::table('order_items', fn (Blueprint $table) => $table->unsignedBigInteger('product_id')->nullable()->change());
            Schema::table('order_items', fn (Blueprint $table) => $table->foreign('product_id')->references('id')->on('products')->nullOnDelete());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_requests');
    }
};