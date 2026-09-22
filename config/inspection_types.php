<?php

/**
 * Inspection categories.
 *
 * Single source of truth for the sidebar, the router and the per-type
 * dashboards. Adding a category is an entry here — no new controller,
 * route or view is required.
 *
 * legacy_id maps to entity_types.id in the existing database. Categories
 * without one have no historical records yet.
 */
return [

    'groups' => [

        'fire' => [
            'label' => 'Fire Prevention',
            'types' => [
                'building' => [
                    'name'      => 'Occupied Building',
                    'short'     => 'Buildings',
                    'legacy_id' => 1,
                    'live'      => true,
                    'blurb'     => 'Fire safety, electrical installation and security assessment of occupied buildings.',
                ],
                'petrol' => [
                    'name'      => 'Petrol Station',
                    'short'     => 'Petrol Stations',
                    'legacy_id' => 2,
                    'live'      => true,
                    'blurb'     => 'Siting, separation distances, firefighting systems, licensing and insurance.',
                ],
                'community_education' => [
                    'name'      => 'Community Education',
                    'short'     => 'Community Education',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Fire prevention awareness sessions delivered to communities, schools and businesses.',
                ],
            ],
        ],

        'building' => [
            'label' => 'Building Inspection',
            'types' => [
                'construction' => [
                    'name'      => 'Ongoing Construction',
                    'short'     => 'Construction',
                    'legacy_id' => null,
                    'live'      => true,
                    'blurb'     => 'Compliance of works in progress against the approved permit.',
                ],
                'desk_review' => [
                    'name'      => 'Desk Review',
                    'short'     => 'Desk Review',
                    'legacy_id' => null,
                    'live'      => true,
                    'blurb'     => 'Review of building permit applications and approvals.',
                ],
                'housing_profile' => [
                    'name'      => 'Housing Profile Monitoring',
                    'short'     => 'Housing',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Monitoring of housing stock condition and profile.',
                ],
                'drone_operation' => [
                    'name'      => 'Drone Operation',
                    'short'     => 'Drone',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Aerial survey of sites and structures, with imagery attached to the inspection record.',
                ],
            ],
        ],

        'environment' => [
            'label' => 'Environment Protection',
            'types' => [
                'waste_water' => [
                    'name'      => 'Wastewater Management Inspection',
                    'short'     => 'Wastewater',
                    'legacy_id' => null,
                    'live'      => true,
                    'blurb'     => 'Wastewater system condition, discharge quality and compliance.',
                ],
                'solid_waste' => [
                    'name'      => 'Solid Waste Collection',
                    'short'     => 'Solid Waste',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Collection coverage, transfer points and disposal compliance.',
                ],
                'public_spaces' => [
                    'name'      => 'Public Spaces',
                    'short'     => 'Public Spaces',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Condition, cleanliness and permitted use of parks, squares and open public areas.',
                ],
                'wetland' => [
                    'name'      => 'Wetland',
                    'short'     => 'Wetland',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Protection of wetland buffers, encroachment and unauthorised development.',
                ],
                'quarries_mining' => [
                    'name'      => 'Quarries & Mining Sites',
                    'short'     => 'Quarries & Mining',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Extraction site safety, rehabilitation obligations and environmental compliance.',
                ],
                'illegal_dumping' => [
                    'name'      => 'Illegal Dumping Monitoring',
                    'short'     => 'Illegal Dumping',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Identification and monitoring of unauthorised waste dumping sites.',
                ],
            ],
        ],

        'road' => [
            'label' => 'Roads & Utilities',
            'types' => [
                
                'road_register' => [
                    'name'      => 'Road Register',
                    'short'     => 'Corridor',
                    'legacy_id' => null,
                    'live'      => true,
                    'blurb'     => 'Roads condition, encroachment and drainage.',
                ],
                
                'road_inspection' => [
                    'name'      => 'Road Inspection',
                    'short'     => 'Corridor',
                    'legacy_id' => null,
                    'live'      => true,
                    'blurb'     => 'Roads condition, encroachment and drainage.',
                ],
                'street_light' => [
                    'name'      => 'Street Light Inspection',
                    'short'     => 'Street Lights',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Section-by-section survey of street lighting.',
                ],
            ],
        ],

        'enforcement' => [
            'label' => 'Enforcement',
            'types' => [
                'fines' => [
                    'name'      => 'Fines',
                    'short'     => 'Fines',
                    'legacy_id' => null,
                    'live'      => false,
                    'blurb'     => 'Penalties issued, confirmation status and settlement.',
                ],
            ],
        ],
    ],

    /*
     * Which checklist sections count as siting and which as operations.
     *
     * A siting failure was settled when the premises was built — plot
     * size, distance from housing, distance from a power line — and
     * cannot be corrected by better housekeeping. An operational one
     * usually can: an extinguisher can be bought, a licence renewed.
     *
     * The distinction decides whether a letter asks for improvement or
     * for closure, so it is recorded here rather than inferred.
     */
    'section_groups' => [

        'petrol' => [
            // 1 Plot size · 2 Road safety · 3 Distance from housing
            // 4 Distance from power line · 5 Distance from sensitive areas
            // 13 Tank capacity
            'siting'     => ['1', '2', '3', '4', '5', '13'],

            // 6 Firefighting · 7 Warning signs · 8 Electrical · 9 Sanitary
            // 10 Permitting · 11 Retail licence · 12 Insurance
            // 14 Zoning compliance · 15 Kitchen and bar
            'operations' => ['6', '7', '8', '9', '10', '11', '12', '14', '15'],
        ],

        /* For an occupied building the useful split is what can be put
           right before a deadline and what cannot. Permits and fixed
           installations take months; extinguishers and signage do not. */
        'building' => [
            'siting'     => ['1', '3'],   // documents, electrical and mechanical
            'operations' => ['2', '4'],   // equipment, signage, kitchen practice
        ],
    ],
    
    
    /*
     * The two upper side panels on an activity dashboard.
     *
     * What is worth showing differs by category. For a petrol station,
     * siting against operations decides whether a letter asks for
     * improvement or for closure — a separation distance cannot be
     * corrected by housekeeping. For an occupied building nearly
     * everything is correctable, so the useful comparison is between
     * districts and between premises.
     *
     * Available: 'siting', 'operations', 'districts', 'ranking'.
     * A category not listed falls back to districts and ranking.
     */
    'panels' => [
        'petrol'   => ['siting', 'operations'],
        'building' => ['districts', 'ranking'],
    ],

];