<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\DTO\AgentDefinition;
use Modules\Agents\DTO\AgentReply;
use Modules\Agents\DTO\IncomingMessage;
use Modules\Agents\DTO\PendingAction;
use Modules\Agents\Runtime\AgentRuntime;
use Modules\Agents\Runtime\Conversation;
use Modules\Agents\Runtime\ConversationTurn;
use Modules\Agents\Templates\AgentTemplateRegistry;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

function useRuntimeProvider(FakeChatProvider $provider): void
{
    $registry = new ProviderRegistry;
    $registry->register($provider);

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

function restaurantAgent(array $knowledge = ['menu' => 'Griyo 500 G']): AgentDefinition
{
    return (new AgentTemplateRegistry)->make('restaurant', 'ti-kafe', 'Ti Kafe', knowledge: $knowledge);
}

function spoke(string $text, bool $spoken = false): IncomingMessage
{
    return new IncomingMessage(
        channel: 'web',
        conversationRef: 'test',
        text: $text,
        audioPath: $spoken ? '/tmp/nòt.mp3' : null,
    );
}

// ── Memwa ────────────────────────────────────────────────────────────────

it('keeps the whole exchange so the agent still knows what was ordered', function () {
    $provider = new FakeChatProvider('demo', reply: 'Ak ki akonpayman?');
    useRuntimeProvider($provider);

    $runtime = app(AgentRuntime::class);
    $agent = restaurantAgent();

    $first = $runtime->respond($agent, new Conversation, spoke('Mwen vle de griyo'));
    $second = $runtime->respond($agent, $first->conversation, spoke('Diri kole'));

    // Kat mesaj: kliyan, ajan, kliyan, ajan.
    expect($second->conversation->count())->toBe(4);

    // Sa ki konte: nan dezyèm apèl la, « de griyo » toujou la.
    $sent = $second->conversation->messagesFor($agent, 'ht');
    $contents = array_column($sent, 'content');

    expect($contents)->toContain('Mwen vle de griyo')
        ->and($contents)->toContain('Diri kole');
});

it('always sends the system prompt first so the safety rules cannot be trimmed away', function () {
    useRuntimeProvider(new FakeChatProvider('demo'));

    $agent = restaurantAgent();
    $conversation = new Conversation;

    foreach (range(1, 15) as $i) {
        $conversation = $conversation
            ->withTurn(ConversationTurn::user("kesyon {$i}"))
            ->withTurn(ConversationTurn::assistant("repons {$i}"));
    }

    $messages = $conversation->messagesFor($agent, 'ht');

    expect($messages[0]['role'])->toBe('system')
        ->and($messages[0]['content'])->toContain('1 dola ayisyen = 5 goud');
});

it('drops the oldest messages instead of growing without limit', function () {
    $agent = restaurantAgent();
    $conversation = new Conversation;

    foreach (range(1, 30) as $i) {
        $conversation = $conversation->withTurn(ConversationTurn::user("mesaj {$i}"));
    }

    $messages = $conversation->messagesFor($agent, 'ht', maxMessages: 6);

    // 1 konsiy sistèm + 6 dènye mesaj yo.
    expect($messages)->toHaveCount(7)
        ->and($messages[1]['content'])->toBe('mesaj 25')
        ->and(end($messages)['content'])->toBe('mesaj 30');
});

it('records which provider answered and how long it took', function () {
    useRuntimeProvider(new FakeChatProvider('demo', reply: 'Nou louvri 10h-22h.'));

    $outcome = app(AgentRuntime::class)->respond(restaurantAgent(), new Conversation, spoke('Ki lè?'));

    expect($outcome->provider)->toBe('demo')
        ->and($outcome->latencyMs)->toBeGreaterThanOrEqual(0)
        ->and($outcome->reply->text)->toBe('Nou louvri 10h-22h.');
});

it('survives a round trip through session storage', function () {
    $original = (new Conversation)
        ->withTurn(ConversationTurn::user('Mwen vle de griyo', spoken: true))
        ->withTurn(ConversationTurn::assistant('Ak ki akonpayman?', ['provider' => 'demo']));

    $restored = Conversation::fromArray($original->toArray());

    expect($restored->count())->toBe(2)
        ->and($restored->turns[0]->content)->toBe('Mwen vle de griyo')
        ->and($restored->turns[0]->wasSpoken())->toBeTrue()
        ->and($restored->turns[1]->role)->toBe(ConversationTurn::ASSISTANT);
});

it('forgets everything when the merchant restarts the conversation', function () {
    $conversation = (new Conversation)->withTurn(ConversationTurn::user('Mwen vle de griyo'));

    expect($conversation->cleared()->isEmpty())->toBeTrue()
        ->and($conversation->count())->toBe(1); // orijinal la pa touche
});

// ── ConfirmationPolicy branche ───────────────────────────────────────────

it('confirms a written order before acting on it', function () {
    $reply = app(AgentRuntime::class)->decide(
        restaurantAgent(),
        new PendingAction('create_order', ['2 griyo'], confidence: 0.95),
    );

    expect($reply->type)->toBe(AgentReply::CONFIRM)
        ->and($reply->text)->toBe('Mwen tande: 2 griyo. Se sa?')
        ->and($reply->pendingAction)->not->toBeNull();
});

it('answers a read-only question straight away', function () {
    $reply = app(AgentRuntime::class)->decide(
        restaurantAgent(),
        new PendingAction('get_menu', [], confidence: 0.95),
    );

    expect($reply->type)->toBe(AgentReply::ACTED);
});

it('hands a low-confidence action to a human, with the contact when there is one', function () {
    $runtime = app(AgentRuntime::class);
    $agent = (new AgentTemplateRegistry)->make(
        'restaurant', 'ti-kafe', 'Ti Kafe', handoffTo: '+509 0000 0000',
    );

    $reply = $runtime->decide($agent, new PendingAction('create_order', ['?'], confidence: 0.2));

    expect($reply->type)->toBe(AgentReply::HANDOFF)
        ->and($reply->text)->toContain('+509 0000 0000');
});

// Se règ ki pèmèt lanse ak yon ASR ki pa pafè: yon mo mal transkri vin yon
// ti korije, li pa vin yon move kòmand.
it('confirms a spoken order even when the agent is confident', function () {
    $reply = app(AgentRuntime::class)->decide(
        restaurantAgent(),
        new PendingAction('create_order', ['2 griyo'], confidence: 0.99),
        spoken: true,
    );

    expect($reply->type)->toBe(AgentReply::CONFIRM);
});

it('applies the clinic threshold, which is stricter than the restaurant one', function () {
    $runtime = app(AgentRuntime::class);
    $clinic = (new AgentTemplateRegistry)->make('clinic', 'sante', 'Santé Plus');
    $restaurant = restaurantAgent();

    $action = new PendingAction('book_appointment', ['demen 9h'], confidence: 0.50);

    // 0.50 pase sèy restoran an (0.45) men li tonbe anba sèy klinik la (0.60).
    expect($runtime->decide($clinic, $action)->type)->toBe(AgentReply::HANDOFF)
        ->and($runtime->decide($restaurant, new PendingAction('create_order', ['x'], 0.50))->type)
        ->toBe(AgentReply::CONFIRM);
});
