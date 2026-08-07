<?php

return [
    'default_low_stock_threshold' => max(0, (int) env('BLOOD_INVENTORY_LOW_STOCK_THRESHOLD', 5)),
];
