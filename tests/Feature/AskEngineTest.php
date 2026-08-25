<?php

namespace Tests\Feature;

use App\Ai\AskEngine;
use App\Engine\InsightEngine;
use App\Engine\Metrics;
use Tests\TestCase;

/**
 * The intent router.
 *
 * Two properties matter more than breadth. First, the parse has to be right —
 * "top 5 agents by revenue" ranks agents, not revenue. Second, a question it
 * cannot answer has to come back as "I can't answer that", never as a
 * confident wrong number.
 */
class AskEngineTest extends TestCase
{
    private function ask(string $q): array
    {
        return (new AskEngine(
            $this->tenant,
            Metrics::for($this->tenant),
            InsightEngine::for($this->tenant),
        ))->ask($q);
    }

    public function test_it_separates_the_subject_from_the_metric(): void
    {
        $a = $this->ask('Top 5 agents by revenue');

        $this->assertSame('ranking', $a['parsed']['intent']);
        $this->assertSame('agent', $a['parsed']['subject']);
        $this->assertStringContainsString('agents', $a['headline']);
        $this->assertNotEmpty($a['table']['rows']);
    }

    public function test_specific_metrics_beat_generic_ones(): void
    {
        // 'leads' would swallow this if the patterns were ordered carelessly,
        // and the router would confidently answer a different question.
        $this->assertSame('stale', $this->ask('how many stale leads do we have?')['parsed']['metric']);
        $this->assertSame('conversion', $this->ask('what is our win rate?')['parsed']['metric']);
        $this->assertSame('leads', $this->ask('how many leads came in?')['parsed']['metric']);
    }

    public function test_it_reads_a_time_window(): void
    {
        $this->assertSame(30, $this->ask('revenue in the last 30 days')['parsed']['days']);
        $this->assertSame(365, $this->ask('revenue this year')['parsed']['days']);
        $this->assertSame(90, $this->ask('what is our revenue?')['parsed']['days']);
    }

    public function test_it_reverses_direction_for_worst(): void
    {
        $this->assertSame('asc', $this->ask('worst campaigns by ROAS')['parsed']['direction']);
        $this->assertSame('desc', $this->ask('best campaigns by ROAS')['parsed']['direction']);
    }

    public function test_it_answers_the_money_questions_with_real_figures(): void
    {
        $overdue = $this->ask('How much is overdue?');
        $finance = Metrics::for($this->tenant)->finance();

        $this->assertSame('overdue', $overdue['parsed']['metric']);
        $this->assertSame(\App\Support\Fmt::moneyShort($finance['overdueAmount']), $overdue['headline']);
        $this->assertNotNull($overdue['drill']);
    }

    public function test_risk_returns_the_open_findings(): void
    {
        InsightEngine::for($this->tenant)->run();
        $a = $this->ask('What am I at risk of?');

        $this->assertSame('risk', $a['parsed']['intent']);
        $this->assertNotEmpty($a['items']);
    }

    public function test_a_dimension_question_returns_a_breakdown(): void
    {
        $a = $this->ask('Revenue by location');

        $this->assertSame('location', $a['parsed']['dim']);
        $this->assertStringContainsString('Breakdown', $a['headline']);
        $this->assertNotEmpty($a['table']['rows']);
    }

    public function test_a_forecast_says_how_little_it_knows(): void
    {
        $a = $this->ask('Forecast next month');

        $this->assertSame('forecast', $a['parsed']['intent']);
        // A linear fit on six points is a direction, not a number, and the
        // card has to carry that rather than imply precision.
        $this->assertLessThan(0.7, $a['confidence']);
        $this->assertStringContainsString('linear projection', $a['narrative']);
    }

    public function test_it_refuses_rather_than_guesses(): void
    {
        $a = $this->ask('what is the weather in Lagos tomorrow');

        $this->assertSame('unknown', $a['kind']);
        $this->assertLessThan(0.3, $a['confidence']);
        $this->assertNotEmpty($a['items'], 'It refused without offering anything it can do.');
    }

    public function test_every_suggested_question_actually_answers(): void
    {
        InsightEngine::for($this->tenant)->run();

        $engine = new AskEngine(
            $this->tenant,
            Metrics::for($this->tenant),
            InsightEngine::for($this->tenant),
        );

        foreach ($engine->suggestions() as $question) {
            $a = $engine->ask($question);

            $this->assertNotSame(
                'unknown',
                $a['kind'],
                "The product suggests \"{$question}\" and then cannot answer it.",
            );
            $this->assertNotEmpty($a['headline']);
        }
    }
}
