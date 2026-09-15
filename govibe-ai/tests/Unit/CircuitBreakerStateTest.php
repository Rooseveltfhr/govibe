<?php

use Modules\AIProvider\Support\CircuitBreakerState;

it('stays closed below the failure threshold', function () {
    $state = new CircuitBreakerState(failureThreshold: 3, cooldownSeconds: 60);

    expect($state->currentState(2, null, 1000))->toBe(CircuitBreakerState::CLOSED)
        ->and($state->allowsTraffic(2, null, 1000))->toBeTrue();
});

it('opens when the threshold is reached', function () {
    $state = new CircuitBreakerState(failureThreshold: 3, cooldownSeconds: 60);

    $next = $state->recordFailure(2, null, 1000);

    expect($next['failures'])->toBe(3)
        ->and($next['opened_at'])->toBe(1000)
        ->and($state->allowsTraffic(3, 1000, 1000))->toBeFalse();
});

it('moves to half-open once the cooldown has elapsed', function () {
    $state = new CircuitBreakerState(failureThreshold: 3, cooldownSeconds: 60);

    expect($state->currentState(3, 1000, 1059))->toBe(CircuitBreakerState::OPEN)
        ->and($state->currentState(3, 1000, 1060))->toBe(CircuitBreakerState::HALF_OPEN)
        ->and($state->allowsTraffic(3, 1000, 1060))->toBeTrue();
});

it('reopens with a fresh cooldown when the half-open probe fails', function () {
    $state = new CircuitBreakerState(failureThreshold: 3, cooldownSeconds: 60);

    $next = $state->recordFailure(3, 1000, 1060);

    expect($next['opened_at'])->toBe(1060);
});

it('closes again after a success', function () {
    $state = new CircuitBreakerState(failureThreshold: 3, cooldownSeconds: 60);

    expect($state->recordSuccess())->toBe(['failures' => 0, 'opened_at' => null]);
});
