<?php

return [
    /*
     * Approximate barangay-level reference points used only when every matching
     * legacy location row lacks valid coordinates. Markers are further rounded
     * before being returned by the aggregate map API.
     *
     * Pangao coordinates source: https://www.philatlas.com/luzon/r04a/batangas/lipa/pangao.html
     * Balintawak coordinates source: https://www.philatlas.com/luzon/r04a/batangas/lipa/balintawak.html
     * The Philippine Statistics Authority lists Pangao as a City of Lipa barangay:
     * https://psa.gov.ph/classification/psgc/barangays/0401014000
     * Balintawak coordinates are an approximate barangay-level reference point,
     * not a donor's stored location.
     */
    'barangay_map_references' => [
        [
            'barangay' => 'Pangao',
            'cities' => ['City of Lipa', 'Lipa City', 'Lipa'],
            'province' => 'Batangas',
            'latitude' => 13.9171,
            'longitude' => 121.1237,
        ],
        [
            'barangay' => 'Balintawak',
            'cities' => ['City of Lipa', 'Lipa City', 'Lipa'],
            'province' => 'Batangas',
            'latitude' => 13.9530,
            'longitude' => 121.1588,
        ],
    ],
];
