<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            // client_id was cascadeOnDelete, which meant deleting a client would take
            // its whole order history with it. Nothing ever triggered it because the
            // RESTRICT on address_id happened to block the delete first, but that is
            // protection by accident. Say it out loud instead.
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
