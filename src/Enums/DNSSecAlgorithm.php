<?php

namespace Tapomix\Castor\Enums;

enum DNSSecAlgorithm: int
{
    // @see https://www.iana.org/assignments/dns-sec-alg-numbers/dns-sec-alg-numbers.xhtml

    // case RSASHA1 = 5; // deprecated
    // case NSEC3RSASHA1 = 7; // deprecated

    case RSASHA256 = 8;
    case RSASHA512 = 10;
    case ECDSAP256SHA256 = 13;
    case ECDSAP384SHA384 = 14;
    case ED25519 = 15;
    case ED448 = 16;
}
