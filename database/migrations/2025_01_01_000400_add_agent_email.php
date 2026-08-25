<?php

use App\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agents need an address.
 *
 * A support ticket raised in the client portal is addressed to the buyer's own
 * agent — that is the whole point of naming the agent on the page. Without a
 * column to send to, the notification had nowhere to go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $t) {
            $t->string('email')->nullable()->after('name');
        });

        // Backfill from the name and the agency slug, so existing agencies do
        // not need reseeding to start receiving anything.
        foreach (DB::table('agents')->join('tenants', 'tenants.id', '=', 'agents.tenant_id')
            ->select('agents.id', 'agents.name', 'tenants.slug')->get() as $row) {
            DB::table('agents')->where('id', $row->id)->update([
                'email' => str($row->name)->slug('.').'@'.$row->slug.'.ng',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('agents', fn (Blueprint $t) => $t->dropColumn('email'));
    }
};
