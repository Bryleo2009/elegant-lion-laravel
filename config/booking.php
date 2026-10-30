<?php
return [
    'days_ahead' => (int) env('BOOKING_DAYS_AHEAD', 3),
    'notice_minutes' => (int) env('BOOKING_NOTICE_MINUTES', 60),
    'interval_minutes' => 30,
    'slot_minutes' => 15,
];
