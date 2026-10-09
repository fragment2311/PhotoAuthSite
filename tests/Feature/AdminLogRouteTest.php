<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminLogRouteTest extends TestCase
{
    public function test_log_journal_route_requires_the_administrator_role(): void
    {
        $route = Route::getRoutes()->getByName('admin.logs.index');

        $this->assertNotNull($route);
        $this->assertContains('role:Администратор', $route->gatherMiddleware());
    }
}