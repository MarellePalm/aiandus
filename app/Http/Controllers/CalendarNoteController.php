<?php

namespace App\Http\Controllers;

use App\Models\CalendarNote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarNoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        $start = now()->setYear($year)->setMonth($month)->startOfMonth()->toDateString();
        $end   = now()->setYear($year)->setMonth($month)->endOfMonth()->toDateString();

        $notesByDate = CalendarNote::query()
            ->where('user_id', $user->id)
            ->whereBetween('note_date', [$start, $end])
            ->orderBy('note_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($n) => $n->note_date->format('Y-m-d'))
            ->map(fn ($g) => $g->values());

        return Inertia::render('CalendarView', [
            'month' => $month,
            'year' => $year,
            'notesByDate' => $notesByDate,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        CalendarNote::create([
            'user_id' => $request->user()->id,
            ...$data,
        ]);

        return Inertia::render('CalendarView', [
            'month' => now()->month,
            'year' => now()->year,
            'notesByDate' => [],
          ]);
          
    }
}