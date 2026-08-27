<?php

namespace App\Ai;

use App\Models\Insight;
use App\Support\Fmt;

/**
 * Deterministic narration — no model required.
 *
 * Grammar-based templates with the real, computed numbers written into them.
 * With several phrasings per detector and figures injected live, this reads
 * genuinely well, and it is what answers when OpenAI is not configured or is
 * having a bad day.
 *
 * Doubles as the fallback for every OpenAI failure path, which is why it must
 * never itself throw.
 */
class TemplateNarrator implements NarratorContract
{
    public function name(): string
    {
        return 'NexABI Local Engine';
    }

    public function isLive(): bool
    {
        return false;
    }

    /** Stable variant choice — the same insight always reads the same way. */
    private function variant(string $key, array $options): string
    {
        return $options[crc32($key) % count($options)];
    }

    private function b(string $s): string
    {
        return '**'.$s.'**';
    }

    public function narrate(Insight $insight): string
    {
        $f = $insight->facts ?? [];
        $b = fn ($s) => $this->b($s);

        try {
            return match ($insight->detector) {

                'staleLeads' => $this->variant('stale'.$insight->id, [
                    $b(Fmt::num($f['count'])).' open leads carrying '.$b(Fmt::moneyShort($f['pipeline']))
                    .' of pipeline have had no contact for two weeks or more. That is '
                    .$b(Fmt::mult($f['multiple'])).' your normal backlog of '.Fmt::num($f['baseline'])
                    .'. The oldest has been sitting for '.$b($f['oldestDays'].' days')
                    .', and their average lead score is '.$b((string) $f['avgScore'])
                    .' — these are not junk leads, they are qualified ones going cold. '
                    .'On historical conversion, roughly '.$b(Fmt::moneyShort($f['estLoss']))
                    .' of that pipeline is at risk.',

                    'You have a follow-up problem, not a lead-generation problem. '
                    .$b(Fmt::num($f['count'])).' qualified leads — average score '
                    .$b((string) $f['avgScore']).', '.$b(Fmt::moneyShort($f['pipeline']))
                    .' in combined budget — have gone untouched past the 14-day mark. '
                    .'Normal for your volume is '.Fmt::num($f['baseline']).'. Left alone, history '
                    .'says about '.$b(Fmt::moneyShort($f['estLoss'])).' of this walks.',
                ]),

                'hotUnworked' => $b(Fmt::num($f['count'])).' of your highest-scoring open leads — '
                    .'average score '.$b((string) $f['avgScore']).', '.$b(Fmt::moneyShort($f['value']))
                    .' in combined budget — have not been contacted in '.$b($f['avgDays'].' days')
                    .'. These are the leads most likely to close, sitting idle.',

                'sourceQuality' => $b($f['source']).' accounts for '.$b(Fmt::pct($f['share'], 0))
                    .' of your lead volume — '.$b(Fmt::num($f['leads'])).' leads — but converts at '
                    .$b(Fmt::pct($f['conversion'], 1)).' against a median of '
                    .$b(Fmt::pct($f['median'], 1)).'. That is '.$b(Fmt::mult($f['multiple']))
                    .' worse. '.$b($f['bestSource']).' converts at '
                    .$b(Fmt::pct($f['bestConversion'], 1)).'. Agent capacity spent on '
                    .$f['source'].' is capacity not spent on channels that close.',

                'campaignBurn' => $this->variant('burn'.$insight->id, [
                    $b($f['name']).' has consumed '.$b(Fmt::moneyShort($f['spend'])).' and produced '
                    .$b(Fmt::num($f['leads'])).' leads, of which '.$b(Fmt::num($f['won']))
                    .' closed. That is a '.$b(Fmt::pct($f['conversion'], 1)).' conversion rate against a '
                    .$b(Fmt::pct($f['baseline'], 1)).' baseline across your other campaigns — '
                    .$b(Fmt::mult($f['multiple'])).' worse. Cost per lead is '
                    .$b(Fmt::money(round($f['cpl']))).' versus '.$b(Fmt::money(round($f['baselineCpl'])))
                    .' elsewhere. Roughly '.$b(Fmt::moneyShort($f['wasted'])).' of this budget is not working.',

                    'The numbers on '.$b($f['name']).' do not hold up. '.$b(Fmt::moneyShort($f['spend']))
                    .' spent, '.$b(Fmt::num($f['leads'])).' leads, '.$b(Fmt::num($f['won'])).' closed. '
                    .'Your other campaigns convert at '.$b(Fmt::pct($f['baseline'], 1))
                    .'; this one manages '.$b(Fmt::pct($f['conversion'], 1)).'. At '
                    .$b(number_format($f['roas'], 2).'×').' return on spend it is destroying value, '
                    .'and taking '.$b(Fmt::pct($f['spendShare'], 0)).' of total marketing spend to do it.',
                ]),

                'demandGap' => $b($f['bedrooms'].'-bed '.$f['type'].'s in '.$f['location'])
                    .' are pulling '.$b(Fmt::num($f['inquiries'])).' inquiries against just '
                    .$b(Fmt::num($f['available'])).' available '
                    .($f['available'] === 1 ? 'unit' : 'units').' — '
                    .$b(number_format($f['demandIndex'], 0)).' inquiries per unit, where your portfolio '
                    .'norm is '.$b(number_format($f['baseline'], 1)).'. That is '
                    .$b(Fmt::mult($f['multiple'])).' normal demand. Units here clear in '
                    .$b(round($f['avgDom']).' days').' on average. You are priced for a market that '
                    .'no longer exists in this segment.',

                'staleInventory' => $b(Fmt::num($f['count'])).' listings worth '
                    .$b(Fmt::moneyShort($f['value'])).' have been on the market more than twice your '
                    .'median of '.$b(round($f['medianDom']).' days').'. That is '
                    .$b(Fmt::pct($f['share'], 0)).' of available stock. The worst is '
                    .$b($f['worstTitle']).' at '.$b($f['worstDays'].' days').'. Carrying this costs '
                    .'approximately '.$b(Fmt::moneyShort($f['carryCost'])).' a month in tied-up capital.',

                'agentDivergence' => $b(Fmt::num($f['laggards'])).' agents are closing at '
                    .$b(Fmt::pct($f['lagRate'], 0)).' while '.$b(Fmt::num($f['leaders']))
                    .' are closing at '.$b(Fmt::pct($f['leadRate'], 0)).' — a '
                    .$b(Fmt::pts($f['gap'], 0)).' gap on comparable lead quality. Between them the '
                    .'lagging cohort holds '.$b(Fmt::num($f['lagLeads'])).' open leads worth '
                    .$b(Fmt::moneyShort($f['lagPipeline'])).'. Moved to top-cohort performance, that '
                    .'pipeline is worth roughly '.$b(Fmt::moneyShort($f['recoverable'])).' more.',

                'funnelErosion' => 'Leads that reach a site visit used to advance to negotiation '
                    .$b(Fmt::pct($f['early'], 0)).' of the time. Over the last '
                    .$b($f['weeks'].' weeks').' that has fallen to '.$b(Fmt::pct($f['recent'], 0))
                    .' — a '.$b(Fmt::pts(-$f['drop'], 0)).' decline. '.$b(Fmt::num($f['dropped']))
                    .' leads have been lost at this specific step. Everything upstream is holding, so '
                    .'the problem is what happens after the viewing, not before it.',

                'revenueTrend' => $f['direction'] === 'down'
                    ? 'Revenue has declined for '.$b($f['months'].' consecutive months')
                      .'. The trailing three-month average is '.$b(Fmt::moneyShort($f['recent']))
                      .' against '.$b(Fmt::moneyShort($f['prior'])).' for the three before that — '
                      .$b(Fmt::delta($f['change'], 0)).'. Win rate is '.$b(Fmt::pct($f['winRate'], 0))
                      .' on '.$b(Fmt::num($f['deals'])).' closed deals.'
                    : 'Revenue is compounding at roughly '
                      .$b(number_format($f['trend'] * 100, 0).'% per month').' across '
                      .$b($f['months'].' months').'. Trailing three-month average is '
                      .$b(Fmt::moneyShort($f['recent'])).', up from '.$b(Fmt::moneyShort($f['prior']))
                      .'. Average deal size '.$b(Fmt::moneyShort($f['avgDeal'])).' — the growth is '
                      .'coming from volume and value together, which is the durable kind.',

                'revenueConcentration' => $b(Fmt::pct($f['concentration'], 0)).' of revenue is produced '
                    .'by '.implode(', ', $f['names']).' — three people out of '
                    .$b(Fmt::num($f['agents'])).'. They have generated '
                    .$b(Fmt::moneyShort($f['revenue'])).' of '.$b(Fmt::moneyShort($f['total']))
                    .'. That is a continuity risk: if any one of them leaves, a third of your revenue '
                    .'leaves with the relationships.',

                'paymentRisk' => $b(Fmt::moneyShort($f['amount'])).' is sitting overdue across '
                    .$b(Fmt::num($f['accounts'])).' accounts and '.$b(Fmt::num($f['instalments']))
                    .' instalments. '.$b($f['topName']).' is the largest exposure — '
                    .$b(Fmt::moneyShort($f['topAmount'])).', '.$b($f['topDays'].' days').' past due, '
                    .'and ranked '.$b('#'.$f['topRank']).' by lifetime value, so this is a relationship '
                    .'issue rather than a collections one. '.$b(Fmt::moneyShort($f['over90']))
                    .' has aged beyond 90 days. Your collection ratio is '
                    .$b(Fmt::pct($f['collectionRatio'], 0)).'.',

                'collectionHealth' => 'Collection ratio is '.$b(Fmt::pct($f['ratio'], 0))
                    .' against a 92% target, with '.$b(Fmt::moneyShort($f['outstanding']))
                    .' outstanding. '.$b(Fmt::moneyShort($f['aging']['90+'])).' has aged beyond 90 '
                    .'days. A further '.$b(Fmt::moneyShort($f['expected30']))
                    .' falls due in the next 30 days.',

                'feedbackCluster' => $b('"'.$f['tag'].'"').' has become your dominant complaint — '
                    .$b(Fmt::num($f['count'])).' mentions in the last 60 days versus '
                    .$b(Fmt::num($f['prev'])).' in the 60 before that. '.$b(Fmt::num($f['negative']))
                    .' of those were one or two stars. Average rating where this tag appears is '
                    .$b(number_format($f['avgRating'], 1)).', against '
                    .$b(number_format($f['overallRating'], 2)).' overall. It is a single, fixable '
                    .'operational issue, and it is dragging the whole satisfaction number.',

                'customerRisk' => $b(Fmt::num($f['count'])).' customers ('
                    .$b(Fmt::pct($f['share'], 0)).' of your base) are at detractor-level satisfaction. '
                    .'They represent '.$b(Fmt::moneyShort($f['value'])).' in closed revenue. '
                    .$b($f['topName']).' is the largest at '.$b(Fmt::moneyShort($f['topLtv']))
                    .'. You have '.$b(Fmt::num($f['promoters'])).' promoters against them.',

                default => $b($insight->title).'. Detected by the '.$insight->archetype
                    .' detector at '.Fmt::pct($insight->confidence, 0).' confidence.',
            };
        } catch (\Throwable) {
            // A missing fact must never blank the card.
            return $this->b($insight->title).'.';
        }
    }

    /**
     * Always null — and that is the correct answer, not a stub.
     *
     * Everything else in this class is a grammar with computed figures written
     * into it, which works precisely because the shape of each sentence is
     * known in advance. A free-form question has no such shape. Pattern-
     * matching one against a fact sheet would produce confident nonsense on
     * the questions it half-recognised, which is worse than the honest card
     * AskEngine already renders when this returns null.
     *
     * So with no model configured, Ask NexABI falls back to saying the data is
     * not there — which is exactly what it did before this method existed.
     */
    public function answer(string $question, array $facts): ?string
    {
        return null;
    }

    public function brief(array $h, array $insights): string
    {
        $b = fn ($s) => $this->b($s);
        $count = count($insights);

        return $b(Fmt::moneyShort($h['revenue'])).' closed across '.$b(Fmt::num($h['deals']))
            .' deals in the last 90 days, '.$b(Fmt::delta($h['revenueChange']))
            .' on the period before. '
            .$b(Fmt::moneyShort($h['pipeline'])).' sits in open pipeline, '
            .$b(Fmt::moneyShort($h['overdue'])).' is overdue, and '.$b(Fmt::num($count)).' '
            .($count === 1 ? 'thing needs' : 'things need').' a decision.';
    }

    /**
     * Written summary.
     *
     * Composed clause by clause from whichever facts were handed over, because
     * the Reports screen scopes them per report — a receivables review is given
     * receivables figures and nothing else. Each clause writes itself only if
     * the numbers behind it are present, so narrowing a report narrows the
     * prose rather than breaking it.
     */
    public function summarise(string $title, string $period, array $h, array $insights): string
    {
        $b = fn ($s) => $this->b($s);
        $has = fn (...$keys) => ! array_diff($keys, array_keys($h));

        $lines = [$b($title).' — '.$period.'.'];

        if ($has('revenue', 'deals')) {
            $line = 'Revenue of '.$b(Fmt::moneyShort($h['revenue'])).' across '
                .$b(Fmt::num($h['deals'])).' closed deals';
            if ($has('revenueChange')) {
                $line .= ', '.$b(Fmt::delta($h['revenueChange'])).' on the prior period';
            }
            if ($has('winRate')) {
                $line .= ', at a '.$b(Fmt::pct($h['winRate'], 0)).' win rate';
            }
            $lines[] = $line.'.';
        }

        if ($has('pipeline')) {
            $line = 'Open pipeline stands at '.$b(Fmt::moneyShort($h['pipeline']));
            $open = $h['openLeads'] ?? $h['open'] ?? null;
            if ($open !== null) {
                $line .= ' over '.$b(Fmt::num($open)).' leads';
            }
            $stale = $h['staleLeads'] ?? $h['stale'] ?? null;
            if ($stale !== null) {
                $line .= ', with '.$b(Fmt::num($stale)).' requiring follow-up';
            }
            $lines[] = $line.'.';
        }

        if ($has('available', 'inventoryValue')) {
            $lines[] = 'Inventory of '.$b(Fmt::num($h['available'])).' available units is valued at '
                .$b(Fmt::moneyShort($h['inventoryValue'])).'.';
        }

        $overdue = $h['overdue'] ?? $h['overdueAmount'] ?? null;
        if ($overdue !== null) {
            $line = 'Receivables show '.$b(Fmt::moneyShort($overdue)).' overdue';
            if ($has('collectionRatio')) {
                $line .= ' against a '.$b(Fmt::pct($h['collectionRatio'], 0)).' collection ratio';
            }
            if ($has('expected30')) {
                $line .= ', with '.$b(Fmt::moneyShort($h['expected30'])).' falling due in the next 30 days';
            }
            $lines[] = $line.'.';
        }

        if ($has('spend', 'roas')) {
            $line = $b(Fmt::moneyShort($h['spend'])).' of campaign spend returned '
                .$b(number_format($h['roas'], 2).'×');
            if ($has('cac')) {
                $line .= ', at '.$b(Fmt::moneyShort($h['cac'])).' per customer acquired';
            }
            $lines[] = $line.'.';
        }

        // Only the headline was handed over and none of the above matched —
        // better a short honest line than a paragraph of nothing.
        if (count($lines) === 1) {
            $lines[] = 'Figures for this period are shown below.';
        }

        if ($insights) {
            $titles = array_map(fn ($i) => $i->title, array_slice($insights, 0, 3));
            $lines[] = 'Matters requiring a decision: '.implode('; ', $titles).'.';
        } else {
            $lines[] = 'No material risks are currently flagged.';
        }

        return implode(' ', $lines);
    }
}
