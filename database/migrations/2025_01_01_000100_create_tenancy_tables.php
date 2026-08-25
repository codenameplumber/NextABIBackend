<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy: one database, tenant_id on every domain row, enforced by a global
 * scope on the models. Not database-per-tenant — at the scale this product
 * operates (hundreds of agencies, a few million rows) that would be operational
 * cost with no benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('city')->default('Lagos');
            $t->string('currency', 8)->default('NGN');
            $t->json('settings')->nullable();   // policies set by accepted recommendations
            $t->timestamp('detectors_ran_at')->nullable();
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // 'ceo' | 'sales' | 'finance' | 'agent' — drives insight ranking.
            $t->string('role')->default('ceo')->after('email');
            // A client user is a buyer, not staff. They see the portal only.
            $t->foreignId('customer_id')->nullable()->after('role');
            $t->boolean('is_client')->default(false)->after('customer_id');
            $t->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tenant_id');
            $t->dropColumn(['role', 'customer_id', 'is_client']);
        });
        Schema::dropIfExists('tenants');
    }
};
