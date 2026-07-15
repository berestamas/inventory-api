<?php

declare(strict_types=1);

dataset('invalid attach fields', [
    'device null' => ['device', null],
    'device empty' => ['device', ''],
    'device not a uuid' => ['device', 'not-a-uuid'],
    'device not a string' => ['device', ['not', 'a', 'string']],
    'serial_number null' => ['serial_number', null],
    'serial_number empty' => ['serial_number', ''],
    'serial_number not a string' => ['serial_number', ['not', 'a', 'string']],
    'serial_number too long' => ['serial_number', str_repeat('A', 256)],
    'serial_number with space' => ['serial_number', 'SN 001'],
    'serial_number with slash' => ['serial_number', 'SN/001'],
    'serial_number with hash' => ['serial_number', 'SN#001'],
    'serial_number with plus' => ['serial_number', 'SN+001'],
    'serial_number with question mark' => ['serial_number', 'SN?001'],
]);

dataset('valid attach serial boundaries', [
    'serial_number max length' => ['serial_number', str_repeat('A', 255)],
    'serial_number with allowed specials' => ['serial_number', 'SN_2026.01:X-9'],
]);

dataset('required attach fields', [
    'device' => ['device'],
    'serial_number' => ['serial_number'],
]);
