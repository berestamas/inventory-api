<?php

use App\Models\ContractDevice;
use Illuminate\Database\Eloquent\Factories\HasFactory;

arch('models have factories')
    ->expect('App\Models')
    ->toUseTrait(HasFactory::class)
    ->ignoring(ContractDevice::class);

arch('app files use strict types')
    ->expect('App')
    ->toUseStrictTypes();
