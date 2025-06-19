<?php

declare(strict_types=1);

namespace Peso\Services\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;

final readonly class MockClient
{
    public const ENDPOINT_DAILY = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    public const ENDPOINT_90DAYS = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
    public const ENDPOINT_HISTORY = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    public static function get(): Client
    {
        $client = new Client();

        $client->on(
            new RequestMatcher('/stats/eurofxref/eurofxref-daily.xml', 'www.ecb.europa.eu', ['GET'], ['https']),
            function () {
                return new Response(body: fopen(__DIR__ . '/../data/eurofxref-daily.xml', 'r'));
            }
        );
        $client->on(
            new RequestMatcher('/stats/eurofxref/eurofxref-hist-90d.xml', 'www.ecb.europa.eu', ['GET'], ['https']),
            function () {
                return new Response(body: fopen(__DIR__ . '/../data/eurofxref-hist-90d.xml', 'r'));
            }
        );
        $client->on(
            new RequestMatcher('/stats/eurofxref/eurofxref-hist.xml', 'www.ecb.europa.eu', ['GET'], ['https']),
            function () {
                return new Response(body: fopen(__DIR__ . '/../data/eurofxref-hist.xml', 'r'));
            }
        );

        return $client;
    }
}
