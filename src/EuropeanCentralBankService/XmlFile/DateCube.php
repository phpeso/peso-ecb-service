<?php

declare(strict_types=1);

namespace Peso\Services\EuropeanCentralBankService\XmlFile;

use Peso\Services\RuntimeException;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

final readonly class DateCube implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $currencies = [];
        $data = $reader->parseInnerTree([]); // empty map
        foreach ($data as $currency) {
            $key = $currency['attributes']['currency'] ?? throw new RuntimeException('Invalid data returned');
            $value = $currency['attributes']['rate'] ?? throw new RuntimeException('Invalid data returned');
            $currencies[$key] = $value;
        }
        return $currencies;
    }
}
