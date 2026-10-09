<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramSession;
use App\Models\Room;
use App\Models\Speaker;
use Illuminate\Http\Request;

class SessionAdminController extends Controller
{
    public function index()
    {
        return view('admin.sessions.index', [
            'sessions' => ProgramSession::with(['room', 'speakers'])->orderBy('day')->orderBy('starts_at')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.sessions.form', [
            'session'  => new ProgramSession(['active' => true, 'type' => 'panel']),
            'rooms'    => Room::orderBy('sort')->get(),
            'speakers' => Speaker::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $session = ProgramSession::create($this->validated($request));
        $session->speakers()->sync($request->input('speaker_ids', []));

        return redirect()->route('admin.sessions.index')->with('ok', 'Session créée.');
    }

    public function edit(ProgramSession $session)
    {
        return view('admin.sessions.form', [
            'session'  => $session->load('speakers'),
            'rooms'    => Room::orderBy('sort')->get(),
            'speakers' => Speaker::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ProgramSession $session)
    {
        $session->update($this->validated($request));
        $session->speakers()->sync($request->input('speaker_ids', []));

        return redirect()->route('admin.sessions.index')->with('ok', 'Session mise à jour.');
    }

    public function destroy(ProgramSession $session)
    {
        $session->delete();

        return back()->with('ok', 'Session supprimée.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => 'required|string|max:190',
            'description' => 'nullable|string|max:5000',
            'day'         => 'required|date',
            'starts_at'   => 'required|date_format:H:i',
            'ends_at'     => 'required|date_format:H:i|after:starts_at',
            'room_id'     => 'nullable|integer|exists:rooms,id',
            'type'        => 'required|string|in:'.implode(',', array_keys(config('finpo.session_types'))),
            'track'       => 'nullable|string|max:120',
            'featured'    => 'nullable|boolean',
            'active'      => 'nullable|boolean',
            'speaker_ids'   => 'nullable|array',
            'speaker_ids.*' => 'integer|exists:speakers,id',
        ]);

        $data['featured'] = $request->boolean('featured');
        $data['active'] = $request->boolean('active');
        unset($data['speaker_ids']);

        return $data;
    }
}
