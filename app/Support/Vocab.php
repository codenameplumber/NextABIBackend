<?php

namespace App\Support;

/**
 * The vocabulary the simulator draws from, and the conditions it deliberately
 * writes into the data.
 *
 * NARRATIVES is the audit trail. Each entry is a condition planted on purpose,
 * paired with the detector expected to rediscover it. The detectors are never
 * told any of this — delete the failing campaign rows and its insight
 * disappears on its own, which is the whole difference between a computed
 * product and a decorated one.
 */
final class Vocab
{
    /**
     * The name pool has to be bigger than the book.
     *
     * At 4,500 leads a 2,200-combination pool collides constantly and the
     * simulator's fallback starts appending digits — "Chidi Okafor 7" — which
     * is the single detail that makes a dataset read as generated. 128 × 96 is
     * roughly 12,000 combinations against a book of about 5,000 people.
     *
     * Igbo, Yoruba and Hausa names in roughly the proportions a Lagos agency's
     * client book actually carries.
     */
    public const FIRST = [
        'Chidi', 'Adaeze', 'Emeka', 'Ngozi', 'Tunde', 'Folake', 'Bola', 'Yemi', 'Kunle', 'Amaka',
        'Ifeanyi', 'Zainab', 'Musa', 'Aisha', 'Segun', 'Bimpe', 'Obinna', 'Chioma', 'Femi', 'Tolu',
        'Uche', 'Nneka', 'Ibrahim', 'Halima', 'Damilola', 'Kemi', 'Seun', 'Ayo', 'Chinedu', 'Blessing',
        'Gbenga', 'Ronke', 'Ahmed', 'Fatima', 'Olumide', 'Temitope', 'Ikenna', 'Grace', 'Sadiq',
        'Maryam', 'Bayo', 'Titi', 'Nnamdi', 'Ejiro', 'Hassan', 'Rukayat', 'Dele', 'Sade', 'Chinaza',
        'Kelechi', 'Abiodun', 'Nkechi', 'Wale', 'Bisi', 'Osaze',
        'Chukwuemeka', 'Oluwaseun', 'Adanna', 'Babatunde', 'Ifechukwu', 'Olamide', 'Ugochi',
        'Abdulrahman', 'Chinelo', 'Oluwatobi', 'Ezinne', 'Adewale', 'Nkiru', 'Kayode', 'Chiamaka',
        'Omolara', 'Ifunanya', 'Sikiru', 'Onyinye', 'Taiwo', 'Kehinde', 'Adaobi', 'Lanre',
        'Chidinma', 'Aminu', 'Simisola', 'Obiageli', 'Rotimi', 'Hauwa', 'Chukwudi', 'Yetunde',
        'Ngozika', 'Jide', 'Amina', 'Somtochukwu', 'Bukola', 'Ikechukwu', 'Aderonke', 'Nasir',
        'Chinyere', 'Oluwadamilare', 'Ebele', 'Toyin', 'Suleiman', 'Ahunna', 'Bode', 'Zubaida',
        'Chidubem', 'Morenike', 'Ifeoma', 'Sesan', 'Halimat', 'Obiora', 'Folasade', 'Uzoamaka',
        'Akin', 'Rashidat', 'Chibuzo', 'Iyabo', 'Garba', 'Adaeke', 'Niyi', 'Sekinat',
        'Chimezie', 'Bolanle', 'Umar', 'Nwabueze', 'Modupe', 'Idris', 'Chiazor', 'Doyin',
    ];

    public const LAST = [
        'Okafor', 'Adeyemi', 'Nwosu', 'Balogun', 'Eze', 'Ogunleye', 'Abubakar', 'Chukwu', 'Bello',
        'Olawale', 'Ibrahim', 'Okonkwo', 'Adebayo', 'Mohammed', 'Uzoma', 'Fashola', 'Ndukwe', 'Salami',
        'Anyanwu', 'Oyelaran', 'Danjuma', 'Iwu', 'Akinola', 'Nwachukwu', 'Lawal', 'Obi', 'Sanusi',
        'Ajayi', 'Onyeka', 'Yusuf', 'Emenike', 'Adeleke', 'Nwankwo', 'Oladipo', 'Afolabi', 'Chukwuma',
        'Bakare', 'Ezeugo', 'Aliyu', 'Okoro',
        'Adesanya', 'Nnaji', 'Oyewole', 'Madu', 'Sowande', 'Igwe', 'Bamidele', 'Okeke', 'Alabi',
        'Ugochukwu', 'Oduya', 'Nwaogu', 'Aregbesola', 'Ekwueme', 'Ojo', 'Ilesanmi', 'Onwuka',
        'Adeniyi', 'Nzeribe', 'Fagbemi', 'Chiedu', 'Soyinka', 'Agbaje', 'Nwosuagwu', 'Odunlami',
        'Ezenwa', 'Falana', 'Obialor', 'Shittu', 'Amadi', 'Ogunbiyi', 'Njoku', 'Adegoke', 'Iheanacho',
        'Oyinlola', 'Achebe', 'Fawehinmi', 'Ogbonna', 'Akindele', 'Nweke', 'Olaniyan', 'Ihedioha',
        'Sotomi', 'Anozie', 'Babangida', 'Umeh', 'Ademola', 'Chinwuba', 'Fasanya', 'Nwadike',
        'Olatunbosun', 'Ezeani', 'Giwa', 'Onuoha', 'Adeoye', 'Mbah', 'Sulaimon', 'Okwuosa',
    ];

    /** Location tiers drive price, demand and days-on-market. */
    public const LOCATIONS = [
        ['name' => 'Banana Island',   'tier' => 'ultra',   'base' => 900000000, 'demand' => 0.45, 'w' => 3],
        ['name' => 'Ikoyi',           'tier' => 'ultra',   'base' => 520000000, 'demand' => 0.60, 'w' => 6],
        ['name' => 'Victoria Island', 'tier' => 'premium', 'base' => 340000000, 'demand' => 0.72, 'w' => 9],
        ['name' => 'Lekki Phase 1',   'tier' => 'premium', 'base' => 210000000, 'demand' => 0.95, 'w' => 14],
        ['name' => 'Lekki',           'tier' => 'mid',     'base' => 118000000, 'demand' => 1.28, 'w' => 20],
        ['name' => 'Chevron',         'tier' => 'mid',     'base' => 96000000,  'demand' => 1.05, 'w' => 11],
        ['name' => 'Ajah',            'tier' => 'value',   'base' => 62000000,  'demand' => 1.12, 'w' => 13],
        ['name' => 'Ikeja GRA',       'tier' => 'premium', 'base' => 175000000, 'demand' => 0.82, 'w' => 8],
        ['name' => 'Magodo',          'tier' => 'mid',     'base' => 104000000, 'demand' => 0.88, 'w' => 7],
        ['name' => 'Gbagada',         'tier' => 'mid',     'base' => 88000000,  'demand' => 0.79, 'w' => 5],
        ['name' => 'Yaba',            'tier' => 'value',   'base' => 71000000,  'demand' => 0.94, 'w' => 4],
    ];

    public const PROP_TYPES = [
        ['name' => 'Detached Duplex',      'mult' => 1.65, 'beds' => [4, 5, 6], 'w' => 14],
        ['name' => 'Semi-Detached Duplex', 'mult' => 1.28, 'beds' => [3, 4, 5], 'w' => 16],
        ['name' => 'Terraced Duplex',      'mult' => 1.05, 'beds' => [3, 4],    'w' => 15],
        ['name' => 'Apartment',            'mult' => 0.78, 'beds' => [1, 2, 3], 'w' => 24],
        ['name' => 'Penthouse',            'mult' => 2.10, 'beds' => [3, 4],    'w' => 5],
        ['name' => 'Bungalow',             'mult' => 0.85, 'beds' => [2, 3, 4], 'w' => 8],
        ['name' => 'Land',                 'mult' => 0.42, 'beds' => [0],       'w' => 10],
        ['name' => 'Office Space',         'mult' => 1.15, 'beds' => [0],       'w' => 5],
        ['name' => 'Shortlet',             'mult' => 0.55, 'beds' => [1, 2, 3], 'w' => 3],
    ];

    /** Channel quality is deliberately uneven — this is what cohort detectors find. */
    public const SOURCES = [
        ['name' => 'Referral',        'quality' => 1.85, 'w' => 12, 'channel' => 'Referral'],
        ['name' => 'Website',         'quality' => 1.15, 'w' => 16, 'channel' => 'Organic'],
        ['name' => 'Instagram',       'quality' => 1.05, 'w' => 18, 'channel' => 'Social'],
        ['name' => 'Google Ads',      'quality' => 1.22, 'w' => 14, 'channel' => 'Paid Search'],
        ['name' => 'Facebook',        'quality' => 0.86, 'w' => 15, 'channel' => 'Social'],
        ['name' => 'Property Portal', 'quality' => 0.92, 'w' => 11, 'channel' => 'Marketplace'],
        ['name' => 'WhatsApp',        'quality' => 1.32, 'w' => 8,  'channel' => 'Direct'],
        ['name' => 'Walk-in',         'quality' => 1.55, 'w' => 4,  'channel' => 'Direct'],
        ['name' => 'Billboard',       'quality' => 0.62, 'w' => 2,  'channel' => 'Offline'],
    ];

    public const STAGES = ['new', 'contacted', 'qualified', 'viewing', 'negotiating', 'won', 'lost'];

    public const LOST_REASONS = [
        'Budget mismatch', 'Bought elsewhere', 'Financing declined', 'Went cold',
        'Location not suitable', 'Timing - postponed', 'Title concerns',
    ];

    public const FEEDBACK_TAGS = [
        'response time', 'agent knowledge', 'property condition', 'pricing',
        'documentation', 'site visit', 'communication', 'handover',
        'payment process', 'after-sales',
    ];

    public const POSITIVE_FB = [
        'Agent was extremely responsive and knew the estate inside out.',
        'Smooth process end to end. Documentation was ready before I even asked.',
        'Site visit was well organised, and the property matched the listing exactly.',
        'Handover was clean. No hidden charges, no surprises.',
        'They understood my budget and did not waste my time on the wrong properties.',
        'Payment plan was flexible and clearly explained upfront.',
    ];

    public const NEGATIVE_FB = [
        'Took four days to get a callback after I paid the inspection fee.',
        'Nobody responded to my WhatsApp messages for almost a week.',
        'Response time is the weak point - everything else was fine.',
        'I had to chase the agent repeatedly for the survey documents.',
        'Slow response after the deposit. Communication dropped off completely.',
        'Called three times before anyone picked up. Very frustrating.',
        'The listed price was not the final price. Felt misled.',
        'Property condition was not as advertised in the photos.',
    ];

    public const NEUTRAL_FB = [
        'Process was okay overall, a few delays on documentation.',
        'Decent experience. Agent could have been more proactive.',
        'Property was fine but the paperwork took longer than promised.',
    ];

    /**
     * The seven planted conditions. Change a number here and the corresponding
     * insight changes on screen — nothing is hardcoded in the UI.
     */
    public const NARRATIVES = [
        /**
         * Expressed as a SHARE of the open book, not a count.
         *
         * The stale detector compares the backlog against a baseline of 6% of
         * open leads, so a fixed 47 stops being a finding the moment the book
         * grows — which is exactly what happened when this dataset went from
         * 1,400 leads to 4,500. A share keeps the condition true at any size.
         */
        'STALE_LEADS' => [
            'openShare' => 0.19, 'minCount' => 40, 'minDays' => 15, 'maxDays' => 41,
            'detector' => 'stagnation', 'area' => 'leads',
            'note' => 'Open, high-budget leads with no contact activity for 15+ days.',
        ],
        /**
         * spendMult is relative to the AVERAGE campaign, and the detector
         * ignores anything under 3.5% of total spend — correctly, because a
         * campaign nobody is really funding is not a finding. Across ~120
         * campaigns that means a burn has to be roughly six times the average
         * before it deserves the word.
         */
        'CAMPAIGN_BURN' => [
            'name' => 'Meta - Lagos Mainland Push', 'spendMult' => 7.5, 'convMult' => 0.28,
            'detector' => 'cohort', 'area' => 'marketing',
            'note' => 'Heavy spend, poor conversion versus the channel baseline.',
        ],
        'DEMAND_GAP' => [
            'location' => 'Lekki', 'type' => 'Apartment', 'beds' => 3,
            'inquiryMult' => 6.4, 'stockCap' => 4,
            'detector' => 'concentration', 'area' => 'properties',
            'note' => 'Inquiries per available listing far above portfolio baseline.',
        ],
        'PAYMENT_RISK' => [
            'accounts' => 6, 'minOverdueDays' => 32, 'whaleIndex' => 0,
            'detector' => 'threshold', 'area' => 'finance',
            'note' => 'Overdue instalments past 30 days, including one top-10 account.',
        ],
        /**
         * Also a share, for the same reason — and the detector will not call a
         * theme a complaint unless at least 45% of the reviews carrying it are
         * one or two stars. Organic reviews pick tags at random and are mostly
         * positive, so at four times the review volume the planted cluster has
         * to be both larger and angrier to survive the dilution.
         */
        'FEEDBACK_CLUSTER' => [
            'tag' => 'response time', 'windowShare' => 0.42, 'minCount' => 22,
            'negativeWeights' => [46, 44, 10], 'windowDays' => 60,
            'detector' => 'anomaly', 'area' => 'feedback',
            'note' => 'Recurring complaint tag concentrated in the last 60 days.',
        ],
        // Scaled with the team: 20 stars and 26 strugglers out of 120 keeps the
        // same roughly one-in-five / one-in-four shape the cohort detector was
        // tuned against, rather than two people carrying the whole signal.
        'AGENT_DIVERGENCE' => [
            'stars' => 20, 'strugglers' => 26, 'starClose' => 0.62, 'strugglerClose' => 0.31,
            'detector' => 'cohort', 'area' => 'sales',
            'note' => 'Two agent cohorts with materially different close rates on similar leads.',
        ],
        /**
         * Weekly cohorts in this window are small — five to fourteen leads
         * reach a viewing in a given week — so a mild demotion rate disappears
         * into sampling noise once the series is smoothed, and the detector
         * correctly declines to call it. The planted drop is therefore steep
         * enough to be a real signal rather than a plausible-looking one.
         */
        'FUNNEL_EROSION' => [
            'stage' => 'viewing to negotiating', 'windowWeeks' => 13,
            'dropTo' => 0.20, 'baseline' => 0.71,
            'detector' => 'funnel', 'area' => 'sales',
            'note' => 'Stage conversion decaying over the trailing 13 weeks.',
        ],
    ];

    /**
     * Status -> badge kind, shared across the lead, deal, property and payment
     * tables so one word never wears two colours in one product.
     */
    public const STATUS_KIND = [
        'new' => 'info', 'contacted' => '', 'qualified' => 'info', 'viewing' => 'medium',
        'negotiating' => 'ai', 'won' => 'pos', 'lost' => 'neg',
        'available' => 'pos', 'reserved' => 'medium', 'sold' => '', 'off_market' => 'low',
        'paid' => 'pos', 'pending' => 'medium', 'partial' => 'medium',
        'overdue' => 'neg', 'scheduled' => '', 'due' => 'medium',
        'active' => 'pos', 'paused' => 'neg', 'completed' => '',
        'open' => 'medium', 'resolved' => 'pos', 'closed' => '',
    ];

    public static function statusKind(?string $status): string
    {
        return self::STATUS_KIND[$status] ?? '';
    }

    /** Timeline glyph for an activity type. */
    public static function activityIcon(string $type): string
    {
        return [
            'lead_created' => 'plus', 'call' => 'ask', 'whatsapp' => 'feedback',
            'email' => 'mail', 'site_visit' => 'property', 'note' => 'reports',
            'deal_won' => 'check', 'deal_lost' => 'x', 'followup_scheduled' => 'clock',
            'payment' => 'finance', 'reassigned' => 'agent',
        ][$type] ?? 'info';
    }

    /**
     * Seasonality for Nigerian real estate: Q4 and Q1 are strong (diaspora
     * buying, year-end bonuses), the Q3 rainy season is soft.
     */
    public static function seasonality(int $timestamp): float
    {
        $month = (int) date('n', $timestamp) - 1;

        return [1.06, 1.02, 1.00, 0.97, 0.95, 0.92, 0.88, 0.90, 0.98, 1.08, 1.16, 1.22][$month];
    }
}
