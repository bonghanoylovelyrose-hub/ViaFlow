<?php

return [
    // Local billing fixtures still participate in billing and DSS. Their
    // simulated water debt must never create collection notices or reminders.
    'local_fixture_consumer_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('BILLING_LOCAL_FIXTURE_CONSUMER_IDS', '')),
    ))),
];
