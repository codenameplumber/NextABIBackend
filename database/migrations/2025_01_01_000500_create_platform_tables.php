<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The pivot: from a console over one agency's data to a platform three
 * different kinds of people sign into.
 *
 * Deliberately additive. Every table keeps the name the metrics layer, the
 * fourteen detectors, the playbooks and the action bus already speak — renaming
 * `agents` to `realtors` across all of that is pure risk for no functional
 * gain. The schema keeps its vocabulary; the product speaks the new one:
 *
 *     agents      -> "Realtor"      gains a login, a code, an approval state
 *     customers   -> "Investor"     gains demographics and a source
 *     deals       -> "Commitment"   gains a lifecycle and a frozen rate
 *     payments    -> "Instalment"   the journey, generated on approval
 *     campaigns   -> "Marketing"    a record of what was run
 *
 * A naming sweep can happen later as its own change, with tests already green.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | Users -----------------------------------------------------------
         |
         | `role` used to carry two meanings at once: which area you belong to
         | AND which lens the insight feed is ranked through. Splitting them is
         | what lets an admin be a "finance" lens while a realtor is not a lens
         | at all. `is_client` stays for now so nothing that reads it breaks
         | mid-migration; `type` is the column to trust.
         */
        Schema::table('users', function (Blueprint $t) {
            $t->string('type')->default('admin')->after('role');   // admin|realtor|investor
            $t->foreignId('agent_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();
            $t->index(['tenant_id', 'type']);
        });

        // Existing rows: buyers become investors, everyone else stays admin.
        DB::table('users')->where('is_client', true)->update(['type' => 'investor']);

        /*
         | Realtors ---------------------------------------------------------
         |
         | The referral code is the spine of the whole product. Every investor
         | is attributed to a realtor, so commission, sales credit and realtor
         | production all fall out of one column instead of being reconstructed
         | after the fact.
         */
        Schema::table('agents', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();
            $t->string('code', 16)->nullable()->after('email');
            $t->string('phone')->nullable()->after('code');
            $t->string('status')->default('approved')->after('phone');  // pending|approved|suspended
            $t->timestamp('approved_at')->nullable()->after('status');
            $t->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();

            // Codes are handed out, typed and pasted. They have to be unique
            // platform-wide, not per tenant, or a pasted link is ambiguous.
            $t->unique('code');
            $t->index(['tenant_id', 'status']);
        });

        /*
         | Investors --------------------------------------------------------
         |
         | agent_id already exists and now means "the realtor who owns this
         | investor". Nullable, and null IS the unassigned queue — someone who
         | arrived without a referral code and is waiting for an admin.
         */
        Schema::table('customers', function (Blueprint $t) {
            $t->string('state')->nullable()->after('phone');
            $t->string('lga')->nullable()->after('state');
            $t->string('income_bracket')->nullable()->after('lga');
            $t->string('occupation')->nullable()->after('income_bracket');
            $t->string('heard_about_us')->nullable()->after('occupation');
            $t->timestamp('assigned_at')->nullable()->after('agent_id');
            $t->foreignId('assigned_by')->nullable()->after('assigned_at')
                ->constrained('users')->nullOnDelete();

            // The unassigned queue is a screen an admin opens every morning.
            $t->index(['tenant_id', 'agent_id']);
            $t->index(['tenant_id', 'heard_about_us']);
        });

        /*
         | Properties -------------------------------------------------------
         |
         | Commission is set per property before it goes up, capped at 15%. The
         | duration bounds exist because the investor chooses their own term —
         | without a ceiling someone picks 240 months on a 45M flat.
         */
        Schema::table('properties', function (Blueprint $t) {
            $t->decimal('commission_rate', 4, 3)->default(0.050)->after('prior_price');
            $t->unsignedSmallInteger('min_duration_months')->default(3)->after('commission_rate');
            $t->unsignedSmallInteger('max_duration_months')->default(24)->after('min_duration_months');
            $t->text('description')->nullable()->after('title');
            $t->json('images')->nullable()->after('description');
            $t->boolean('listed')->default(true)->after('promoted');
        });

        /*
         | Commitments ------------------------------------------------------
         |
         | `won` stays because the sales metrics and several detectors read it,
         | and it keeps meaning what it meant: this one closed. `status` carries
         | the new lifecycle alongside it.
         |
         | commission_rate here is a SNAPSHOT, frozen at approval. It is a
         | contractual figure — editing a property's rate next year must not
         | silently rewrite commissions already earned. That is the exact
         | mirror of the payment-status rule: status must always be live,
         | a rate must always be frozen.
         */
        Schema::table('deals', function (Blueprint $t) {
            $t->string('status')->default('completed')->after('won');
            $t->unsignedSmallInteger('duration_months')->nullable()->after('status');
            $t->decimal('commission_rate', 4, 3)->nullable()->after('commission');
            $t->timestamp('committed_at')->nullable()->after('opened_at');
            $t->timestamp('approved_at')->nullable()->after('committed_at');
            $t->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $t->string('declined_reason')->nullable()->after('lost_reason');
            $t->index(['tenant_id', 'status']);
        });

        // Everything already seeded is historical and settled.
        DB::table('deals')->where('won', true)->update(['status' => 'completed']);
        DB::table('deals')->where('won', false)->update(['status' => 'cancelled']);

        /*
         | Instalments ------------------------------------------------------
         |
         | Payments are recorded by an admin against an offline transfer, so the
         | row needs to say who recorded it and against what reference. Without
         | that a disputed payment has no audit trail.
         */
        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('recorded_by')->nullable()->after('method')
                ->constrained('users')->nullOnDelete();
            $t->timestamp('recorded_at')->nullable()->after('recorded_by');
            $t->string('reference')->nullable()->after('recorded_at');
        });

        /*
         | Leads -------------------------------------------------------------
         |
         | A lead is a questionnaire capture from a realtor's link or an advert.
         | It collects the same shape of information a registration does, minus
         | the account — which is the whole point: someone answering an advert
         | is not ready to choose a password.
         */
        Schema::table('leads', function (Blueprint $t) {
            $t->string('heard_about_us')->nullable()->after('channel');
            $t->string('state')->nullable()->after('phone');
            $t->string('lga')->nullable()->after('state');
            $t->string('income_bracket')->nullable()->after('lga');
            $t->string('occupation')->nullable()->after('income_bracket');
            $t->foreignId('converted_user_id')->nullable()->after('converted_at')
                ->constrained('users')->nullOnDelete();
            $t->index(['tenant_id', 'heard_about_us']);
        });

        /*
         | Feedback ----------------------------------------------------------
         |
         | No longer a menu item, but still collected — asked once the journey
         | is under way and again when it completes. Those two moments are what
         | keep the satisfaction detectors fed.
         */
        Schema::table('feedbacks', function (Blueprint $t) {
            $t->foreignId('deal_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();
            $t->string('trigger')->nullable()->after('channel');  // journey_started|completed
        });

        /*
         | Marketing ---------------------------------------------------------
         |
         | Campaigns become a record of marketing actually done, so they need
         | somewhere to write what it was for and how it went, rather than only
         | what it cost.
         */
        Schema::table('campaigns', function (Blueprint $t) {
            $t->string('objective')->nullable()->after('name');
            $t->text('notes')->nullable()->after('objective');
            $t->foreignId('owner_user_id')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
        });

        /*
         | Promotional materials ---------------------------------------------
         |
         | Admin uploads one base asset. Personalisation happens at download —
         | the realtor's own code and link are stamped in when they take it, so
         | there is one file to maintain rather than one per realtor.
         */
        Schema::create('promotional_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('kind')->default('flyer');       // flyer|image|brochure|video|copy
            $t->text('body')->nullable();               // for copy-and-paste templates
            $t->string('file_path')->nullable();
            $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('active')->default(true);
            $t->unsignedInteger('downloads')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'active']);
        });

        /*
         | Commission payouts -------------------------------------------------
         |
         | Separate from the commitment so Finance can answer "paid" as distinct
         | from "owed". Accrues on approval; an admin marks it paid.
         */
        Schema::create('commission_payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $t->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 16, 2);
            $t->decimal('rate', 4, 3);                  // the frozen rate, carried for the record
            $t->string('status')->default('owed');      // owed|paid|written_off
            $t->timestamp('paid_at')->nullable();
            $t->string('reference')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            // One payout per commitment. Approving twice must not pay twice.
            $t->unique(['tenant_id', 'deal_id']);
            $t->index(['tenant_id', 'agent_id', 'status'], 'payout_agent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('promotional_materials');

        Schema::table('campaigns', function (Blueprint $t) {
            $t->dropConstrainedForeignId('owner_user_id');
            $t->dropColumn(['objective', 'notes']);
        });

        Schema::table('feedbacks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('deal_id');
            $t->dropColumn('trigger');
        });

        Schema::table('leads', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'heard_about_us']);
            $t->dropConstrainedForeignId('converted_user_id');
            $t->dropColumn(['heard_about_us', 'state', 'lga', 'income_bracket', 'occupation']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recorded_by');
            $t->dropColumn(['recorded_at', 'reference']);
        });

        Schema::table('deals', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'status']);
            $t->dropConstrainedForeignId('approved_by');
            $t->dropColumn(['status', 'duration_months', 'commission_rate',
                'committed_at', 'approved_at', 'declined_reason']);
        });

        Schema::table('properties', function (Blueprint $t) {
            $t->dropColumn(['commission_rate', 'min_duration_months', 'max_duration_months',
                'description', 'images', 'listed']);
        });

        Schema::table('customers', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'agent_id']);
            $t->dropIndex(['tenant_id', 'heard_about_us']);
            $t->dropConstrainedForeignId('assigned_by');
            $t->dropColumn(['state', 'lga', 'income_bracket', 'occupation',
                'heard_about_us', 'assigned_at']);
        });

        Schema::table('agents', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'status']);
            $t->dropUnique(['code']);
            $t->dropConstrainedForeignId('approved_by');
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['code', 'phone', 'status', 'approved_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'type']);
            $t->dropConstrainedForeignId('agent_id');
            $t->dropColumn('type');
        });
    }
};
