<?php

use Modules\Agents\DTO\PendingAction;
use Modules\Agents\Runtime\ConfirmationPolicy;
use Modules\Agents\Templates\RestaurantTemplate;

function tiKafe(array $knowledge = [], array $channels = ['whatsapp'], array $languages = ['ht', 'fr'])
{
    return RestaurantTemplate::make(
        key: 'ti-kafe',
        name: 'Ti Kafe',
        knowledge: $knowledge ?: [
            'mni' => 'Poul boukannen 500 G, Griyo 500 G, Diri kole 250 G',
            'orè' => 'Lendi-Samdi 10h-22h',
            'livrezon' => 'Wi, nan zòn Delmas sèlman',
        ],
        channels: $channels,
        languages: $languages,
    );
}

it('creates a ready-to-use restaurant agent from the template', function () {
    $agent = tiKafe();

    expect($agent->sector)->toBe('restaurant')
        ->and($agent->name)->toBe('Ti Kafe')
        ->and($agent->defaultLanguage())->toBe('ht')
        ->and($agent->systemPrompt)->not->toBe('');
});

it('gives the agent both read and write tools', function () {
    $agent = tiKafe();

    expect($agent->hasTool('get_menu'))->toBeTrue()
        ->and($agent->hasTool('create_order'))->toBeTrue()
        // Yon ajan restoran pa dwe gen dwa fè yon ranbousman.
        ->and($agent->hasTool('refund'))->toBeFalse();
});

it('serves the channels it was published on', function () {
    $agent = tiKafe(channels: ['whatsapp', 'phone']);

    expect($agent->servesChannel('whatsapp'))->toBeTrue()
        ->and($agent->servesChannel('phone'))->toBeTrue()
        ->and($agent->servesChannel('web'))->toBeFalse();
});

it('puts the business knowledge into the compiled prompt', function () {
    $prompt = tiKafe()->compiledPrompt('ht');

    expect($prompt)->toContain('Poul boukannen 500 G')
        ->toContain('Lendi-Samdi 10h-22h')
        ->toContain('Ti Kafe');
});

it('always forbids inventing prices — this protects the reputation', function () {
    // Se règ ki pi enpòtan an: yon ajan ki envante yon pri koute yon kliyan.
    expect(tiKafe()->compiledPrompt('ht'))
        ->toContain('Pa janm envante yon pri')
        ->toContain('pase bay yon moun');
});

it('confirms every order but answers prices directly', function () {
    $policy = ConfirmationPolicy::fromArray(tiKafe()->confirmation);

    $order = new PendingAction('create_order', ['qty' => '2', 'item' => 'poul'], 0.98);
    $price = new PendingAction('get_price', ['item' => 'griyo'], 0.98);

    expect($policy->decide($order))->toBe(ConfirmationPolicy::CONFIRM)
        ->and($policy->decide($price))->toBe(ConfirmationPolicy::ACT);
});

it('hands off instead of guessing when it did not understand', function () {
    $policy = ConfirmationPolicy::fromArray(tiKafe()->confirmation);

    $unclear = new PendingAction('create_order', ['item' => '???'], 0.20);

    expect($policy->decide($unclear))->toBe(ConfirmationPolicy::HANDOFF);
});

it('confirms a spoken order in creole with the words the customer will hear', function () {
    $policy = ConfirmationPolicy::fromArray(tiKafe()->confirmation);
    $order = new PendingAction('create_order', ['qty' => '2', 'item' => 'poul boukannen'], 0.90);

    expect($policy->decide($order, spoken: true))->toBe(ConfirmationPolicy::CONFIRM)
        ->and($policy->question($order, 'ht'))->toBe('Mwen tande: 2, poul boukannen. Se sa?');
});

it('routes restaurant chatter to the cheap model', function () {
    // Kesyon restoran yo kout epi repetitif — routeur la monte poukont li
    // si yon kesyon vin konplike.
    expect(tiKafe()->routingStrategy)->toBe('cost');
});

it('offers sample questions so the merchant can test before publishing', function () {
    expect(RestaurantTemplate::sampleQuestions('ht'))->toHaveCount(3)
        ->and(RestaurantTemplate::sampleQuestions('ht')[0])->toContain('Bonjou')
        ->and(RestaurantTemplate::sampleQuestions('fr')[0])->toContain('Bonjour')
        ->and(RestaurantTemplate::sampleQuestions('en')[0])->toContain('close');
});
