<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dictionary_items', function (Blueprint $table) {
            $table->dropForeign(['dictionary_type_id']);
            $table->foreign('dictionary_type_id')
                ->references('id')
                ->on('dictionary_types')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dictionary_items', function (Blueprint $table) {
            $table->dropForeign(['dictionary_type_id']);
            $table->foreign('dictionary_type_id')
                ->references('id')
                ->on('dictionary_types')
                ->cascadeOnDelete();
        });
    }
};
