<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Livewire\EditGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Support\HblClient;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The setting that stopped Himalayan Bank working at all.
 *
 * The admin screen calls this field "Y/N", and `filter_var()` understands
 * neither: given 'N' it returns null, and the `?? true` that followed turned
 * that into "yes". So the setting was write-only. Every payment asked PACO for
 * 3-D Secure whatever the admin chose, and on a profile that cannot complete
 * 3DS the buyer submitted a card and was rejected before the acquirer was ever
 * contacted — PACO recorded status F at step PR with no card and no approval
 * code. The vendor's own working demo only ever sends 'N'.
 */
function gatewayWith3ds(mixed $value): Gateway
{
    $credentials = [
        'office_id' => '9104137120',
        'api_key' => 'test-api-key',
        'encryption_key_id' => '7664a2ed0dee4879bdfca0e8ce1ac313',
    ];

    if ($value !== 'absent') {
        $credentials['request_3ds'] = $value;
    }

    return Gateway::updateOrCreate(['code' => 'himalayan'], [
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['USD'],
        'credentials' => $credentials,
    ]);
}

it('treats N as off', function (mixed $value): void {
    expect(gatewayWith3ds($value)->getHimalayanRequest3ds())->toBeFalse();
})->with([
    'the letter N' => 'N',
    'lowercase n' => 'n',
    'the word no' => 'no',
    'the string zero' => '0',
    'the string false' => 'false',
    'a real false' => false,
    'the integer zero' => 0,
]);

it('treats Y as on', function (mixed $value): void {
    expect(gatewayWith3ds($value)->getHimalayanRequest3ds())->toBeTrue();
})->with([
    'the letter Y' => 'Y',
    'lowercase y' => 'y',
    'the word yes' => 'yes',
    'the string one' => '1',
    'a real true' => true,
]);

it('asks for 3DS when nothing has been configured', function (): void {
    // Forgetting to set it should not quietly turn protection off.
    expect(gatewayWith3ds('absent')->getHimalayanRequest3ds())->toBeTrue();
});

it('does not treat an unreadable value as on', function (): void {
    // This is the bug in one line. Anything it could not parse became "yes",
    // which is exactly how 'N' turned into a 3DS request.
    expect(gatewayWith3ds('garbage')->getHimalayanRequest3ds())->toBeFalse();
});

it('sends request3dsFlag N to the bank when the admin turns it off', function (): void {
    // The end of the chain: what PACO actually receives.
    $sent = [];

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('prePaymentUi')->andReturnUsing(function (array $payload) use (&$sent): array {
        $sent = $payload;

        return ['response' => ['Data' => ['paymentPage' => ['paymentPageURL' => 'https://demo/pay']]]];
    });

    (new HimalayanBankGateway(gatewayWith3ds('N'), $client))->charge([
        'amount_cents' => 500,
        'currency' => 'USD',
        'description' => 'Bench',
        'order_reference' => 'ORD-1',
    ]);

    expect($sent['request3dsFlag'])->toBe('N');
});

it('sends request3dsFlag Y when the admin leaves it on', function (): void {
    $sent = [];

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('prePaymentUi')->andReturnUsing(function (array $payload) use (&$sent): array {
        $sent = $payload;

        return ['response' => ['Data' => ['paymentPage' => ['paymentPageURL' => 'https://demo/pay']]]];
    });

    (new HimalayanBankGateway(gatewayWith3ds('Y'), $client))->charge([
        'amount_cents' => 500,
        'currency' => 'USD',
        'description' => 'Bench',
        'order_reference' => 'ORD-2',
    ]);

    expect($sent['request3dsFlag'])->toBe('Y');
});

it('shows the toggle in the position the stored value means', function (): void {
    // A row written through the old text box holds 'N'. The switch has to read
    // that as off, or the admin turns it off, saves, and sees it on again.
    gatewayWith3ds('N');

    Livewire::test(EditGateway::class, ['code' => 'himalayan'])
        ->assertSet('fieldValues.request_3ds', false);
});

it('stores a real boolean when the toggle is used', function (): void {
    gatewayWith3ds('Y');

    Livewire::test(EditGateway::class, ['code' => 'himalayan'])
        ->set('fieldValues.request_3ds', false)
        ->call('save');

    $stored = Gateway::where('code', 'himalayan')->first()->getCredential('request_3ds');

    expect($stored)->toBeFalse()
        ->and(Gateway::where('code', 'himalayan')->first()->getHimalayanRequest3ds())->toBeFalse();
});
