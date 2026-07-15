<?php

declare(strict_types=1);

use App\Enums\DeviceCategory;

dataset('invalid device fields', [
    'name null' => ['name', null],
    'name empty' => ['name', ''],
    'name not a string' => ['name', ['not', 'a', 'string']],
    'name too long' => ['name', str_repeat('a', 256)],
    'manufacturer null' => ['manufacturer', null],
    'manufacturer empty' => ['manufacturer', ''],
    'manufacturer not a string' => ['manufacturer', ['not', 'a', 'string']],
    'manufacturer too long' => ['manufacturer', str_repeat('a', 256)],
    'category null' => ['category', null],
    'category empty' => ['category', ''],
    'category unknown value' => ['category', 'spaceship'],
    'category not a string' => ['category', ['not', 'a', 'string']],
    'description not a string' => ['description', ['not', 'a', 'string']],
]);

dataset('valid device field boundaries', [
    'name max length' => ['name', str_repeat('a', 255)],
    'name unicode' => ['name', 'Ékezetes Eszköznév'],
    'manufacturer max length' => ['manufacturer', str_repeat('a', 255)],
    'category enum value' => ['category', DeviceCategory::NetworkEquipment->value],
    'description null' => ['description', null],
]);

dataset('required device fields', [
    'name' => ['name'],
    'manufacturer' => ['manufacturer'],
    'category' => ['category'],
]);
