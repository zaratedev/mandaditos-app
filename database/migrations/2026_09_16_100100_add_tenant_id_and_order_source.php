<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables that belong to a business. Kept nullable for now so the existing
     * single-business data keeps working; the switch to NOT NULL + a global tenant
     * scope is a hardening step for the multi-tenant SaaS phase (§18, Fase 3c).
     *
     * @var list<string>
     */
    private array $tenantTables = ['users', 'clients', 'addresses', 'orders'];

    public function up(): void
    {
        foreach ($this->tenantTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('businesses')->nullOnDelete();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status');
            // A portal order has no staff creator, so the column has to allow null.
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        foreach ($this->tenantTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
