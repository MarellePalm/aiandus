<?php

namespace App\Http\Controllers;

use App\Models\CalendarNote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'type' => ['nullable', 'string', 'in:note,task'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $paths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $paths[] = $file->store('calendar-notes', 'public');
            }
        }

        $type = $data['type'] ?? 'note';
        $dueAt = null;
        if ($type === 'task' && ! empty($data['due_date'])) {
            $dueAt = isset($data['due_time']) && $data['due_time'] !== ''
                ? Carbon::parse($data['due_date'].' '.$data['due_time'])
                : Carbon::parse($data['due_date'])->setTime(9, 0);
        }

        $note = CalendarNote::create([
            'user_id' => $request->user()->id,
            'note_date' => $data['note_date'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'media' => $paths,
            'type' => $type,
            'done' => $type === 'task' ? false : null,
            'due_at' => $dueAt,
        ]);

        return redirect()->route('calendar', [
            'month' => $note->note_date->month,
            'year' => $note->note_date->year,
        ]);
    }

    public function edit(Request $request, CalendarNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $dueAt = $note->due_at;
        return Inertia::render('NoteForm', [
            'note' => [
                'id' => $note->id,
                'note_date' => $note->note_date->format('Y-m-d'),
                'title' => $note->title,
                'body' => $note->body,
                'media_urls' => $note->media_urls,
                'type' => $note->type,
                'due_date' => $dueAt ? $dueAt->format('Y-m-d') : null,
                'due_time' => $dueAt ? $dueAt->format('H:i') : null,
            ],
            'editMode' => true,
        ]);
    }

    public function update(Request $request, CalendarNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'in:note,task'],
            'done' => ['sometimes', 'boolean'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $paths = $note->media ?? [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $paths[] = $file->store('calendar-notes', 'public');
            }
        }

        $dueAt = null;
        if (($data['type'] ?? $note->type) === 'task' && ! empty($data['due_date'] ?? null)) {
            $dueAt = isset($data['due_time']) && $data['due_time'] !== ''
                ? Carbon::parse($data['due_date'].' '.$data['due_time'])
                : Carbon::parse($data['due_date'])->setTime(9, 0);
        }

        $update = [
            'note_date' => $data['note_date'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'media' => $paths,
            'due_at' => $dueAt,
        ];
        if (array_key_exists('type', $data)) {
            $update['type'] = $data['type'];
        }
        if (array_key_exists('done', $data)) {
            $update['done'] = $data['done'];
        }
        $note->update($update);

        return redirect()->route('calendar', [
            'month' => $note->note_date->month,
            'year' => $note->note_date->year,
        ]);
    }

    public function destroy(Request $request, CalendarNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->delete();

        return redirect()->back();
    }

    public function toggleDone(Request $request, CalendarNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->update(['done' => !$note->done]);

        return redirect()->back();
    }
}