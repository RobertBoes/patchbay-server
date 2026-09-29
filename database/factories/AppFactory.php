<?php

namespace Database\Factories;

use App\Models\App;
use RobertBoes\Patchbay\Database\Factories\AppFactory as PatchbayAppFactory;

/**
 * @extends PatchbayAppFactory
 */
class AppFactory extends PatchbayAppFactory
{
    protected $model = App::class;
}
