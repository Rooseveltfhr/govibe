<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\DTO\PendingAction;
use Modules\Agents\Runtime\AgentBuilder;
use Modules\Agents\Runtime\ConfirmationPolicy;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

function useBuilderProvider(FakeChatProvider $provider): void
{
    $registry = new ProviderRegistry;
    $registry->register($provider);

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

it('lists the sectors a merchant can pick from', function () {
    $sectors = array_map(fn ($t) => $t->sector, app(AgentBuilder::class)->availableSectors());

    expect($sectors)->toContain('restaurant', 'clinic', 'school');
});

it('creates an agent from a sector template through one entry point', function () {
    $agent = app(AgentBuilder::class)->create(
        sector: 'restaurant',
        key: 'ti-kafe',
        name: 'Ti Kafe',
        knowledge: ['orè' => '10h-22h'],
    );

    expect($agent->sector)->toBe('restaurant')
        ->and($agent->name)->toBe('Ti Kafe')
        ->and($agent->hasTool('create_order'))->toBeTrue();
});

it('derives the confirmation policy straight from the agent definition', function () {
    $builder = app(AgentBuilder::class);
    $agent = $builder->create('restaurant', 'ti-kafe', 'Ti Kafe');

    $policy = $builder->confirmationPolicy($agent);

    expect($policy)->toBeInstanceOf(ConfirmationPolicy::class);

    $decision = $builder->decide($agent, new PendingAction('create_order', ['2 poul'], confidence: 0.95));

    expect($decision)->toBe(ConfirmationPolicy::CONFIRM);
});

it('runs a demo for a freshly created agent using its own sector questions', function () {
    useBuilderProvider(new FakeChatProvider('demo', reply: 'Nou louvri 10h-22h.'));

    $builder = app(AgentBuilder::class);
    $agent = $builder->create('restaurant', 'ti-kafe', 'Ti Kafe', knowledge: ['orè' => '10h-22h']);

    $turns = $builder->demo($agent);

    expect($turns)->toHaveCount(3)
        ->and($turns[0]->answer)->toBe('Nou louvri 10h-22h.');
});

it('runs a demo with a custom question instead of the sector defaults', function () {
    useBuilderProvider(new FakeChatProvider('demo', reply: 'Nou fèmen jou dimanch.'));

    $builder = app(AgentBuilder::class);
    $agent = $builder->create('restaurant', 'ti-kafe', 'Ti Kafe');

    $turns = $builder->demo($agent, ['Èske nou louvri dimanch?']);

    expect($turns[0]->question)->toBe('Èske nou louvri dimanch?')
        ->and($turns[0]->answer)->toBe('Nou fèmen jou dimanch.');
});
