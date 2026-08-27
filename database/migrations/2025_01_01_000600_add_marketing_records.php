<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing you have to be told about.
 *
 * Everything the engine measured until now arrived with a tracking link
 * attached: a Meta campaign has clicks, a portal listing has impressions, and
 * the lead that came from either carries a campaign_id. That is the half of
 * Nigerian property marketing that a database can see by itself.
 *
 * The other half cannot be seen at all. A billboard on Lekki-Epe Expressway, a
 * radio read on Wazobia, an announcement at a church — these produce buyers and
 * leave no click. Before this migration the only trace was the buyer later
 * saying "billboard" on the registration form, which measures the OUTCOME but
 * never the SPEND, so the return on it was structurally uncomputable.
 *
 * Two pairs of columns fix that, and they are deliberately separate:
 *
 *   expected_*  what you thought it would produce, recorded when you booked it
 *   actual_*    what it produced, recorded by hand once you know
 *
 * Keeping the expectation is the point. A campaign that returned 40 leads is
 * a success or a failure depending entirely on whether you were promised 30 or
 * 400, and an agency that only stores the outcome can never tell which of its
 * suppliers oversells. It also gives the detectors something they could not
 * previously have: a baseline that is not drawn from the other campaigns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $t) {
            // What it was sold to you as.
            $t->unsignedInteger('expected_leads')->nullable()->after('clicks');
            $t->decimal('expected_revenue', 16, 2)->nullable()->after('expected_leads');

            // What actually came back. Null means "not counted yet", which is
            // a different thing from zero and has to stay distinguishable —
            // zero leads is a finding, an uncounted campaign is a chore.
            $t->unsignedInteger('actual_leads')->nullable()->after('expected_revenue');
            $t->decimal('actual_revenue', 16, 2)->nullable()->after('actual_leads');

            /*
             | Hand-entered rather than tracked.
             |
             | The metrics layer attributes leads through campaign_id, which an
             | offline record will never have. This flag is what lets marketing
             | figures prefer the recorded numbers for those rows and the
             | attributed ones everywhere else, without guessing from whether a
             | column happens to be null.
             */
            $t->boolean('manual')->default(false)->after('planted');
            $t->index(['tenant_id', 'manual']);
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'manual']);
            $t->dropColumn([
                'expected_leads', 'expected_revenue',
                'actual_leads', 'actual_revenue', 'manual',
            ]);
        });
    }
};
