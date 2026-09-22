<?php

/**
 * Positions held within the CoK Inspection Unit.
 *
 * Recorded against each member of an inspection team so the report is
 * signed by the people who conducted the site visit, under their own
 * names and posts, rather than a generic title.
 *
 * Grouped for a readable selector; the stored value is the position
 * itself, so a report reproduced years later still reads correctly even
 * if the grouping changes.
 */
return [

    'Leadership' => [
        'Chief Inspector',
        'Director of Inspection Unit',
    ],

    'Senior Inspectors' => [
        'Senior Building Inspector',
        'Senior Roads and Utilities Inspector',
        'Hygiene and Sanitation Specialist',
    ],

    'Inspectors' => [
        'Electrical and Mechanical Inspector',
        'Building Inspector',
        'Building and Infrastructure Inspector',
        'Roads and Utilities Inspector',
        'Hygiene, Sanitation and Environment Inspector (HSEI)',
        'Hygiene and Sanitation Officer',
    ],
];
