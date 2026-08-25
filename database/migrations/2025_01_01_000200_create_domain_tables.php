<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The nine business entities.
 *
 * Indexing note: almost every detector filters on tenant_id plus a status and a
 * date. The composite indexes below are taken from the actual WHERE clauses in
 * App\Engine\Metrics rather than added speculatively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('role')->default('Sales Agent');
            $t->string('team')->nullable();
            $t->string('tier')->default('core');            // star | core | struggling
            $t->decimal('skill', 4, 3)->default(0.600);
            $t->boolean('active')->default(true);
            $t->boolean('coaching')->default(false);
            $t->unsignedBigInteger('mentor_id')->nullable();
            $t->timestamp('joined_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'active']);
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('channel');
            $t->string('source');
            $t->string('location')->nullable();
            $t->decimal('spend', 16, 2)->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->timestamp('start_at');
            $t->timestamp('end_at')->nullable();
            $t->string('status')->default('active');        // active | paused | completed
            $t->timestamp('paused_at')->nullable();
            $t->string('planted')->nullable();              // audit trail for demo data
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('ref')->index();
            $t->string('title');
            $t->string('type');
            $t->string('location');
            $t->string('tier')->nullable();
            $t->unsignedTinyInteger('bedrooms')->default(0);
            $t->unsignedInteger('size_sqm')->default(0);
            $t->decimal('price', 16, 2);
            $t->decimal('prior_price', 16, 2)->nullable();
            $t->string('status')->default('available');     // available|reserved|sold|off_market
            $t->timestamp('listed_at');
            $t->timestamp('sold_at')->nullable();
            $t->unsignedInteger('days_on_market')->default(0);
            $t->unsignedInteger('views')->default(0);
            $t->unsignedInteger('inquiries')->default(0);
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('promoted')->default(false);
            $t->timestamp('repriced_at')->nullable();
            $t->string('planted')->nullable();
            $t->timestamps();
            // The demand-gap detector groups by exactly these three columns.
            $t->index(['tenant_id', 'location', 'type', 'bedrooms'], 'prop_segment_idx');
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('since')->nullable();
            $t->decimal('ltv', 16, 2)->default(0);
            $t->string('segment')->default('standard');     // vip|high_value|standard|entry
            $t->unsignedInteger('rank')->nullable();
            $t->decimal('satisfaction', 3, 2)->nullable();
            $t->string('source')->nullable();
            $t->boolean('escalated')->default(false);
            $t->timestamp('escalated_at')->nullable();
            $t->string('recovery_status')->nullable();
            $t->timestamp('recovery_opened_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'segment']);
            $t->index(['tenant_id', 'ltv']);
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('source');
            $t->string('channel')->nullable();
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status')->default('new');
            /**
             * Deepest stage ever reached. Current status is not enough: a lead
             * that got to a site visit and was then lost still passed through
             * viewing, and a funnel that forgets that flatters every conversion
             * figure in the product.
             */
            $t->string('max_stage')->default('new');
            $t->unsignedTinyInteger('score')->default(50);
            $t->decimal('budget', 16, 2)->default(0);
            $t->string('wants_location')->nullable();
            $t->string('wants_type')->nullable();
            $t->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('matched_property_id')->nullable();
            $t->boolean('is_open')->default(true);
            $t->timestamp('last_contact_at')->nullable();
            $t->timestamp('converted_at')->nullable();
            $t->timestamp('follow_up_at')->nullable();
            $t->timestamp('reassigned_at')->nullable();
            $t->timestamp('rescored_at')->nullable();
            $t->string('lost_reason')->nullable();
            $t->string('follow_up_note')->nullable();
            $t->string('planted')->nullable();
            $t->boolean('imported')->default(false);
            $t->timestamps();
            // The stagnation detector: open leads ordered by last contact.
            $t->index(['tenant_id', 'is_open', 'last_contact_at'], 'lead_stale_idx');
            $t->index(['tenant_id', 'source']);
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('deals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('value', 16, 2)->default(0);
            $t->decimal('commission', 16, 2)->default(0);
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->unsignedInteger('cycle_days')->default(0);
            $t->string('source')->nullable();
            $t->string('location')->nullable();
            $t->string('property_type')->nullable();
            $t->boolean('won')->default(false);
            $t->string('lost_reason')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'won', 'closed_at'], 'deal_won_idx');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('deal_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 16, 2);
            $t->unsignedSmallInteger('instalment')->default(1);
            $t->unsignedSmallInteger('of_total')->default(1);
            $t->timestamp('due_at');
            $t->timestamp('paid_at')->nullable();
            /**
             * Stored status is a snapshot. Everything the user sees is derived
             * from due_at against the current time, because a stored status
             * goes stale the moment the clock moves and an instalment ends up
             * claiming to be scheduled three weeks after it was due.
             */
            $t->string('status')->default('scheduled');   // paid|overdue|pending|partial|scheduled
            $t->string('method')->nullable();
            $t->unsignedSmallInteger('reminders')->default(0);
            $t->timestamp('reminded_at')->nullable();
            $t->boolean('restructured')->default(false);
            $t->timestamp('original_due_at')->nullable();
            $t->string('planted')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'due_at'], 'pay_status_idx');
            $t->index(['tenant_id', 'customer_id']);
        });

        Schema::create('feedbacks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('rating')->default(3);
            $t->decimal('sentiment', 4, 2)->default(0);
            $t->text('body');
            $t->json('tags')->nullable();
            $t->string('channel')->nullable();
            $t->string('planted')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
            $t->index(['tenant_id', 'rating']);
        });

        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('entity_type')->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('at');
            $t->string('note')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'at']);
            $t->index(['entity_type', 'entity_id']);
        });

        /**
         * Event-sourcing the stage changes. This is what makes an honest funnel
         * possible: cohort conversion by acquisition week, and deepest-stage
         * reached without having to trust a denormalised column.
         */
        Schema::create('lead_stage_transitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->string('from_stage')->nullable();
            $t->string('to_stage');
            $t->timestamp('at');
            $t->index(['tenant_id', 'lead_id']);
            $t->index(['tenant_id', 'to_stage', 'at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'lead_stage_transitions', 'activities', 'feedbacks', 'payments',
            'deals', 'leads', 'customers', 'properties', 'campaigns', 'agents',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
