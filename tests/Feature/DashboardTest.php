<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('atRiskCount')
            ->has('attendanceRate')
            ->has('attendanceStats', fn (AssertableInertia $page) => $page
                ->where('Present', 0)
                ->where('Late', 0)
                ->where('Absent', 0)
                ->where('Excused', 0)
            )
        );
});
