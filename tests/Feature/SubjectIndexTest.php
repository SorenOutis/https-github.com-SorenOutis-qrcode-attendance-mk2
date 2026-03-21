<?php

use App\Models\Subject;
use App\Models\User;

test('authenticated users can visit the subjects index', function () {
    $user = User::factory()->create();
    Subject::factory()->count(3)->create();

    $response = $this->actingAs($user)->get('/subjects');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Subjects/Index')
            ->has('subjects', 3)
        );
});
