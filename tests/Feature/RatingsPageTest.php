<?php

use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('ratings page displays aggregate stats and filters', function () {
    $user = User::factory()->create();

    Rating::create(['name' => 'Test1', 'score' => 5, 'message' => 'Great']);
    Rating::create(['name' => 'Test2', 'score' => 5, 'message' => 'Awesome']);
    Rating::create(['name' => 'Test3', 'score' => 2, 'message' => 'Bad']);

    $this->actingAs($user)->get('/ratings')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Ratings')
            ->has('aggregateStats', fn (AssertableInertia $page) => $page
                ->where('average', 4)
                ->where('total', 3)
                ->has('distribution')
            )
        );
});
