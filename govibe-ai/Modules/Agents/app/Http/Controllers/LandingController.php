<?php

namespace Modules\Agents\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Agents\Templates\AgentTemplateRegistry;
use Modules\Agents\Templates\TemplateDescriptor;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Paj akèy piblik la.
 *
 * Li pa yon vitrin apa: kat li montre yo se menm modèl katalòg la, epi
 * bouton « Demo » a mennen sou menm demo a. Yon paj akèy ki dekri yon
 * pwodwi ki pa egziste se yon pwomès nou pa ka kenbe.
 */
class LandingController extends Controller
{
    public function __construct(
        private readonly AgentTemplateRegistry $templates,
        private readonly ProviderRegistry $providers,
    ) {}

    public function index(): View
    {
        return view('agents::landing', [
            'agents' => $this->templates->ofKind(TemplateDescriptor::KIND_AGENT),
            'chatbots' => $this->templates->ofKind(TemplateDescriptor::KIND_CHATBOT),
            'all' => $this->templates->all(),
            'hasProvider' => $this->providers->configuredFor(Capability::Chat) !== [],
            'hasVoice' => $this->providers->configuredFor(Capability::Speech) !== [],
        ]);
    }
}
