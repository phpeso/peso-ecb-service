<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Services\EuropeanCentralBankService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class CurrentRequestTest extends TestCase
{
    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('1.1508', $response->rate->value);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'MXN'));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('21.8075', $response->rate->value);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'JPY'));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('166.67', $response->rate->value);

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testNoRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http);

        // unknown currency
        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'KZT'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals('Unable to find exchange rate for EUR/KZT', $response->exception->getMessage());

        // reverse rate
        $response = $service->send(new CurrentExchangeRateRequest('USD', 'EUR'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals('Unable to find exchange rate for USD/EUR', $response->exception->getMessage());
    }
}
