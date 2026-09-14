<?php

namespace Modules\Tagtoa\App\Http\Controllers\Stand;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA SMART STAND — activer une salle entière, debout, au téléphone.
 *
 * L'écran est pensé pour un geste répété quarante fois : la caméra reste
 * OUVERTE, on gratte, on vise, on passe à la table suivante. Chaque lecture est
 * un appel à ce contrôleur et rien d'autre — pas de page qui se recharge, pas de
 * formulaire à revalider, pas de retour en arrière.
 *
 * ⚠️ La charge utile contient le secret. Elle arrive en CORPS de requête, jamais
 * dans l'URL : une URL finit dans l'historique du navigateur, dans l'en-tête
 * Referer et dans le journal d'accès du serveur — trois endroits où le secret
 * d'un stand non réclamé vaut le stand lui-même.
 */
class StandActivationController extends Controller
{
    public function __construct(protected StandActivator $activator)
    {
    }

    /** L'écran d'activation en série. */
    public function screen(): View
    {
        $tenantId = Tenant::id();

        return view('tagtoa::stand.activate', [
            'nextLabel' => $this->activator->nextLabel($tenantId),
            'prefix'    => StandActivator::DEFAULT_PREFIX,
            'deja'      => Stand::ofBusiness($tenantId)->count(),
        ]);
    }

    /**
     * Une lecture. Renvoie toujours 200 avec un verdict lisible par l'écran.
     *
     * Un code HTTP d'erreur ferait crier la console du navigateur à chaque
     * étiquette de transporteur qui passe devant la caméra, et l'écran n'a
     * besoin que de savoir quoi afficher dans sa liste.
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            // 48 : de quoi accepter une lecture bavarde sans laisser passer un
            // corps de requête quelconque.
            'payload'        => ['required', 'string', 'max:48'],
            'location_label' => ['nullable', 'string', 'max:60'],
            'label_prefix'   => ['nullable', 'string', 'max:30'],
            'target_module'  => ['nullable', 'string', 'in:menu,links,pay'],
        ]);

        $resultat = $this->activator->activate($data['payload'], Tenant::id(), [
            'location_label' => $data['location_label'] ?? null,
            'label_prefix'   => $data['label_prefix'] ?? StandActivator::DEFAULT_PREFIX,
            'target_module'  => $data['target_module'] ?? null,
            'actor_name'     => optional($request->user())->name,
            'ip'             => $request->ip(),
            'user_agent'     => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        $stand = $resultat['stand'];

        return response()->json([
            'result'  => $resultat['result'],
            'message' => $this->message($resultat['result']),
            // L'identifiant public est imprimé au recto : l'afficher ne révèle
            // rien. Le secret, lui, ne repart jamais — pas même masqué.
            'stand'   => $stand ? [
                'id'        => $stand->id,
                'public_id' => $stand->public_id,
                'label'     => $resultat['label'],
            ] : ['public_id' => StandScratch::parse($data['payload'])[0]],
            'next_label' => $this->activator->nextLabel(
                Tenant::id(),
                $data['label_prefix'] ?? StandActivator::DEFAULT_PREFIX
            ),
        ]);
    }

    /**
     * Ce que le marchand lit dans sa liste.
     *
     * Chaque message dit QUOI FAIRE, pas seulement ce qui a échoué : l'écran est
     * consulté d'une main, debout dans une salle, et « code invalide » n'aide
     * personne à finir d'installer ses tables.
     */
    private function message(string $result): string
    {
        return match ($result) {
            StandActivator::OK           => __('Activé.'),
            StandActivator::ALREADY_MINE => __('Déjà à vous — rien à faire.'),
            StandActivator::NO_SECRET    => __('C\'est le QR du dessus. Grattez le panneau au dos et visez le petit code.'),
            StandActivator::UNREADABLE   => __('Ce code n\'est pas un stand TAGTOA.'),
            StandActivator::NOT_FOUND    => __('Stand inconnu. Vérifiez le numéro imprimé.'),
            StandActivator::BAD_CODE     => __('Code d\'activation incorrect. Regrattez et visez à nouveau.'),
            StandActivator::NOT_OPEN     => __('Ce stand n\'est pas activable. Contactez TAGTOA.'),
            StandActivator::TOO_MANY     => __('Trop d\'essais sur ce stand. Réessayez dans une heure.'),
            StandActivator::NO_BUSINESS  => __('Créez votre commerce avant d\'activer un stand.'),
            default                      => __('Réessayez.'),
        };
    }
}
