<?php

namespace Modules\Agents\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Agents\DTO\IncomingMessage;
use Modules\Agents\Models\Agent;
use Modules\Agents\Runtime\AgentBuilder;
use Modules\Agents\Runtime\AgentRuntime;
use Modules\Agents\Runtime\Conversation;
use Modules\Agents\Templates\AgentTemplateRegistry;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIServices\Speech\SpeechService;
use Modules\AIServices\Transcription\TranscriptionService;

/**
 * Katalòg modèl ajan yo, bouton « Demo » ak bouton « Kreye ».
 *
 * Demo a pa yon simulasyon: li pase nan menm router ak menm ajan ki pral
 * reponn kliyan yo. Si demo a mache, ajan an mache.
 */
class AgentController extends Controller
{
    public function __construct(
        private readonly AgentTemplateRegistry $templates,
        private readonly AgentBuilder $builder,
        private readonly AgentRuntime $runtime,
        private readonly ProviderRegistry $providers,
        private readonly SpeechService $speech,
        private readonly TranscriptionService $transcription,
    ) {}

    /** Katalòg sektè yo + ajan ki deja kreye. */
    public function index(): View
    {
        return view('agents::index', [
            'templates' => $this->templates->all(),
            'agents' => Agent::query()->latest()->get(),
            'hasProvider' => $this->providers->configuredFor(Capability::Chat) !== [],
        ]);
    }

    /** Fòm « Kreye » pou yon sektè. */
    public function create(string $sector): View
    {
        abort_unless($this->templates->has($sector), 404);

        $descriptor = $this->templates->get($sector);

        return view('agents::create', [
            'descriptor' => $descriptor,
            'questions' => $this->templates->sampleQuestions($sector, 'ht'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sector' => ['required', 'string', Rule::in(array_map(
                static fn ($t) => $t->sector,
                $this->templates->all(),
            ))],
            'name' => ['required', 'string', 'max:120'],
            'handoff_to' => ['nullable', 'string', 'max:120'],
            'knowledge' => ['nullable', 'array'],
            'knowledge.*' => ['nullable', 'string', 'max:2000'],
        ]);

        // Chan konesans ki vid pa vle di anyen pou ajan an — nou pa sere yo,
        // sinon konsiy la ta gen liy « Orè: » san repons.
        $knowledge = array_filter(
            $data['knowledge'] ?? [],
            static fn ($v): bool => is_string($v) && trim($v) !== '',
        );

        $agent = Agent::create([
            'key' => Agent::uniqueKeyFor($data['name']),
            'name' => $data['name'],
            'sector' => $data['sector'],
            'knowledge' => $knowledge,
            'channels' => ['whatsapp', 'web'],
            'languages' => ['ht', 'fr'],
            'handoff_to' => $data['handoff_to'] ?? null,
        ]);

        return redirect()
            ->route('agents.show', $agent)
            ->with('status', __('Agent créé.'));
    }

    public function show(Agent $agent): View
    {
        return view('agents::show', [
            'agent' => $agent,
            'definition' => $agent->toDefinition($this->templates),
            'questions' => $this->templates->sampleQuestions($agent->sector, 'ht'),
            'hasProvider' => $this->providers->configuredFor(Capability::Chat) !== [],
        ]);
    }

    /**
     * Bouton « Demo » — yon vrè konvèsasyon, pa yon seri kesyon apa.
     *
     * Istorik la viv nan sesyon an paske se yon esè: yon machann k ap eseye
     * yon ajan pa bezwen nou sere konvèsasyon l nan baz done a. Lè kanal yo
     * rive (WhatsApp, telefòn), se yo k ap pote istorik la — menm runtime,
     * yon lòt kote pou sere l.
     */
    public function demo(Request $request, string $sector): View|RedirectResponse
    {
        abort_unless($this->templates->has($sector), 404);

        $validated = $request->validate([
            'question' => ['nullable', 'string', 'max:500'],
            'agent' => ['nullable', 'integer', 'exists:agents,id'],
            'reset' => ['nullable'],
            'mode' => ['nullable', 'string'],
        ]);

        $agentModel = isset($validated['agent']) ? Agent::find($validated['agent']) : null;

        $definition = $agentModel
            ? $agentModel->toDefinition($this->templates)
            : $this->builder->create($sector, 'demo', __('Démo'));

        $sessionKey = $this->sessionKey($sector, $agentModel);

        if ($request->has('reset')) {
            $request->session()->forget($sessionKey);

            return redirect()->route('agents.demo', array_filter([
                'sector' => $sector,
                'agent' => $agentModel?->id,
            ]));
        }

        $conversation = Conversation::fromArray((array) $request->session()->get($sessionKey, []));

        $question = trim($validated['question'] ?? '');
        $error = null;

        if ($question !== '') {
            try {
                $outcome = $this->runtime->respond(
                    agent: $definition,
                    conversation: $conversation,
                    message: new IncomingMessage(
                        channel: 'web',
                        conversationRef: $sessionKey,
                        text: $question,
                    ),
                );

                $conversation = $outcome->conversation;
                $request->session()->put($sessionKey, $conversation->toArray());
            } catch (NoProviderAvailableException $e) {
                // Pa gen kle API konfigire: nou di sa klèman olye nou fè kwè
                // demo a mache ak yon repons nou envante.
                $error = __("Aucun fournisseur d'IA n'est configuré sur ce serveur.");
            }
        }

        return view('agents::demo', [
            'sector' => $sector,
            'agentModel' => $agentModel,
            'definition' => $definition,
            'conversation' => $conversation,
            'suggestions' => $this->templates->sampleQuestions($sector, $definition->defaultLanguage()),
            'error' => $error,
            'hasProvider' => $this->providers->configuredFor(Capability::Chat) !== [],
            'hasVoice' => $this->speech->available(),
            'mode' => $request->string('mode')->toString() === 'call' ? 'call' : 'chat',
        ]);
    }

    /**
     * Mòd « Apèl »: moun nan pale, ajan an reponn ak vwa.
     *
     * Menm runtime, menm memwa, menm politik konfimasyon ak mòd chat la —
     * sèl bagay ki chanje se pòt antre a (odyo→tèks) ak pòt sòti a
     * (tèks→odyo). Se sa ki fè yon ajan, pa de.
     *
     * Repons lan se JSON paske paj la rele l san rechaje: yon apèl ki ta
     * rechaje paj la chak fraz pa ta yon apèl.
     */
    public function voice(Request $request, string $sector): JsonResponse
    {
        abort_unless($this->templates->has($sector), 404);

        $validated = $request->validate([
            'audio' => ['nullable', 'file', 'max:10240'],
            'text' => ['nullable', 'string', 'max:500'],
            'agent' => ['nullable', 'integer', 'exists:agents,id'],
            'speak' => ['nullable'],
        ]);

        $agentModel = isset($validated['agent']) ? Agent::find($validated['agent']) : null;
        $definition = $agentModel
            ? $agentModel->toDefinition($this->templates)
            : $this->builder->create($sector, 'demo', __('Démo'));

        $sessionKey = $this->sessionKey($sector, $agentModel);
        $language = $definition->defaultLanguage();

        // ── Pòt antre: odyo → tèks
        $audio = $request->file('audio');
        $spokenPath = null;
        $text = trim((string) ($validated['text'] ?? ''));

        if ($audio !== null && ! is_array($audio) && $audio->isValid()) {
            $spokenPath = $audio->getRealPath() ?: null;

            try {
                $transcription = $this->transcription->transcribe(
                    audioPath: (string) $spokenPath,
                    language: $language,
                    prefer: 'elevenlabs',
                );
                $text = trim($transcription['text']);
            } catch (NoProviderAvailableException|ProviderException $e) {
                return response()->json([
                    'error' => __("La transcription vocale n'est pas disponible sur ce serveur."),
                ], 503);
            }
        }

        if ($text === '') {
            return response()->json([
                'error' => __("Rien n'a été entendu. Réessayez."),
            ], 422);
        }

        // ── Ajan an
        $conversation = Conversation::fromArray((array) $request->session()->get($sessionKey, []));

        try {
            $outcome = $this->runtime->respond(
                agent: $definition,
                conversation: $conversation,
                message: new IncomingMessage(
                    channel: 'web',
                    conversationRef: $sessionKey,
                    text: $text,
                    audioPath: $spokenPath,
                ),
            );
        } catch (NoProviderAvailableException|ProviderException $e) {
            return response()->json([
                'error' => __("Aucun fournisseur d'IA n'est configuré sur ce serveur."),
            ], 503);
        }

        $request->session()->put($sessionKey, $outcome->conversation->toArray());

        // ── Pòt sòti: tèks → vwa, sèlman si mòd apèl la mande l (yon mesaj
        // ekri nan mòd chat pa dwe boule kredi vwa). Si vwa a tonbe, repons
        // lan rive kanmenm an tèks: yon apèl san son pi bon pase yon apèl
        // san repons.
        $spokenAudio = null;

        if ($request->boolean('speak')) {
            try {
                $spokenAudio = $this->speech->speak($outcome->reply->text, $language)->toDataUri();
            } catch (NoProviderAvailableException|ProviderException $e) {
                $spokenAudio = null;
            }
        }

        return response()->json([
            'transcript' => $text,
            'reply' => $outcome->reply->text,
            'audio' => $spokenAudio,
            'provider' => $outcome->provider,
            'model' => $outcome->model,
            'latency_ms' => $outcome->latencyMs,
        ]);
    }

    /**
     * Chak ajan gen pwòp konvèsasyon l: eseye Chez A pa dwe kite tras nan
     * demo Chez B. « template » se esè a sou modèl sektè a, anvan kreyasyon.
     */
    private function sessionKey(string $sector, ?Agent $agentModel): string
    {
        $scope = $agentModel === null ? 'template' : (string) $agentModel->id;

        return 'louvia.demo.'.$sector.'.'.$scope;
    }
}
