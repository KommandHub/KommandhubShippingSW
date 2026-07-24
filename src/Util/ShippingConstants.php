<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Util;

/**
 * Canonical names for everything this plugin writes into shared Shopware
 * storage.
 *
 * Custom-field keys are global across the installation and are the lookup key
 * for stored data. Defining them here — and only here — means a rename is a
 * one-line change that the type system can follow, instead of a string hunt
 * that silently orphans existing rows.
 */
final class ShippingConstants
{
    public const CUSTOM_FIELD_SET = 'kommandhub_shipping_fieldset';

    public const CUSTOM_FIELD_REFERENCE = 'kommandhub_shipping_reference';

    public const CUSTOM_FIELD_TRACKING_NUMBER = 'kommandhub_shipping_tracking_number';

    public const CUSTOM_FIELD_TRACKING_URL = 'kommandhub_shipping_tracking_url';

    public const CUSTOM_FIELD_CARRIER = 'kommandhub_shipping_carrier';

    public const CUSTOM_FIELD_STATUS = 'kommandhub_shipping_status';
}
