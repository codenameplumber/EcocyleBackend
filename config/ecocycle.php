<?php

return [
    // The split applied to every verified batch. Must sum to 1.0 --
    // SettlementService asserts it, because drift here is money.
    'household_share' => (float) env('ECOCYCLE_HOUSEHOLD_SHARE', 0.60),
    'agent_share'     => (float) env('ECOCYCLE_AGENT_SHARE', 0.40),

    // Declared-vs-verified variance beyond this raises an integrity flag.
    'variance_tolerance' => (float) env('ECOCYCLE_VARIANCE_TOLERANCE', 5.0),

    // A new area opens once this many households register within its radius.
    'area_activation_threshold' => 200,

    'claude' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
    ],

    'facility' => [
        'name'    => 'EcoCycle Lagos Facility',
        'address' => 'Plot 14, Ojota Industrial Layout, Lagos',
        'lat'     => 6.5794,
        'lng'     => 3.3792,
    ],
];
