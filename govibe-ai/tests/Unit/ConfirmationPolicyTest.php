<?php

use Modules\Agents\DTO\PendingAction;
use Modules\Agents\Runtime\ConfirmationPolicy;

function pendingAction(string $tool = 'create_order', float $confidence = 1.0, array $args = []): PendingAction
{
    return new PendingAction($tool, $args ?: ['item' => 'poul', 'qty' => '2'], $confidence);
}

it('hands off when confidence is too low', function () {
    $policy = new ConfirmationPolicy;

    // Yon ajan ki pa sèten pa dwe devine — li pase bay yon moun.
    expect($policy->decide(pendingAction(confidence: 0.30)))->toBe(ConfirmationPolicy::HANDOFF);
});

it('confirms by default — that is what lets us ship with imperfect ASR', function () {
    expect((new ConfirmationPolicy)->decide(pendingAction(confidence: 0.99)))
        ->toBe(ConfirmationPolicy::CONFIRM);
});

it('always confirms a spoken message even when confident', function () {
    $policy = new ConfirmationPolicy(confirmEverything: false);

    expect($policy->decide(pendingAction(confidence: 0.99), spoken: false))->toBe(ConfirmationPolicy::ACT)
        ->and($policy->decide(pendingAction(confidence: 0.99), spoken: true))->toBe(ConfirmationPolicy::CONFIRM);
});

it('acts directly on read-only tools', function () {
    $policy = new ConfirmationPolicy(neverConfirm: ['get_price', 'get_hours']);

    expect($policy->decide(pendingAction('get_price', 0.95)))->toBe(ConfirmationPolicy::ACT)
        ->and($policy->decide(pendingAction('get_hours', 0.95), spoken: true))->toBe(ConfirmationPolicy::ACT);
});

it('still hands off a read-only tool when confidence collapses', function () {
    $policy = new ConfirmationPolicy(neverConfirm: ['get_price']);

    expect($policy->decide(pendingAction('get_price', 0.10)))->toBe(ConfirmationPolicy::HANDOFF);
});

it('always confirms listed tools regardless of confidence', function () {
    $policy = new ConfirmationPolicy(confirmEverything: false, alwaysConfirm: ['refund']);

    expect($policy->decide(pendingAction('refund', 1.0)))->toBe(ConfirmationPolicy::CONFIRM);
});

it('uses the confidence threshold when confirm-everything is off', function () {
    $policy = new ConfirmationPolicy(confirmBelow: 0.80, confirmEverything: false);

    expect($policy->decide(pendingAction(confidence: 0.85)))->toBe(ConfirmationPolicy::ACT)
        ->and($policy->decide(pendingAction(confidence: 0.70)))->toBe(ConfirmationPolicy::CONFIRM);
});

it('asks the confirmation question in the conversation language', function () {
    $policy = new ConfirmationPolicy;
    $order = pendingAction(args: ['qty' => '2', 'item' => 'poul']);

    expect($policy->question($order, 'ht'))->toBe('Mwen tande: 2, poul. Se sa?')
        ->and($policy->question($order, 'fr'))->toContain('Je confirme')
        ->and($policy->question($order, 'en'))->toContain('Just to confirm')
        ->and($policy->question($order, 'es'))->toContain('Confirmo');
});

it('handles an action with no readable arguments', function () {
    $policy = new ConfirmationPolicy;

    expect($policy->question(new PendingAction('handoff'), 'ht'))->toBe('Se sa?');
});

it('builds from a config array', function () {
    $policy = ConfirmationPolicy::fromArray([
        'confirm_everything' => false,
        'handoff_below' => 0.5,
        'never_confirm' => ['get_price'],
        'always_confirm' => ['refund'],
    ]);

    expect($policy->decide(pendingAction('get_price', 0.9)))->toBe(ConfirmationPolicy::ACT)
        ->and($policy->decide(pendingAction('refund', 0.9)))->toBe(ConfirmationPolicy::CONFIRM)
        ->and($policy->decide(pendingAction('create_order', 0.4)))->toBe(ConfirmationPolicy::HANDOFF);
});

it('summarises a pending action for the human to confirm', function () {
    expect(pendingAction(args: ['qty' => '2', 'item' => 'poul boukannen'])->summary())
        ->toBe('2, poul boukannen');
});
