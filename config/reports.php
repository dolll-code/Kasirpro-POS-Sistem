<?php

return [
    'expiry_hours' => (int) env('REPORT_EXPORT_EXPIRY_HOURS', 24),
    'sync_threshold' => (int) env('REPORT_EXPORT_SYNC_THRESHOLD', 0),
    'row_limit' => (int) env('REPORT_EXPORT_ROW_LIMIT', 5000),
];
