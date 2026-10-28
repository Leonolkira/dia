<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('color')->nullable()->after('product_id');
            $table->string('size')->nullable()->after('color');
        });

        // On reprend les anciennes lignes
        DB::table('product_variants')->where('attribute', 'couleur')->update(['color' => DB::raw('`value`')]);
        DB::table('product_variants')->whereIn('attribute', ['taille', 'pointure'])->update(['size' => DB::raw('`value`')]);

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['attribute', 'value']);
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('color')->nullable()->after('product_id');
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }

    public function down(): void
    {
        // Pas de retour en arrière : données de test uniquement
    }
};