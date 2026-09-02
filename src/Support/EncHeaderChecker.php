<?php

namespace Kreetancraft\PaymentGateway\Support;

use Jose\Component\Checker\HeaderChecker;
use Jose\Component\Checker\InvalidHeaderException;

/**
 * Verifies the JWE "enc" (content encryption algorithm) header. web-token/jwt-framework v4
 * dropped the jose/easy package's ContentEncryptionAlgorithmChecker, so this is a minimal
 * replacement following the same HeaderChecker contract as the framework's own AlgorithmChecker.
 */
final readonly class EncHeaderChecker implements HeaderChecker
{
    private const HEADER_NAME = 'enc';

    /**
     * @param  list<string>  $supportedAlgorithms
     */
    public function __construct(
        private array $supportedAlgorithms,
        private bool $protectedHeader = true,
    ) {}

    public function checkHeader(mixed $value): void
    {
        if (! is_string($value)) {
            throw new InvalidHeaderException('"enc" must be a string.', self::HEADER_NAME, $value);
        }

        if (! in_array($value, $this->supportedAlgorithms, true)) {
            throw new InvalidHeaderException('Unsupported content encryption algorithm.', self::HEADER_NAME, $value);
        }
    }

    public function supportedHeader(): string
    {
        return self::HEADER_NAME;
    }

    public function protectedHeaderOnly(): bool
    {
        return $this->protectedHeader;
    }
}
