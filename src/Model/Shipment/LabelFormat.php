<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Shipment;

enum LabelFormat: string
{
    case PDF = 'pdf';
    case PNG = 'png';
    case ZPL = 'zpl';
}
