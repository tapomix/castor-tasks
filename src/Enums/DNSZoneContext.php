<?php

namespace Tapomix\Castor\Enums;

enum DNSZoneContext: string
{
    case Raw = 'raw'; // = raw zone with placeholder as serial (*committed*)
    case Unsigned = 'unsigned'; // = raw with placeholder replaced by real serial (*ignored*)
    case Signed = 'signed'; // = signed zone used in dns server (*ignored*)
}
