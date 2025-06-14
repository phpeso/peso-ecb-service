<?php

namespace Peso\Services\EuropeanCentralBankService;

use Sabre\Xml\Reader;

final readonly class XmlFile
{
    public static function parse(string $xml): array
    {
        $reader = new Reader();
        $reader->elementMap = [
            '{http://www.gesmes.org/xml/2002-08-01}Envelope' => XmlFile\Envelope::class,
        ];

        $reader->XML($xml);

        return $reader->parse()['value'];
    }
}
