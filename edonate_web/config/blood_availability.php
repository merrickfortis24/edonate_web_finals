<?php

return [
    /*
     * Approximate barangay-level reference points used only when every matching
     * legacy location row lacks valid coordinates. Markers are further rounded
     * before being returned by the aggregate map API.
     *
     * Pangao coordinates source: https://www.philatlas.com/luzon/r04a/batangas/lipa/pangao.html
     * The Philippine Statistics Authority lists Pangao as a City of Lipa barangay:
     * https://psa.gov.ph/classification/psgc/barangays/0401014000
     */
    'barangay_map_references' => [
        [
            'barangay' => 'Pangao',
            'cities' => ['City of Lipa', 'Lipa City', 'Lipa'],
            'province' => 'Batangas',
            'latitude' => 13.9171,
            'longitude' => 121.1237,
        ],
    ],
];
