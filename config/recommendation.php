<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Co-occurrence for Item-Based CF
    |--------------------------------------------------------------------------
    |
    | Pasangan produk hanya disimpan jika jumlah pembeli bersama (intersection)
    | mencapai ambang ini. Default penelitian = 2. Turunkan via .env jika
    | dataset demonstrasi terlalu kecil.
    |
    */

    'min_co_occurrence' => (int) env('CF_MIN_CO_OCCURRENCE', 2),

];
