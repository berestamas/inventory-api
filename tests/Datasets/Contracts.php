<?php

declare(strict_types=1);

dataset('invalid contract fields', [
    'contract_number null' => ['contract_number', null],
    'contract_number empty' => ['contract_number', ''],
    'contract_number not a string' => ['contract_number', ['not', 'a', 'string']],
    'contract_number too long' => ['contract_number', str_repeat('a', 256)],
    'partner_name null' => ['partner_name', null],
    'partner_name empty' => ['partner_name', ''],
    'partner_name not a string' => ['partner_name', ['not', 'a', 'string']],
    'partner_name too long' => ['partner_name', str_repeat('a', 256)],
    'description not a string' => ['description', ['not', 'a', 'string']],
    'signed_at not a date' => ['signed_at', 'not-a-date'],
    'signed_at not a string' => ['signed_at', ['not', 'a', 'date']],
    'starts_at not a date' => ['starts_at', 'not-a-date'],
    'ends_at not a date' => ['ends_at', 'not-a-date'],
    'ends_at before starts_at' => ['ends_at', '2026-01-10'],
]);

dataset('valid contract field boundaries', [
    'contract_number max length' => ['contract_number', str_repeat('a', 255)],
    'partner_name unicode' => ['partner_name', 'Ékezetes Partner Bt.'],
    'description null' => ['description', null],
    'signed_at null' => ['signed_at', null],
    'ends_at leap day' => ['ends_at', '2028-02-29'],
    'ends_at equals starts_at' => ['ends_at', '2026-01-15'],
]);

dataset('required contract fields', [
    'contract_number' => ['contract_number'],
    'partner_name' => ['partner_name'],
]);
