<?php

return [
    'incidents' => [
        // The dashboard's existing 30-day aged-open rule is the default response deadline.
        'response_deadline_days' => (int) env('DSS_INCIDENT_RESPONSE_DEADLINE_DAYS', 30),
        'high_priority' => [
            // Existing hotspot logic used five unresolved reports or at least half
            // of the location's reports remaining open as a backlog signal.
            'unresolved_backlog_count' => (int) env('DSS_INCIDENT_HIGH_BACKLOG_COUNT', 5),
            'unresolved_share_percent' => (float) env('DSS_INCIDENT_HIGH_BACKLOG_SHARE_PERCENT', 50),
            // No recurrence window existed in the workflow; keep this triage rule configurable.
            'recurrence_count' => (int) env('DSS_INCIDENT_RECURRENCE_COUNT', 3),
            'recurrence_window_days' => (int) env('DSS_INCIDENT_RECURRENCE_WINDOW_DAYS', 90),
        ],
    ],
];
