<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up()
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->text('description')->nullable()->after('photo_path');
            $table->string('category', 50)->nullable()->after('unit');
            $table->string('type', 50)->nullable()->after('category');
            $table->index(['category', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['category', 'type']);
            $table->dropColumn(['category', 'type']);
        });
    }
};
