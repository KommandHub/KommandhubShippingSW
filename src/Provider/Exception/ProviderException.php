<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Exception;

use Kommandhub\ShippingSW\Exception\ShippingException;

/**
 * Base for every provider-layer failure. Extends the plugin's ShippingException
 * so callers can catch the whole plugin's errors with one type.
 */
class ProviderException extends ShippingException
{
}
