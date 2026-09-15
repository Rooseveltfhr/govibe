<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Models\VoiceProfile;
use Modules\AIServices\Speech\VoiceLibrary;

/**
 * Bibliyotèk vwa platfòm nan.
 *
 * Twa bagay sou yon sèl paj:
 *
 * 1. **Bibliyotèk nou** — vwa nou chwazi, ak lang chak vwa bon pou li, epi
 *    kilès ki vwa pa defo pou chak lang.
 * 2. **Sa ki disponib lakay founisè a** — dè santèn vwa ElevenLabs. Nou pran
 *    sa nou vle, youn pa youn. Yon machann pa dwe janm wè lis sa a: yon lis
 *    dè santèn vwa pa yon chwa, se yon abandon.
 * 3. **Anrejistre yon vwa** — klonaj, ak echantiyon odyo. Se chemen an pou
 *    yon vwa kreyòl: ElevenLabs pa livre youn.
 */
class VoiceLibraryController extends Controller
{
    public function __construct(private readonly VoiceLibrary $voices) {}

    public function index(): View
    {
        $mine = $this->voices->curated();
        $taken = $mine->pluck('voice_id')->all();

        // Sa ki lakay founisè a epi ki poko nan bibliyotèk nou.
        $available = array_values(array_filter(
            $this->voices->all(),
            static fn ($voice): bool => ! in_array($voice->id, $taken, true),
        ));

        return view('core::admin.voices', [
            'mine' => $mine,
            'available' => $available,
            'connected' => $this->voices->available(),
            'languages' => VoiceProfile::LANGUAGES,
        ]);
    }

    /** Pran yon vwa founisè a genyen epi mete l nan bibliyotèk nou. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'voice_id' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'language' => ['required', Rule::in(VoiceProfile::LANGUAGES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Yon vwa ki pa egziste lakay founisè a ta bay yon erè sou CHAK
        // repons — epi nou ta dekouvri sa nan yon apèl ak yon kliyan.
        $found = $this->voices->find($data['voice_id']);

        if ($found === null) {
            return back()->withErrors([
                'voice_id' => __("Cette voix n'existe pas chez le fournisseur."),
            ]);
        }

        VoiceProfile::query()->updateOrCreate(
            ['provider_key' => $found->providerKey, 'voice_id' => $found->id],
            [
                'name' => $data['name'],
                'language' => $data['language'],
                'source' => $found->isMine() ? VoiceProfile::SOURCE_CLONED : VoiceProfile::SOURCE_LIBRARY,
                'preview_url' => $found->previewUrl,
                'notes' => $data['notes'] ?? null,
            ],
        );

        return redirect()->route('admin.voices')->with('status', __('Voix ajoutée à la bibliothèque.'));
    }

    public function update(Request $request, VoiceProfile $voice): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'language' => ['required', Rule::in(VoiceProfile::LANGUAGES)],
            'is_default' => ['nullable'],
        ]);

        $voice->update([
            'name' => $data['name'],
            'language' => $data['language'],
        ]);

        if ($request->boolean('is_default')) {
            $voice->makeDefault();
        }

        return redirect()->route('admin.voices')->with('status', __('Voix mise à jour.'));
    }

    public function destroy(VoiceProfile $voice): RedirectResponse
    {
        // Nou retire l nan bibliyotèk NOU. Nou pa efase l lakay founisè a:
        // yon vwa klonaj se travay yon moun, epi yon bouton « retire » sou
        // yon paj pa dwe detwi sa pou tout tan.
        $voice->delete();

        return redirect()->route('admin.voices')->with('status', __('Voix retirée de la bibliothèque.'));
    }

    /**
     * Anrejistre yon vwa nouvo (klonaj) epi mete l nan bibliyotèk la.
     *
     * Se chemen an pou kreyòl: ElevenLabs pa livre yon vwa kreyòl, kidonk
     * se nou ki fè youn ak echantiyon yon moun ki pale kreyòl.
     */
    public function clone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'language' => ['required', Rule::in(VoiceProfile::LANGUAGES)],
            'description' => ['nullable', 'string', 'max:500'],
            'samples' => ['required', 'array', 'min:1', 'max:5'],
            'samples.*' => ['file', 'max:10240'],
        ]);

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
            return back()->withErrors(['samples' => __('La voix n\'a pas pu être enregistrée : ').$e->getMessage()]);
        }

        VoiceProfile::query()->updateOrCreate(
            ['provider_key' => $voice->providerKey, 'voice_id' => $voice->id],
            [
                'name' => $data['name'],
                'language' => $data['language'],
                'source' => VoiceProfile::SOURCE_CLONED,
                'notes' => $data['description'] ?? null,
                'position' => 10,
            ],
        );

        return redirect()->route('admin.voices')->with('status', __('Voix enregistrée et ajoutée à la bibliothèque.'));
    }
}
