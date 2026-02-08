<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
