<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The courier dashboard and board always slice a courier's orders by status
     * ("what is still open", "what did I deliver", "what is delivered but unpaid").
     * The foreign key already indexes courier_id on its own, so this adds the status
     * half that those queries actually filter on.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['courier_id', 'status'], 'orders_courier_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // MySQL absorbs the foreign key's own index into this composite one, so the
            // constraint has to get a standalone index back before this one can go.
            $table->index('courier_id', 'orders_courier_id_foreign');
            $table->dropIndex('orders_courier_id_status_index');
        });
    }
};
