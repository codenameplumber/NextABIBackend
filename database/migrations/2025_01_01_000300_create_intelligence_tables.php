<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The intelligence layer: what the detectors found, what to do about it, and
 * what was actually done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            /**
             * Stable identity across detector runs. Without it, a dismissed
             * finding resurrects every fifteen minutes and the product becomes
             * something people learn to ignore. Hashed from
             * tenant + detector + entity key, and upserted on.
             */
            $t->string('fingerprint', 64);

            $t->string('detector');                 // staleLeads, campaignBurn, ...
            $t->string('archetype');                // threshold|trend|anomaly|cohort|...
            $t->string('area');                     // sales|leads|properties|...
            $t->string('title', 500);
            $t->text('narrative')->nullable();      // filled lazily by the AI layer
            $t->string('narrative_hash', 64)->nullable();

            $t->json('facts');                      // everything the narrator needs
            $t->json('evidence');                   // label/value/drill triples

            $t->decimal('severity', 4, 3)->default(0);
            $t->decimal('confidence', 4, 3)->default(0);
            $t->decimal('priority', 6, 4)->default(0);
            $t->string('band');                     // critical|high|medium|low

            $t->string('status')->default('new');   // new|acknowledged|actioned|dismissed
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            /**
             * Stamped when a run stops emitting the signal. Gives the product a
             * history of what was wrong and how long it took to fix — a report
             * the client-side prototype could never produce.
             */
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('dismissed_at')->nullable();
            $t->timestamp('actioned_at')->nullable();
            $t->timestamps();

            $t->unique(['tenant_id', 'fingerprint']);
            $t->index(['tenant_id', 'status', 'priority'], 'insight_feed_idx');
            $t->index(['tenant_id', 'area']);
        });

        Schema::create('recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('insight_id')->constrained()->cascadeOnDelete();
            $t->string('key');                      // stable per insight, survives re-runs
            $t->string('label', 500);
            $t->text('rationale')->nullable();
            $t->string('effort')->default('medium');   // low|medium|high
            $t->decimal('impact', 16, 2)->default(0);
            $t->boolean('is_primary')->default(false);
            $t->string('action_type');               // maps to an App\Engine\Actions handler
            $t->json('payload');
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'key']);
            $t->index(['insight_id', 'is_primary']);
        });

        Schema::create('action_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('insight_id')->nullable()->constrained()->nullOnDelete();
            $t->string('recommendation_key')->nullable();
            $t->string('action_type');
            $t->string('label', 500);
            $t->string('message', 500);
            $t->string('before')->nullable();        // the diff is the proof the loop closed
            $t->string('after')->nullable();
            $t->string('detail', 500)->nullable();
            $t->string('area')->nullable();
            $t->unsignedInteger('affected')->default(0);
            $t->decimal('impact', 16, 2)->default(0);
            /**
             * Guards against a double-click applying an action twice. The
             * client-side prototype had no protection here at all.
             */
            $t->string('idempotency_key', 64)->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'idempotency_key']);
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference')->unique();
            $t->string('topic');
            $t->text('body');
            $t->string('status')->default('open');   // open|answered|closed
            $t->text('reply')->nullable();
            $t->timestamp('replied_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['support_tickets', 'action_logs', 'recommendations', 'insights'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
