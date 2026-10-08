<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Do not depend on a built Vite manifest (other work may rebuild it concurrently).
        $this->withoutVite();
    }

    protected function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }
}
