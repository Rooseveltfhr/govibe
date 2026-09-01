<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\Runtime\DemoConversation;
use Modules\Agents\Templates\AgentTemplateRegistry;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

function useDemoProvider(FakeChatProvider $provider): void
{
    $registry = new ProviderRegistry;
    $registry->register($provider);

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

it('runs the sector sample questions through the real router and returns a transcript', function () {
    useDemoProvider(new FakeChatProvider('demo', reply: 'Nou louvri 10h-22h.'));

    $templates = new AgentTemplateRegistry;
    $agent = $templates->make('restaurant', 'ti-kafe', 'Ti Kafe', knowledge: [
        'orè' => 'Lendi-Samdi 10h-22h',
    ]);

    $questions = $templates->sampleQuestions('restaurant', 'ht');

    $demo = app(DemoConversation::class);
    $turns = $demo->run($agent, $questions);

    expect($turns)->toHaveCount(count($questions));

    foreach ($turns as $turn) {
        expect($turn->answer)->toBe('Nou louvri 10h-22h.')
            ->and($turn->provider)->toBe('demo')
            ->and($turn->latencyMs)->toBeGreaterThanOrEqual(0);
    }
});

it('uses the agent sector language by default', function () {
    useDemoProvider(new FakeChatProvider('demo'));

    $templates = new AgentTemplateRegistry;
    $agent = $templates->make('clinic', 'sante-plus', 'Santé Plus', languages: ['fr', 'ht']);

    $turns = app(DemoConversation::class)->run($agent, ['Bonjour']);

    expect($turns)->toHaveCount(1);
});

it('demonstrates a custom question, not just the sector defaults', function () {
    useDemoProvider(new FakeChatProvider('demo', reply: 'Nou fèmen jou dimanch.'));

    $templates = new AgentTemplateRegistry;
    $agent = $templates->make('restaurant', 'ti-kafe', 'Ti Kafe');

    $turns = app(DemoConversation::class)->run($agent, ['Èske nou louvri dimanch?']);

    expect($turns[0]->question)->toBe('Èske nou louvri dimanch?')
        ->and($turns[0]->answer)->toBe('Nou fèmen jou dimanch.');
});
