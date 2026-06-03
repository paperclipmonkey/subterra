<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cave rescue / 999 reference, keyed by cave "region" tag
|--------------------------------------------------------------------------
|
| Drives the Rescue Protocol script a duty officer reads during a real
| incident. The region is resolved from the cave's region tag (Tag with
| category = "region"); the keys below MUST match those tag names.
|
| In the UK you dial 999, ask for the POLICE, then ask the police for CAVE
| RESCUE (be explicit it is cave rescue, NOT mountain rescue). Team names are
| from the British Cave Rescue Council member list. Where a region spans
| several police forces the "note" explains how to handle it.
|
*/

return [

    'procedure' => [
        'Dial 999 (or 112) and ask for the POLICE.',
        'Ask the police for CAVE RESCUE — be explicit it is cave rescue, NOT mountain rescue.',
        'Give the cave/location, the number and condition of people, and any injuries.',
        'Stay by the phone — a rescue team member will call you back.',
    ],

    // Used when the cave has no recognised region tag.
    'default' => [
        'police_force' => null,
        'rescue_team' => 'your regional cave rescue team',
        'rescue_abbr' => null,
        'note' => 'Region unknown — ask the caller which area the cave is in, then request that area\'s police force and cave rescue team.',
    ],

    'regions' => [
        'Mendip' => [
            'police_force' => 'Avon and Somerset Police',
            'rescue_team' => 'Mendip Cave Rescue',
            'rescue_abbr' => 'MCR',
            'note' => null,
        ],
        'Yorkshire' => [
            'police_force' => 'North Yorkshire Police',
            'rescue_team' => 'Cave Rescue Organisation',
            'rescue_abbr' => 'CRO',
            'note' => 'The Dales straddle three forces — near Leck Fell it may be Cumbria or Lancashire Police. Give a precise location and let the 999 operator route the call.',
        ],
        'Peak District' => [
            'police_force' => 'Derbyshire Constabulary',
            'rescue_team' => 'Derbyshire Cave Rescue Organisation',
            'rescue_abbr' => 'DCRO',
            'note' => null,
        ],
        'South Wales' => [
            'police_force' => 'Dyfed-Powys Police',
            'rescue_team' => 'South & Mid Wales Cave Rescue Team',
            'rescue_abbr' => 'SMWCRT',
            'note' => 'Covers three forces — Dyfed-Powys (the main cave systems), South Wales Police and Gwent Police for eastern/valley sites.',
        ],
        'North Wales' => [
            'police_force' => 'North Wales Police',
            'rescue_team' => 'North Wales Cave Rescue Organisation',
            'rescue_abbr' => 'NWCRO',
            'note' => null,
        ],
        'Devon' => [
            'police_force' => 'Devon and Cornwall Police',
            'rescue_team' => 'Devon Cave Rescue Organisation',
            'rescue_abbr' => 'DevCRO',
            'note' => null,
        ],
        'Forest of Dean' => [
            'police_force' => 'Gloucestershire Constabulary',
            'rescue_team' => 'Gloucestershire Cave Rescue Group',
            'rescue_abbr' => 'GCRG',
            'note' => null,
        ],
        'Scotland' => [
            'police_force' => 'Police Scotland',
            'rescue_team' => 'Scottish Cave Rescue Organisation',
            'rescue_abbr' => 'SCRO',
            'note' => 'Police may task the local Mountain Rescue Team first and place SCRO on standby — still ask for cave rescue.',
        ],
        'Portland' => [
            'police_force' => 'Dorset Police',
            'rescue_team' => 'Mendip Cave Rescue',
            'rescue_abbr' => 'MCR',
            'note' => 'Portland sea caves can be tidal — for a sea cave, also ask the 999 operator for HM Coastguard.',
        ],
    ],

];
