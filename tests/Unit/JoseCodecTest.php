<?php

use Jose\Component\Core\JWK;
use Kreetancraft\PaymentGateway\Support\JoseCodec;

/**
 * The JOSE envelope PACO receives.
 *
 * The `kid` assertions are the point. JoseCodec used to read the key id from
 * `payment-gateway.gateways.himalayan.encryption_key_id` — a config key that has
 * never existed — and it is container-autowired with no arguments, so the
 * constructor default was always null. Every outbound JWE went out with
 * `kid: ""`, which PACO requires and rejects when empty. Nothing caught it
 * because nothing asserted on the built token.
 */
function rsaKey(): JWK
{
    // PHP's openssl extension cannot generate a key on every machine (it needs a
    // readable openssl.cnf), so shell out to the CLI and load the PEM through the
    // codec's own loader — which also exercises that loader. Generated once and
    // reused: 2048-bit keygen is slow enough to notice per test.
    static $pem = null;

    if ($pem === null) {
        $file = tempnam(sys_get_temp_dir(), 'jose').'.pem';
        exec('openssl genrsa -out '.escapeshellarg($file).' 2048 2>&1', $out, $code);

        if ($code !== 0 || ! is_file($file)) {
            test()->markTestSkipped('openssl CLI is not available to generate a test key.');
        }

        $pem = (string) file_get_contents($file);
        @unlink($file);
    }

    return (new JoseCodec)->loadPrivateKey($pem);
}

it('puts the configured key id in the JWE protected header', function (): void {
    $key = rsaKey();

    $token = (new JoseCodec)->encrypt(
        ['request' => ['hello' => 'world']],
        $key,
        $key,
        '7664a2ed0dee4879bdfca0e8ce1ac313',
    );

    // The protected header is the first compact segment, base64url, unencrypted.
    $header = json_decode(
        base64_decode(strtr(explode('.', $token)[0], '-_', '+/'), true),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($header['kid'])->toBe('7664a2ed0dee4879bdfca0e8ce1ac313')
        ->and($header['alg'])->toBe('RSA-OAEP')
        ->and($header['enc'])->toBe('A128CBC-HS256')
        ->and($header['typ'])->toBe('JWT');
});

it('refuses to build a token with no key id', function (): void {
    // Signing with an empty kid produces a request PACO will reject, and the
    // failure surfaces at the bank rather than here. Fail where it is fixable.
    $key = rsaKey();

    expect(fn () => (new JoseCodec)->encrypt(['request' => []], $key, $key, ''))
        ->toThrow(InvalidArgumentException::class, 'encryption key id');
});

it('refuses a key id that is only whitespace', function (): void {
    $key = rsaKey();

    expect(fn () => (new JoseCodec)->encrypt(['request' => []], $key, $key, '   '))
        ->toThrow(InvalidArgumentException::class);
});

it('round-trips a payload it signed and encrypted', function (): void {
    $codec = new JoseCodec;
    $key = rsaKey();

    $payload = [
        'request' => ['orderNo' => 'ORD-1'],
        'iss' => 'api-key',
        'aud' => 'PacoAudience',
        'iat' => now()->timestamp,
        'nbf' => now()->timestamp,
        'exp' => now()->addHour()->timestamp,
    ];

    // Decrypting expects PACO's issuer and our own api key as the audience, so
    // build a token shaped the way a PACO response is.
    $response = $codec->encrypt(
        [...$payload, 'iss' => 'PacoIssuer', 'aud' => 'api-key'],
        $key,
        $key,
        'kid-1',
    );

    $claims = $codec->decrypt($response, $key, $key, 'api-key');

    expect($claims['request']['orderNo'])->toBe('ORD-1');
});

it('rejects a response addressed to somebody else', function (): void {
    $codec = new JoseCodec;
    $key = rsaKey();

    $token = $codec->encrypt([
        'request' => [],
        'iss' => 'PacoIssuer',
        'aud' => 'somebody-elses-key',
        'iat' => now()->timestamp,
        'nbf' => now()->timestamp,
        'exp' => now()->addHour()->timestamp,
    ], $key, $key, 'kid-1');

    expect(fn () => $codec->decrypt($token, $key, $key, 'api-key'))->toThrow(Exception::class);
});

it('rejects a response from the wrong issuer', function (): void {
    $codec = new JoseCodec;
    $key = rsaKey();

    $token = $codec->encrypt([
        'request' => [],
        'iss' => 'NotPaco',
        'aud' => 'api-key',
        'iat' => now()->timestamp,
        'nbf' => now()->timestamp,
        'exp' => now()->addHour()->timestamp,
    ], $key, $key, 'kid-1');

    expect(fn () => $codec->decrypt($token, $key, $key, 'api-key'))->toThrow(Exception::class);
});

it('rejects an expired response', function (): void {
    $codec = new JoseCodec;
    $key = rsaKey();

    // Beyond the 120s leeway.
    $token = $codec->encrypt([
        'request' => [],
        'iss' => 'PacoIssuer',
        'aud' => 'api-key',
        'iat' => now()->subHour()->timestamp,
        'nbf' => now()->subHour()->timestamp,
        'exp' => now()->subMinutes(10)->timestamp,
    ], $key, $key, 'kid-1');

    expect(fn () => $codec->decrypt($token, $key, $key, 'api-key'))->toThrow(Exception::class);
});

it('rejects a tampered token', function (): void {
    $codec = new JoseCodec;
    $key = rsaKey();

    $token = $codec->encrypt([
        'request' => ['orderNo' => 'ORD-1'],
        'iss' => 'PacoIssuer',
        'aud' => 'api-key',
        'iat' => now()->timestamp,
        'nbf' => now()->timestamp,
        'exp' => now()->addHour()->timestamp,
    ], $key, $key, 'kid-1');

    $parts = explode('.', $token);
    $parts[3] = strtr(base64_encode('tampered-ciphertext'), '+/', '-_');

    expect(fn () => $codec->decrypt(implode('.', $parts), $key, $key, 'api-key'))->toThrow(Exception::class);
});
