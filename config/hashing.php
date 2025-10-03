<?php

return [

    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds' => 12,
    ],

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
    ],

];