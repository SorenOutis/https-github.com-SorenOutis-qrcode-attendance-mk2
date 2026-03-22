<?php

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('comments page displays total count and sorting works', function () {
    $user = User::factory()->create();

    Comment::create(['name' => 'A', 'message' => 'Test1']);
    Comment::create(['name' => 'B', 'message' => 'Test2']);
    Comment::create(['name' => 'C', 'message' => 'Test3']);
    Comment::create(['name' => 'D', 'message' => 'Test4']);

    $this->actingAs($user)->get('/comments')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('CommentsSuggestions')
            ->where('totalCount', 4)
            ->has('comments')
            ->has('filters')
        );
});
