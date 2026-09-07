<?php

namespace Modules\Agents\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Agents\Models\Agent;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIServices\Speech\VoiceLibrary;

/**
 * Vwa yon ajan: chwazi youn nan bibliyotèk la, oswa anrejistre pa w.
 *
 * Yon vwa klonaj se sa ki fè yon ajan sonnen tankou biznis lan olye li
 * sonnen tankou tout lòt moun ki achte menm zouti a.
 */
class VoiceController extends Controller
{
    public function __construct(private readonly VoiceLibrary $voices) {}

    public function edit(Agent $agent): View
    {
        return view('agents::voices.edit', [
            'agent' => $agent,
            'voices' => $this->voices->all(),
            'available' => $this->voices->available(),
            'current' => $agent->voice_id,
        ]);
    }

    /** Chwazi yon vwa ki deja nan bibliyotèk la. */
    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'voice_id' => ['nullable', 'string', 'max:120'],
        ]);

        $chosen = trim((string) ($data['voice_id'] ?? ''));

        // Yon vwa ki pa nan bibliyotèk la ta bay yon 404 ElevenLabs sou
        // chak repons — epi machann nan t ap dekouvri sa nan yon apèl ak
        // yon kliyan. Nou refize l isit la.
        if ($chosen !== '' && $this->voices->find($chosen) === null) {
            return back()->withErrors(['voice_id' => __("Cette voix n'existe pas dans la bibliothèque.")]);
        }

        $agent->update(['voice_id' => $chosen !== '' ? $chosen : null]);

        return redirect()
            ->route('agents.voice.edit', $agent)
            ->with('status', __('Voix mise à jour.'));
    }

    /**
     * Anrejistre yon vwa nouvo apati echantiyon odyo, epi bay ajan an li.
     *
     * Echantiyon yo pa rete sou disk nou: yo monte lakay founisè a epi nou
     * efase yo. Yon anrejistreman vwa se yon done byometrik — nou pa kenbe
     * sa nou pa bezwen.
     */
    public function store(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'samples' => ['required', 'array', 'min:1', 'max:5'],
            'samples.*' => ['file', 'max:10240'],
        ]);

        // Validasyon an deja garanti se fichye: sa ki rete pou verifye se
        // si transfè a byen fini (yon telechajman ki koupe rive vid).
        $paths = [];

        foreach ($request->file('samples', []) as $sample) {
            if ($sample->isValid()) {
                $paths[] = (string) $sample->getRealPath();
            }
        }

        if ($paths === []) {
            return back()->withErrors(['samples' => __("Aucun échantillon audio n'a été reçu.")]);
        }

        try {
            $voice = $this->voices->add($data['name'], $paths, $data['description'] ?? null);
        } catch (NoProviderAvailableException|ProviderException $e) {
            return back()->withErrors(['samples' => __("La voix n'a pas pu être enregistrée : ").$e->getMessage()]);
        }

        $agent->update(['voice_id' => $voice->id]);

        return redirect()
            ->route('agents.voice.edit', $agent)
            ->with('status', __('Voix enregistrée et attribuée à cet agent.'));
    }
}
