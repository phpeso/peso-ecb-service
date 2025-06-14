<?php

namespace Peso\Services\EuropeanCentralBankService\XmlFile;

use Sabre\Xml\Element\KeyValue;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

final readonly class Envelope implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $reader->pushContext();
        $reader->elementMap = [
            '{http://www.ecb.int/vocabulary/2002-08-01/eurofxref}Cube' => OuterCube::class,
        ];
        $data = KeyValue::xmlDeserialize($reader);
        $reader->popContext();

        return $data['{http://www.ecb.int/vocabulary/2002-08-01/eurofxref}Cube'];
    }
}
