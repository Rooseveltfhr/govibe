<?php

namespace Modules\Agents\Runtime;

use Modules\Agents\DTO\PendingAction;

/**
 * Deside si yon aksyon dwe konfime, fèt dirèk, oswa pase bay yon moun.
 *
 * Se pyès ki pi enpòtan nan tout runtime lan, epi se poutèt sa li viv ISIT LA
 * epi non nan chak ajan: lè nou aprann yon pi bon fason pou deside, nou repare
 * l yon sèl kote pou tout ajan yo alafwa.
 *
 * Klas pi — okenn E/S, okenn depandans framework.
 */
class ConfirmationPolicy
{
    public const ACT = 'act';

    public const CONFIRM = 'confirm';

    public const HANDOFF = 'handoff';

    /**
     * @param  float  $confirmBelow  anba sa a, mande konfimasyon
     * @param  float  $handoffBelow  anba sa a, pase bay yon moun
     * @param  list<string>  $alwaysConfirm  zouti ki toujou mande konfimasyon
     * @param  list<string>  $neverConfirm  zouti ki pa janm mande (lekti sèlman)
     */
    public function __construct(
        private readonly float $confirmBelow = 0.92,
        private readonly float $handoffBelow = 0.45,
        private readonly array $alwaysConfirm = [],
        private readonly array $neverConfirm = [],
        private readonly bool $confirmEverything = true,
    ) {}

    public function decide(PendingAction $action, bool $spoken = false): string
    {
        // Konfyans twò ba: ajan an pa dwe devine — li pase bay yon moun.
        // Yon ajan ki konnen lè l pa konnen bati konfyans;
        // yon ajan ki devine kraze l.
        if ($action->confidence < $this->handoffBelow) {
            return self::HANDOFF;
        }

        // Zouti lekti sèlman (bay yon pri, di yon orè): pa gen anyen pou defèt.
        if (in_array($action->tool, $this->neverConfirm, true)) {
            return self::ACT;
        }

        if (in_array($action->tool, $this->alwaysConfirm, true)) {
            return self::CONFIRM;
        }

        // Vwa pase nan ASR: menm ak yon bon konfyans, yon mo ki mal transkri
        // ka chanje yon kòmand. Nou konfime toujou.
        if ($spoken) {
            return self::CONFIRM;
        }

        if ($this->confirmEverything) {
            return self::CONFIRM;
        }

        return $action->confidence < $this->confirmBelow ? self::CONFIRM : self::ACT;
    }

    /** Fraz konfimasyon an, nan lang konvèsasyon an. */
    public function question(PendingAction $action, string $language = 'ht'): string
    {
        $summary = $action->summary();

        return match ($language) {
            'fr' => $summary === '' ? 'Je confirme ?' : "Je confirme : {$summary} ?",
            'en' => $summary === '' ? 'Shall I confirm?' : "Just to confirm: {$summary}?",
            'es' => $summary === '' ? '¿Confirmo?' : "Confirmo: {$summary}?",
            default => $summary === '' ? 'Se sa?' : "Mwen tande: {$summary}. Se sa?",
        };
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        /** @var list<string> $always */
        $always = array_values(array_filter((array) ($config['always_confirm'] ?? []), 'is_string'));
        /** @var list<string> $never */
        $never = array_values(array_filter((array) ($config['never_confirm'] ?? []), 'is_string'));

        return new self(
            confirmBelow: (float) ($config['confirm_below'] ?? 0.92),
            handoffBelow: (float) ($config['handoff_below'] ?? 0.45),
            alwaysConfirm: $always,
            neverConfirm: $never,
            confirmEverything: (bool) ($config['confirm_everything'] ?? true),
        );
    }
}
