<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Support\HblClient;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * The HBL driver, against what PACO actually returns.
 *
 * The vendor demo parses no transaction statuses at all — its controllers are
 * `return response()->json($request->all())` — so the status vocabulary this
 * package shipped with was guessed. These fixtures use the single-letter codes
 * PACO really sends, the same ones the monolith asserts against captured
 * payloads: `A` authorised, `S` settled, `F` failed, `C` cancelled.
 */
function hblGateway(): Gateway
{
    return Gateway::create([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['NPR', 'USD'],
        'credentials' => [
            'office_id' => 'DEMOOFFICE',
            'api_key' => 'test-api-key',
            'encryption_key_id' => '7664a2ed0dee4879bdfca0e8ce1ac313',
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $tx
 * @return HblClient&MockInterface
 */
function fakeHblReturning(array $tx)
{
    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andReturn(['response' => ['Data' => [$tx]]]);
    app()->instance(HblClient::class, $client);

    return $client;
}

it('settles a transaction PACO reports as S', function (): void {
    // Settled. This was not in the shipped success list, so a genuinely paid
    // transaction read as not-successful.
    fakeHblReturning(['PaymentStatusInfo' => ['PaymentStatus' => 'S'], 'amount' => 150.0, 'currencyCode' => 'NPR']);

    $result = (new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-1']);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('completed');
});

it('settles a transaction PACO reports as A', function (): void {
    // Authorised, pre-settlement. Money is committed; the monolith treats it as paid.
    fakeHblReturning(['PaymentStatusInfo' => ['PaymentStatus' => 'A'], 'amount' => 150.0, 'currencyCode' => 'NPR']);

    expect((new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-2'])->success)->toBeTrue();
});

it('fails a transaction PACO reports as F', function (): void {
    fakeHblReturning(['PaymentStatusInfo' => ['PaymentStatus' => 'F'], 'amount' => 150.0, 'currencyCode' => 'NPR']);

    $result = (new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-3']);

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed');
});

it('cancels a transaction PACO reports as C', function (): void {
    fakeHblReturning(['PaymentStatusInfo' => ['PaymentStatus' => 'C'], 'amount' => 150.0, 'currencyCode' => 'NPR']);

    expect((new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-4'])->status)->toBe('cancelled');
});

it('leaves an in-flight transaction undetermined rather than failed', function (): void {
    // A status the bank has not settled. Reporting this as failed wrote off
    // payments at exactly the moment a buyer returns from the payment page.
    fakeHblReturning(['PaymentStatusInfo' => ['PaymentStatus' => 'I'], 'amount' => 150.0, 'currencyCode' => 'NPR']);

    $result = (new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-5']);

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('pending');
});

it('reads the nested PaymentStatusInfo before the older field names', function (): void {
    fakeHblReturning([
        'PaymentStatusInfo' => ['PaymentStatus' => 'S'],
        'transactionStatus' => 'F',   // stale duplicate; the nested value wins
        'amount' => 10.0,
        'currencyCode' => 'NPR',
    ]);

    expect((new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-6'])->success)->toBeTrue();
});

it('treats an unreachable gateway as undetermined, not as a failure', function (): void {
    // A timeout used to permanently overwrite a captured payment with Failed,
    // with no retry and no way back.
    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andThrow(new RuntimeException('connection timed out'));
    app()->instance(HblClient::class, $client);

    $result = (new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->verify(['order_no' => 'ORD-7']);

    expect($result->status)->toBe('pending');
});

it('does not overwrite a payment when verification is undetermined', function (): void {
    $payment = Payment::factory()->create([
        'gateway' => 'himalayan',
        'gateway_reference' => 'ORD-8',
        'status' => PaymentStatus::Pending,
    ]);

    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andThrow(new RuntimeException('down'));
    app()->instance(HblClient::class, $client);

    hblGateway();
    VerifyPaymentAction::run([
        'gateway' => 'himalayan',
        'order_no' => 'ORD-8',
    ]);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('reports a refund the gateway refused as a failure', function (): void {
    // refund() used to call void() and return success without reading the
    // response, so a PACO rejection was recorded as a completed refund.
    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andReturn(['response' => ['Data' => [[
        'PaymentStatusInfo' => ['PaymentStatus' => 'A'],
        'approvalCode' => '123456',
        'currencyCode' => 'NPR',
    ]]]]);
    $client->shouldReceive('void')->andReturn(['response' => [
        'ResponseCode' => '9999',
        'ErrorDetails' => ['Message' => 'Void not permitted'],
    ]]);
    app()->instance(HblClient::class, $client);

    $result = (new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->refund('ORD-9', 150.0);

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toContain('Void not permitted');
});

it('refunds a settled transaction rather than trying to void it', function (): void {
    // Void only works pre-settlement. Sending one for a settled transaction
    // could never succeed, and the old code would have reported that it had.
    $client = Mockery::mock(HblClient::class);
    $client->shouldReceive('transactionList')->andReturn(['response' => ['Data' => [[
        'PaymentStatusInfo' => ['PaymentStatus' => 'S'],
        'approvalCode' => '654321',
        'currencyCode' => 'USD',
    ]]]]);
    $client->shouldReceive('void')->never();
    $client->shouldReceive('refund')
        ->once()
        ->withArgs(function (array $request): bool {
            // The real approval code, and the payment's own currency — both were
            // hardcoded ('000000' and always NPR).
            return $request['issuerApprovalCode'] === '654321'
                && $request['refundAmount']['currencyCode'] === 'USD';
        })
        ->andReturn(['response' => ['ResponseCode' => '0000']]);
    app()->instance(HblClient::class, $client);

    expect((new HimalayanBankGateway(hblGateway(), app(HblClient::class)))->refund('ORD-10', 25.0)->success)->toBeTrue();
});
