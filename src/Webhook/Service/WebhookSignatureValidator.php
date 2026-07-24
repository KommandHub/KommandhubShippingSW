<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Webhook\Service;

use Kommandhub\ShippingSW\Setting\Service\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verifies that an inbound webhook really came from Shipping.
 *
 * The endpoint is public and unauthenticated, so this is the only thing
 * standing between the internet and the order state machine. Two rules:
 *
 * 1. Compare with hash_equals(), never `===` — a timing-variable comparison
 *    leaks the expected digest byte by byte.
 * 2. Fail closed. A missing header or unconfigured secret is a rejection, not
 *    a pass-through.
 *
 * Adjust SIGNATURE_HEADER and ALGORITHM to what the provider documents. Some
 * providers sign a canonical string (timestamp + body) rather than the raw
 * body — if so, build that string here and nowhere else.
 */
class WebhookSignatureValidator
{
    private const SIGNATURE_HEADER = 'x-shipping-signature';

    private const ALGORITHM = 'sha512';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @throws AccessDeniedHttpException when the request is not authentic
     */
    public function validate(Request $request, ?string $salesChannelId = null): void
    {
        $signature = $request->headers->get(self::SIGNATURE_HEADER);

        if ($signature === null || $signature === '') {
            throw new AccessDeniedHttpException('Missing Shipping signature header.');
        }

        $secret = $this->getSecret($salesChannelId);

        if ($secret === '') {
            throw new AccessDeniedHttpException('Shipping webhook secret is not configured.');
        }

        $expected = hash_hmac(self::ALGORITHM, $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid Shipping signature.');
        }
    }

    private function getSecret(?string $salesChannelId): string
    {
        // Generic fallback validator. In the target architecture each provider
        // verifies its own signature via ShippingProviderInterface::verifyWebhook;
        // this shared secret backs providers that use a single HMAC secret.
        return $this->config->getString('webhookSecret', $salesChannelId);
    }
}
