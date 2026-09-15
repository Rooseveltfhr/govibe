<?php

namespace Modules\Agents\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Agents\DTO\IncomingMessage;
use Modules\Agents\Runtime\AgentRuntime;
use Modules\Agents\Runtime\Conversation;
use Modules\Agents\Support\PlatformSupportAgent;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;

/**
 * Chatbot sipò platfòm nan.
 *
 * Li sèvi ak menm runtime ak menm modèl chatbot nou vann machann yo. Sa a
 * pa yon detay: si sipò nou an pa mache, nou pa gen dwa vann li. Epi lè
 * yon vizitè poze yon kesyon, li wè egzakteman kalite repons pwodwi a bay.
 */
class SupportController extends Controller
{
    private const SESSION_KEY = 'louvia.support';

    private const MAX_TURNS = 24;

    public function __construct(private readonly AgentRuntime $runtime) {}

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $conversation = Conversation::fromArray((array) $request->session()->get(self::SESSION_KEY, []));

        // Yon bwat chat piblik san limit se yon fakti ouvè. Apre yon sèten
        // kantite echanj nou mande moun nan pase sou WhatsApp — kote yon
        // moun ka reponn li toutbon.
        if ($conversation->count() >= self::MAX_TURNS) {
            return response()->json([
                'reply' => __('Cette conversation est déjà longue. Écrivez-nous sur WhatsApp : un humain reprendra le fil.'),
                'closed' => true,
            ]);
        }

        try {
            $outcome = $this->runtime->respond(
                agent: PlatformSupportAgent::definition(),
                conversation: $conversation,
                message: new IncomingMessage(
                    channel: 'web',
                    conversationRef: self::SESSION_KEY,
                    text: trim($data['question']),
                ),
            );
        } catch (NoProviderAvailableException|ProviderException $e) {
            return response()->json([
                'error' => __("Aucun fournisseur d'IA n'est configuré sur ce serveur."),
            ], 503);
        }

        $request->session()->put(self::SESSION_KEY, $outcome->conversation->toArray());

        return response()->json([
            'reply' => $outcome->reply->text,
            'provider' => $outcome->provider,
            'closed' => false,
        ]);
    }
}
