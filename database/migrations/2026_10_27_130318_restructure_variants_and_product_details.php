<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Variantes : couleur + taille (peut déjà exister)
        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'color')) {
                $table->string('color')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('product_variants', 'size')) {
                $table->string('size')->nullable()->after('color');
            }
        });

        // Images : liées à une couleur (peut déjà exister)
        if (!Schema::hasColumn('product_images', 'color')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->string('color')->nullable()->after('product_id');
            });
        }

        // Produits : sur commande + fiche détaillée
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'on_order')) {
                $table->boolean('on_order')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('products', 'lead_time_days')) {
                $table->unsignedSmallInteger('lead_time_days')->nullable()->after('on_order');
            }
            if (!Schema::hasColumn('products', 'details')) {
                $table->json('details')->nullable()->after('lead_time_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['on_order', 'lead_time_days', 'details']);
        });
    }
};