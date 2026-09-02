<?php

namespace Kreetancraft\PaymentGateway\Support;

use InvalidArgumentException;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Checker\AudienceChecker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\ExpirationTimeChecker;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\IssuerChecker;
use Jose\Component\Checker\NotBeforeChecker;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128CBCHS256;
use Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Encryption\Serializer\CompactSerializer as JWECompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JWSCompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use RuntimeException;
use Symfony\Component\Clock\Clock;

/**
 * JWS-then-JWE encode/decode for the 2C2P PACO Core Payment API: requests are signed
 * (PS256) then encrypted (RSA-OAEP / A128CBC-HS256); responses are decrypted then
 * signature-verified. Ported from HBL's PHP demo kit onto the current
 * web-token/jwt-framework major version (no jose/easy, single AlgorithmManager per
 * builder/loader, no compression).
 */
class JoseCodec
{
    private const JWS_ALGORITHM = 'PS256';

    private const JWE_ALGORITHM = 'RSA-OAEP';

    private const JWE_ENCRYPTION = 'A128CBC-HS256';

    private const TOKEN_TYPE = 'JWT';

    private JWSCompactSerializer $jwsCompactSerializer;

    private JWSBuilder $jwsBuilder;

    private JWSLoader $jwsLoader;

    private JWECompactSerializer $jweCompactSerializer;

    private JWEBuilder $jweBuilder;

    private JWELoader $jweLoader;

    public function __construct()
    {
        $this->jwsCompactSerializer = new JWSCompactSerializer;
        $this->jwsBuilder = new JWSBuilder(new AlgorithmManager([new PS256]));
        $this->jwsLoader = new JWSLoader(
            serializerManager: new JWSSerializerManager([new JWSCompactSerializer]),
            jwsVerifier: new JWSVerifier(new AlgorithmManager([new PS256])),
            headerCheckerManager: new HeaderCheckerManager(
                checkers: [new AlgorithmChecker([self::JWS_ALGORITHM], protectedHeader: true)],
                tokenTypes: [new JWSTokenSupport],
            ),
        );

        $this->jweCompactSerializer = new JWECompactSerializer;
        $this->jweBuilder = new JWEBuilder(new AlgorithmManager([new RSAOAEP, new A128CBCHS256]));
        $this->jweLoader = new JWELoader(
            serializerManager: new JWESerializerManager([new JWECompactSerializer]),
            jweDecrypter: new JWEDecrypter(new AlgorithmManager([new RSAOAEP, new A128CBCHS256])),
            headerCheckerManager: new HeaderCheckerManager(
                checkers: [
                    new AlgorithmChecker([self::JWE_ALGORITHM], protectedHeader: true),
                    new EncHeaderChecker([self::JWE_ENCRYPTION]),
                ],
                tokenTypes: [new JWETokenSupport],
            ),
        );
    }

    /**
     * Sign then encrypt a JOSE payload for a PACO request.
     *
     * $encryptionKeyId is PACO's key id and goes in the JWE protected header. It
     * used to be read here from `payment-gateway.gateways.himalayan.encryption_key_id`,
     * a config key that has never existed — the value is collected by the admin
     * UI into the gateway's encrypted credentials, which this class cannot see.
     * The codec is also container-autowired with no arguments, so the constructor
     * default was always null and every outbound JWE went out with `kid: ""`.
     * PACO requires it. Callers pass it now, from wherever they keep credentials.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException when the key id is missing
     */
    public function encrypt(array $payload, JWK $signingKey, JWK $encryptingKey, string $encryptionKeyId): string
    {
        if (trim($encryptionKeyId) === '') {
            throw new InvalidArgumentException(
                'The PACO encryption key id is not configured. Set it on the Himalayan gateway '
                .'credentials; without it every request is signed with an empty `kid` and rejected.'
            );
        }

        $jws = $this->jwsBuilder
            ->create()
            ->withPayload(json_encode($payload, JSON_THROW_ON_ERROR))
            ->addSignature($signingKey, [
                'alg' => self::JWS_ALGORITHM,
                'typ' => self::TOKEN_TYPE,
            ])
            ->build();

        $jwe = $this->jweBuilder
            ->create()
            ->withPayload($this->jwsCompactSerializer->serialize($jws))
            ->withSharedProtectedHeader([
                'alg' => self::JWE_ALGORITHM,
                'enc' => self::JWE_ENCRYPTION,
                'kid' => $encryptionKeyId,
                'typ' => self::TOKEN_TYPE,
            ])
            ->addRecipient($encryptingKey)
            ->build();

        return $this->jweCompactSerializer->serialize($jwe, 0);
    }

    /**
     * Decrypt then verify a JOSE token from a PACO response, checking that it was
     * issued by PACO and addressed to us.
     *
     * @return array<string, mixed>
     */
    public function decrypt(string $token, JWK $decryptingKey, JWK $verificationKey, string $expectedAudience): array
    {
        $recipient = null;
        $jwe = $this->jweLoader->loadAndDecryptWithKey($token, $decryptingKey, $recipient);

        $signature = null;
        $jws = $this->jwsLoader->loadAndVerifyWithKey($jwe->getPayload(), $verificationKey, $signature);

        $claims = json_decode($jws->getPayload(), true, flags: JSON_THROW_ON_ERROR);

        $clock = new Clock;
        $leeway = 120;
        $claimCheckerManager = new ClaimCheckerManager([
            new NotBeforeChecker($clock, $leeway),
            new ExpirationTimeChecker($clock, $leeway),
            new AudienceChecker($expectedAudience),
            new IssuerChecker(['PacoIssuer']),
        ]);
        // The second argument is the list of claims that MUST be present.
        // Without it the library simply skips a checker whose claim is absent, so
        // a response carrying no `aud` sailed past the audience check. The vendor
        // demo has the same hole.
        $claimCheckerManager->check($claims, ['iss', 'aud', 'exp']);

        return $claims;
    }

    /**
     * Load a PEM-wrapped or raw-base64 RSA private key. Tries both PKCS#8 (PRIVATE KEY)
     * and PKCS#1 (RSA PRIVATE KEY) to match demo (RSA PRIVATE KEY) and modern exports.
     */
    public function loadPrivateKey(string $key): JWK
    {
        $key = trim($key);
        if ($key === '') {
            throw new RuntimeException('HBL JOSE key material is empty (private).');
        }

        $candidates = [];
        if (str_starts_with($key, '-----BEGIN')) {
            $candidates[] = $key;
            // Also try alternate header if PKCS#1 vs PKCS#8 mismatch
            if (str_contains($key, 'RSA PRIVATE KEY')) {
                $candidates[] = str_replace('RSA PRIVATE KEY', 'PRIVATE KEY', $key);
            } elseif (str_contains($key, 'PRIVATE KEY')) {
                $candidates[] = str_replace('PRIVATE KEY', 'RSA PRIVATE KEY', $key);
            }
        } else {
            $candidates[] = "-----BEGIN PRIVATE KEY-----\n{$key}\n-----END PRIVATE KEY-----";
            $candidates[] = "-----BEGIN RSA PRIVATE KEY-----\n{$key}\n-----END RSA PRIVATE KEY-----";
        }

        $last = null;
        foreach ($candidates as $pem) {
            try {
                return JWKFactory::createFromKey($pem);
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        throw new RuntimeException('Failed to load private key: '.($last?->getMessage() ?? 'unknown'), 0, $last);
    }

    /**
     * Load a PEM-wrapped or raw-base64 RSA public key.
     */
    public function loadPublicKey(string $key): JWK
    {
        $key = trim($key);
        if ($key === '') {
            throw new RuntimeException('HBL JOSE key material is empty (public).');
        }

        if (str_starts_with($key, '-----BEGIN')) {
            return JWKFactory::createFromKey($key);
        }

        return JWKFactory::createFromKey("-----BEGIN PUBLIC KEY-----\n{$key}\n-----END PUBLIC KEY-----");
    }
}
