<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

/**
 * The optional operations a provider may implement. Provider differences are
 * expressed only as presence/absence of these flags — the core branches on
 * Capability, never on a provider's name or key. A provider that lacks a
 * capability must both return false from supports() and throw
 * UnsupportedCapabilityException if the matching method is called anyway.
 */
enum Capability
{
    case GET_RATES;
    case CREATE_SHIPMENT;
    case GENERATE_LABEL;
    case SCHEDULE_PICKUP;
    case TRACK;
    case VERIFY_WEBHOOK;
}
