<?php

/**
 * Administrative structure of the City of Kigali.
 *
 * Three districts, 35 sectors, 161 cells. Drives the cascading
 * location selectors on the inspection form, so a district, sector
 * and cell can only ever be recorded in a valid combination.
 *
 * When the National Land Authority parcel API becomes available these
 * fields will be filled by decoding the UPI, and this file becomes the
 * fallback and validation source.
 */
return [

    'Gasabo' => [
        'Bumbogo'    => ['Kinyaga', 'Musave', 'Mvuzo', 'Ngara', 'Nkuzuzu', 'Rwampara'],
        'Gatsata'    => ['Karuruma', 'Nyamabuye', 'Nyamugari'],
        'Gikomero'   => ['Gasagara', 'Gicaca', 'Kibara', 'Munini', 'Murambi'],
        'Gisozi'     => ['Musezero', 'Ruhango'],
        'Jabana'     => ['Akamatamu', 'Bweramvura', 'Kabuye', 'Kidashya', 'Ngiryi'],
        'Jali'       => ['Agateko', 'Buhiza', 'Muko', 'Nkusi', 'Nyabuliba', 'Nyakabungo', 'Nyamitanga'],
        'Kacyiru'    => ['Kamatamu', 'Kamutwa', 'Kibaza'],
        'Kimihurura' => ['Kamukina', 'Kimihurura', 'Rugando'],
        'Kimironko'  => ['Bibare', 'Kibagabaga', 'Nyagatovu'],
        'Kinyinya'   => ['Gacuriro', 'Gasharu', 'Kagugu', 'Murama'],
        'Ndera'      => ['Bwiza', 'Cyaruzunge', 'Kibenga', 'Masoro', 'Mukuyu', 'Rudashya'],
        'Nduba'      => ['Butare', 'Gasanze', 'Gasura', 'Gatunga', 'Muremure', 'Sha', 'Shango'],
        'Remera'     => ['Nyabisindu', 'Nyarutarama', 'Rukiri I', 'Rukiri II'],
        'Rusororo'   => ['Bisenga', 'Gasagara', 'Kabuga 1', 'Kabuga 2', 'Kinyana', 'Mbandazi', 'Nyagahinga', 'Ruhanga'],
        'Rutunga'    => ['Gasabo', 'Kabaliza', 'Kagabiro', 'Kacyatwa', 'Kibenga', 'Ndatemwa'],
    ],

    'Kicukiro' => [
        'Gahanga'    => ['Gahanga', 'Kagasa', 'Karembure', 'Murinja', 'Nunga', 'Rwabutenge'],
        'Gatenga'    => ['Gatenga', 'Karambo', 'Nyanza', 'Nyarurama'],
        'Gikondo'    => ['Kansere', 'Kinunga', 'Kagunga'],
        'Kagarama'   => ['Kanserege', 'Muyange', 'Rukatsa'],
        'Kanombe'    => ['Busanza', 'Karama', 'Kabeza', 'Rubirizi'],
        'Kicukiro'   => ['Gasharu', 'Kagina', 'Kicukiro', 'Ngoma'],
        'Kigarama'   => ['Bwerankori', 'Karugira', 'Kigarama', 'Nyarurama', 'Rwampara'],
        'Masaka'     => ['Ayabaraya', 'Cyimo', 'Gako', 'Gitaraga', 'Mbabe', 'Rusheshe'],
        'Niboye'     => ['Gatare', 'Niboye', 'Nyakabanda'],
        'Nyarugunga' => ['Kamashashi', 'Nonko', 'Rwimbogo'],
    ],

    'Nyarugenge' => [
        'Gitega'     => ['Akabahizi', 'Akabeza', 'Gacyamo', 'Kora', 'Kigarama', 'Kinyange'],
        'Kanyinya'   => ['Nyamweru', 'Nzove', 'Taba'],
        'Kigali'     => ['Kigali', 'Mwendo', 'Nyabugogo', 'Ruriba', 'Rwesero'],
        'Kimisagara' => ['Kamuhoza', 'Katabaro', 'Kimisagara'],
        'Mageragere' => ['Kankuba', 'Kavumu', 'Mataba', 'Ntungamo', 'Nyarufunzo', 'Nyarurenzi', 'Runzenze'],
        'Muhima'     => ['Amahoro', 'Kabasengerezi', 'Kabeza', 'Nyabugogo', 'Rugenge', 'Tetero', 'Ubumwe'],
        'Nyakabanda' => ['Munanira I', 'Munanira II', 'Nyakabanda I', 'Nyakabanda II'],
        'Nyamirambo' => ['Cyivugiza', 'Gasharu', 'Mumena', 'Rugarama'],
        'Nyarugenge' => ['Agatare', 'Biryogo', 'Kiyovu', 'Rwampara'],
        'Rwezamenyo' => ['Kabuguru I', 'Kabuguru II', 'Rwezamenyo I', 'Rwezamenyo II'],
    ],
];
