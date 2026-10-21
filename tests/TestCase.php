<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected $seed = true;

    // Tests create their own accounts; exercise installation seeding explicitly.
    protected $seeder = RolesAndPermissionsSeeder::class;
}
