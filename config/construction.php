<?php

return [

    /*
     * Building categories, as used by the administrative sanctions
     * schedule. The wording must match the schedule exactly: the sanction
     * is found by matching this value against the scope recorded against
     * each fault, so "Category II" would find nothing where the schedule
     * says "Category 2".
     */
    'categories' => [
        'Category 1',
        'Category 2',
        'Category 3',
        'Category 4',
        'Category 5',
    ],

    /*
     * Main corridors.
     *
     * THIS LIST IS A PLACEHOLDER. It follows Kigali's road naming — KN
     * for Nyarugenge, KG for Gasabo, KK for Kicukiro — but the corridors
     * the unit actually treats as main must come from the City, not from
     * a developer's guess. Replace it before this is used in enforcement.
     */
    'corridors' => [
        'KN 1 Rd',
        'KN 3 Rd',
        'KN 5 Rd',
        'KG 9 Ave',
        'KG 11 Ave',
        'KK 15 Rd',
        'KK 19 Ave',
        'RN1 — Kigali to Rusizi',
        'RN3 — Kigali to Rusumo',
        'RN4 — Kigali to Gatuna',
    ],

];
