<?php

/**
 * Enforcement correspondence — City of Kigali Inspection Unit.
 *
 * Letters follow the format used by the unit: a reference line, the
 * addressee, a subject, a sequence of paragraphs each opening with
 * "Dushingiye ku…" citing the authority relied upon, the decision
 * itself, the consequence of non-compliance, and the distribution list.
 *
 * Letters are issued in Kinyarwanda. The wording below is taken from
 * the unit's own correspondence and must be reviewed by the Chief
 * Inspector before any change is published.
 */
return [

    /* ----------------------------------------------------------
       Reference numbering
       Example: 8210/07.01.14/26
       ---------------------------------------------------------- */
    'reference' => [
        'registry_code' => '07.01.14',
        'format'        => ':serial/:registry/:year',
        'start_serial'  => 8211,
    ],

    /* ----------------------------------------------------------
       Legal authority
       ---------------------------------------------------------- */
       
    'clauses' => [
        'legal_basis' => "Dushingiye ku Iteka rya Minisitiri No 03/Cab.M/019 ryo ku wa 15/04/2019 rishyiraho amabwiriza ajyanye n'imitunganyirize y'imijyi n'imyubakire mu mugereka wa II urebana n'amabwiriza y'imyubakire, igice cya 5 mu gika cyawo cya 14, aho ibigo bicuruza lisansi bigomba gukorerwa ubugenzuzi mu rwego rwo kugenzura umutekano w'ikigo n'uw'abagikoresha no kugenzura uburyo buhari bwo gukumira inkongi z'umuriro; Dushingiye ku mabwiriza agenga sitasiyo za lisanse mu Rwanda no: 010/energy/rura/2025 yo ku wa 12/08/2025;",
    ],
    'authority' => [
        'building' => 'Iteka rya Minisitiri No 03/Cab.M/019 ryo ku wa 15/04/2019 '
            . "rishyiraho amabwiriza ajyanye n'imitunganyirize y'imijyi n'imyubakire "
            . 'mu mugereka wa II urebana n\'amabwiriza y\'imyubakire mu gika cyawo cya 14, '
            . 'aho inyubako zikoreshwa zigomba gukorerwa ubugenzuzi mu rwego rwo kugenzura '
            . "umutekano w'inyubako n'uw'abazikoresha no kugenzura uburyo buhari bwo "
            . "gukumira inkongi z'umuriro",

        /* Updated to the wording the unit uses, which cites the
           section as well as the paragraph and adds the RURA regulation. */
        'petrol' => 'Iteka rya Minisitiri No 03/Cab.M/019 ryo ku wa 15/04/2019 '
            . "rishyiraho amabwiriza ajyanye n'imitunganyirize y'imijyi n'imyubakire "
            . "mu mugereka wa II urebana n'amabwiriza y'imyubakire, igice cya 5 "
            . 'mu gika cyawo cya 14, aho ibigo bicuruza lisansi bigomba gukorerwa '
            . "ubugenzuzi mu rwego rwo kugenzura umutekano w'ikigo n'uw'abagikoresha "
            . "no kugenzura uburyo buhari bwo gukumira inkongi z'umuriro; "
            . 'Dushingiye ku mabwiriza agenga sitasiyo za lisanse mu Rwanda '
            . 'no: 010/energy/rura/2025 yo ku wa 12/08/2025',
    ],

    /* ----------------------------------------------------------
       Letter types
       ---------------------------------------------------------- */
    /* ----------------------------------------------------------
       Letter types
       Each carries its own approved .docx — a closure letter and an
       enforcement notice are different documents with different
       required paragraphs, not one document with parts hidden or shown.
       'template' is null where no approved file exists yet: the letter
       type can be defined and selected, but cannot be downloaded until
       the file is provided and placed in storage/app/templates/.
       ---------------------------------------------------------- */
    /* ----------------------------------------------------------
       Letter types
       'template' — the approved .docx that download() fills. Null
       where no file has been provided yet; download refuses cleanly.
       'body' — the sequence of clauses (keys into 'clauses' below)
       used only to assemble the starting text shown in the editor on
       first open. Editing afterward is free text — this list only
       decides what the letter begins as.
       ---------------------------------------------------------- */
    'types' => [

        'enforcement' => [
            'name'     => 'Enforcement Notice',
            'name_rw'  => 'Ibaruwa y\'ubugenzuzi',
            'subject'  => 'Ibisabwa nyuma y\'ubugenzuzi bwakozwe',
            'deadline' => 30,
            'template' => 'letter_no_fine.docx',
            'body'     => ['authority', 'inspection', 'requirements', 'deadline', 'thanks'],
        ],

        'fine' => [
            'name'     => 'Notice of Fine',
            'name_rw'  => 'Ibaruwa y\'ihazabu',
            'subject'  => 'Ihazabu ryatanzwe nyuma y\'ubugenzuzi',
            'deadline' => 30,
            'template' => 'letter_fine.docx',
            'body'     => ['authority', 'inspection', 'fine', 'thanks'],
        ],

        'extension' => [
            'name'     => 'Extension of Deadline',
            'name_rw'  => 'Gusubiza ibaruwa',
            'subject'  => 'Gusubiza ibaruwa yanyu',
            'deadline' => 30,
            'template' => null, // not yet provided
            'body'     => ['authority', 'inspection', 'prior_correspondence', 'extension', 'thanks'],
        ],

        'closure_permanent' => [
            'name'     => 'Notice of Closure (Permanent)',
            'name_rw'  => 'Ibaruwa yo guhagarika ibikorwa burundu',
            'subject'  => 'Guhagarika burundu ibikorwa by\'ikigo',
            'deadline' => 0,
            'template' => 'letter_closure_permanent.docx',
            'body'     => ['authority', 'inspection', 'prior_correspondence', 'closure'],
        ],

        'closure_temporary' => [
            'name'     => 'Notice of Closure (Temporary)',
            'name_rw'  => 'Ibaruwa yo guhagarika ibikorwa by\'igihe gito',
            'subject'  => 'Guhagarika by\'agateganyo ibikorwa by\'ikigo',
            'deadline' => 0,
            'template' => 'letter_closure_temporary.docx',
            'body'     => ['authority', 'inspection', 'prior_correspondence', 'closure_temporary'],
        ],

        'clearance' => [
            'name'     => 'Certificate of Compliance',
            'name_rw'  => 'Icyemezo cyo kubahiriza ibisabwa',
            'subject'  => 'Kubahiriza ibisabwa nyuma y\'ubugenzuzi',
            'deadline' => 0,
            'template' => null, // not yet provided
            'body'     => ['authority', 'inspection', 'clearance', 'thanks'],
        ],
    ],

    /* ----------------------------------------------------------
       Standard paragraphs. Placeholders are replaced at generation.
       ---------------------------------------------------------- */
    'clauses' => [

        'authority' => 'Dushingiye ku :authority;',

        'inspection' => 'Dushingiye ku bugenzuzi bwakozwe ku wa :inspection_date '
            . 'mu nyubako yanyu (:premises) iherereye mu murenge wa :sector '
            . 'mu kibanza gifite UPI: :upi yasuwe bikagaragara ko bimwe mu bikoresho '
            . "nkenerwa byifashishwa mu gukumira inkongi z'umuriro no kurinda umutekano bibura;",

        'requirements' => 'Tubandikiye tubasaba gukosora ibitagenda byose byagaragajwe '
            . 'mu bugenzuzi, mu gihe cy\'iminsi :deadline (:deadline days) '
            . 'uhereye ku munsi wakiriyeho iyi baruwa;',

        'prior_correspondence' => 'Dushingiye ku ibaruwa mwandikiwe ifite Ref No: :prior_ref '
            . 'yo ku wa :prior_date, dushingiye kandi ku ibaruwa yanyu mwatwandikiye '
            . 'yo ku wa :reply_date musaba kongererwa igihe cyo gushyira mu bikorwa '
            . 'ibyo mwasabwe mu ibaruwa yavuzwe haruguru;',

        'extension' => 'Tubandikiye tubamenyesha ko mwongerewe indi minsi :deadline '
            . '(:deadline days) yo kubahiriza ibyo mwasabwe byose nkuko bikubiye '
            . 'mu ibaruwa mwandikiwe ifite Ref No: :prior_ref yo ku wa :prior_date '
            . 'mutabyubahiriza mukazahanwa hakurikijwe Iteka ryavuzwe haruguru;',

        'deadline' => 'Mutabyubahiriza mu gihe cyatanzwe, mukazahanwa hakurikijwe '
            . 'Iteka ryavuzwe haruguru;',

        'closure' => 'Tubandikiye tubamenyesha ko ibikorwa by\'ikigo bihagaritswe '
            . 'kugeza igihe ibyasabwe byose bizaba byubahirijwe, hakurikijwe '
            . 'Iteka ryavuzwe haruguru;',
      
        'closure_temporary' => "[UMWANDIKO NTIWEMEJWE — DRAFT PENDING CHIEF INSPECTOR REVIEW] "
            . "Tubandikiye tubamenyesha ko ibikorwa by'ikigo bihagaritswe by'agateganyo, "
            . "kugeza igihe ibisabwa byose bizaba byubahirijwe. Ibikorwa bizasubukurwa "
            . "gusa nyuma y'ubugenzuzi bwo kwemeza ko ibisabwa byose byujujwe, "
            . "hakurikijwe amategeko.",

        'fine' => "[UMWANDIKO NTIWEMEJWE — DRAFT PENDING CHIEF INSPECTOR REVIEW] "
            . "Tubandikiye tubamenyesha ko, hakurikijwe ibyagaragajwe mu bugenzuzi, "
            . "mwahawe ihazabu ry'amafaranga y'u Rwanda :fine_amount (RWF), "
            . "hashingiwe kuri :fine_legal_basis. Iri hazabu rigomba kwishyurwa "
            . "bitarenze ku wa :fine_due_date, mutabikora mukazahanwa hakurikijwe "
            . "amategeko.",

        'clearance' => 'Tubandikiye tubamenyesha ko nyuma y\'ubugenzuzi bwakozwe, '
            . 'byagaragaye ko ibyasabwe byose byubahirijwe;',

        'thanks' => "Tuboneyeho kubashimira uruhare runini mudahwema kutugaragariza "
            . "mu iterambere ry'Umujyi wa Kigali;",
    ],

    'salutation' => [
        'male'    => 'Bwana;',
        'female'  => 'Madamu;',
        'company' => 'Bwana/Madamu;',
    ],

    'closing' => 'Mugire amahoro.',

    /* ----------------------------------------------------------
       Distribution list. The district line is filled from the
       premises being inspected.
       ---------------------------------------------------------- */
    'copies' => [
        "Bwana Umuyobozi w'Umujyi wa Kigali;",
        "Bwana/Madamu Umuyobozi w'Umujyi wa Kigali Wungirije (Bombi);",
        "Madamu Umuyobozi Mukuru w'Ibikorwa by'Umujyi wa Kigali;",
        "Bwana Umuyobozi Mukuru w'Urwego Ngenzuramikorere;",
        "Bwana Umuyobozi Nshingwabikorwa w'Akarere ka :district;",
        "Bwana Umuyobozi Nshingwabikorwa w'Umurenge wa :sector.",
    ],
    /*
     * Who is copied on an enforcement letter.
     *
     * An enforcement notice is not only between the City and the
     * premises: the list states who else has been told, which is part of
     * the record. :district and :sector are filled from the premises.
     */
    'distribution' => [
        "Bwana Umuyobozi w'Umujyi wa Kigali;",
        "Bwana/Madamu Umuyobozi w'Umujyi wa Kigali Wungirije (Bombi);",
        "Madamu Umuyobozi Mukuru w'Ibikorwa by'Umujyi wa Kigali;",
        "Bwana Umuyobozi Mukuru w'Urwego Ngenzuramikorere;",
        "Bwana Umuyobozi Nshingwabikorwa w'Akarere ka :district;",
        "Bwana Umuyobozi Nshingwabikorwa w'Umurenge wa :sector.",
    ],

    /* ----------------------------------------------------------
       Signatory — the Chief Inspector alone signs correspondence.
       ---------------------------------------------------------- */
    'signatory' => [
        'role'  => 'Chief Inspector',
        'title' => 'Chief Inspector',
    ],
];
