<?php

declare(strict_types=1);

namespace Peso\Services\EuropeanCentralBankService\XmlFile;

use Peso\Services\RuntimeException;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

final readonly class OuterCube implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $dates = [];
        $data = $reader->parseInnerTree([
            '{http://www.ecb.int/vocabulary/2002-08-01/eurofxref}Cube' => DateCube::class,
        ]);

        foreach ($data as $date) {
            $key = $date['attributes']['time'] ?? throw new RuntimeException('Invalid data returned');
            $value = $date['value'];

            $dates[$key] = $value;
        }

        return $dates;
    }
}
