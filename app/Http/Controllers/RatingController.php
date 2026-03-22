<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatingController extends Controller
{
    public function index(): Response
    {
        $from = request('from');
        $to = request('to');
        $sort = request('sort', 'newest');

        $query = Rating::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $query = match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'highest' => $query->orderByDesc('score')->orderByDesc('created_at'),
            'lowest' => $query->orderBy('score')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $ratings = $query->get([
            'id',
            'name',
            'email',
            'score',
            'message',
            'is_public',
            'created_at',
            'updated_at',
        ]);

        /** @var array<int, int> $distribution */
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = Rating::where('score', $i)->count();
        }

        return Inertia::render('Ratings', [
            'ratings' => $ratings,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
            'aggregateStats' => [
                'average' => round((float) Rating::avg('score'), 1),
                'total' => Rating::count(),
                'distribution' => $distribution,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'score' => ['required', 'integer', 'between:1,5'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Rating::create($data);

        return back()->with('flash', [
            'rating_submitted' => true,
        ]);
    }

    public function update(Request $request, Rating $rating): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'message' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);

        $rating->update($data);

        return back();
    }

    public function destroy(Rating $rating): RedirectResponse
    {
        $rating->delete();

        return back();
    }
}
