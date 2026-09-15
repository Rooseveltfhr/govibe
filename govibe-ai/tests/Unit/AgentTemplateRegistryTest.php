<?php

use Modules\Agents\DTO\AgentDefinition;
use Modules\Agents\Templates\AgentTemplateRegistry;

it('comes with restaurant, clinic and school registered by default', function () {
    $registry = new AgentTemplateRegistry;

    $sectors = array_map(fn ($t) => $t->sector, $registry->all());

    expect($sectors)->toContain('restaurant', 'clinic', 'school')
        ->and($registry->has('restaurant'))->toBeTrue()
        ->and($registry->has('hospital'))->toBeFalse();
});

it('starts empty when defaults are declined — useful for isolated tests', function () {
    $registry = new AgentTemplateRegistry(withDefaults: false);

    expect($registry->all())->toBe([])
        ->and($registry->has('restaurant'))->toBeFalse();
});

it('builds a ready agent from a sector template', function () {
    $registry = new AgentTemplateRegistry;

    $agent = $registry->make(
        sector: 'restaurant',
        key: 'ti-kafe',
        name: 'Ti Kafe',
        knowledge: ['mni' => 'Poul, Diri'],
    );

    expect($agent->sector)->toBe('restaurant')
        ->and($agent->name)->toBe('Ti Kafe')
        ->and($agent->hasTool('create_order'))->toBeTrue();
});

it('refuses to build an agent for a sector that is not registered', function () {
    $registry = new AgentTemplateRegistry;

    expect(fn () => $registry->make('hospital', 'x', 'X'))
        ->toThrow(InvalidArgumentException::class);
});

it('lets a platform operator add a new sector without touching the registry class', function () {
    $registry = new AgentTemplateRegistry;

    $registry->register(
        sector: 'hotel',
        label: 'Otèl',
        description: 'Rezèvasyon chanm, sèvis.',
        factory: fn (string $key, string $name, array $knowledge, array $channels, array $languages, ?string $handoff) => new AgentDefinition(
            key: $key,
            name: $name,
            sector: 'hotel',
            tools: ['book_room'],
            channels: $channels,
            languages: $languages,
            knowledge: $knowledge,
        ),
        sampleQuestions: fn (string $lang) => ['Gen chanm disponib?'],
    );

    expect($registry->has('hotel'))->toBeTrue()
        ->and($registry->make('hotel', 'h1', 'Otèl Ayiti')->hasTool('book_room'))->toBeTrue()
        ->and($registry->sampleQuestions('hotel'))->toBe(['Gen chanm disponib?']);
});

it('returns sample questions per sector in the requested language', function () {
    $registry = new AgentTemplateRegistry;

    expect($registry->sampleQuestions('restaurant', 'ht'))->not->toBeEmpty()
        ->and($registry->sampleQuestions('clinic', 'fr')[0])->toContain('rendez-vous')
        ->and($registry->sampleQuestions('school', 'en')[0])->toContain('documents');
});

it('lists templates sorted by label for a stable catalogue display', function () {
    $registry = new AgentTemplateRegistry;

    $labels = array_map(fn ($t) => $t->label, $registry->all());

    expect($labels)->toBe(collect($labels)->sort()->values()->all());
});
