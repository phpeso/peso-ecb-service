<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Clock\StaticClock;
use Arokettu\Date\Calendar;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Services\EuropeanCentralBankService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class HistoricalRequestTest extends TestCase
{
    public function testRateWhithin90Days(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-05-15');

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('1.1185', $response->rate->value);
        self::assertEquals('2025-05-15', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'MXN', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('21.636', $response->rate->value);
        self::assertEquals('2025-05-15', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('163.3', $response->rate->value);
        self::assertEquals('2025-05-15', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
        self::assertEquals(MockClient::ENDPOINT_90DAYS, (string)$http->getLastRequest()->getUri());
    }

    public function testNoRateWhithin90Days(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-05-15');

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        // unknown currency
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'KZT', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals(
            'Unable to find exchange rate for EUR/KZT on 2025-05-15',
            $response->exception->getMessage()
        );

        // reverse rate
        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'EUR', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals(
            'Unable to find exchange rate for USD/EUR on 2025-05-15',
            $response->exception->getMessage()
        );
    }

    public function testRateWhithin90DaysWithDiscovery(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-05-25'); // no rates on sundays

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('1.1301', $response->rate->value); // Friday rate
        self::assertEquals('2025-05-23', $response->date->toString()); // Friday

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'MXN', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('21.854', $response->rate->value);
        self::assertEquals('2025-05-23', $response->date->toString()); // Friday

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('161.13', $response->rate->value);
        self::assertEquals('2025-05-23', $response->date->toString()); // Friday

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
        self::assertEquals(MockClient::ENDPOINT_90DAYS, (string)$http->getLastRequest()->getUri());
    }

    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-01-15');

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('1.03', $response->rate->value);
        self::assertEquals('2025-01-15', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'MXN', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('21.09', $response->rate->value);
        self::assertEquals('2025-01-15', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('161.75', $response->rate->value);
        self::assertEquals('2025-01-15', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
        self::assertEquals(MockClient::ENDPOINT_HISTORY, (string)$http->getLastRequest()->getUri());
    }

    public function testNoRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-01-15');

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        // unknown currency
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'KZT', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals(
            'Unable to find exchange rate for EUR/KZT on 2025-01-15',
            $response->exception->getMessage()
        );

        // reverse rate
        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'EUR', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals(
            'Unable to find exchange rate for USD/EUR on 2025-01-15',
            $response->exception->getMessage()
        );
    }

    public function testRateWithDiscovery(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-01-19'); // no rates on sundays

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('1.0298', $response->rate->value); // Friday rate
        self::assertEquals('2025-01-17', $response->date->toString()); // Friday

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'MXN', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('21.4193', $response->rate->value);
        self::assertEquals('2025-01-17', $response->date->toString()); // Friday

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('160.23', $response->rate->value);
        self::assertEquals('2025-01-17', $response->date->toString()); // Friday

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
        self::assertEquals(MockClient::ENDPOINT_HISTORY, (string)$http->getLastRequest()->getUri());
    }

    public function test90DaysMiss(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-03-18'); // 'now' that's way off
        $date = Calendar::parse('2025-02-18'); // should be in 90-day frame but isn't

        $service = new EuropeanCentralBankService(cache: $cache, httpClient: $http, clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('1.0447', $response->rate->value);
        self::assertEquals('2025-02-18', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'MXN', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('21.1919', $response->rate->value);
        self::assertEquals('2025-02-18', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'JPY', $date));
        self::assertInstanceOf(SuccessResponse::class, $response);
        self::assertEquals('158.55', $response->rate->value);
        self::assertEquals('2025-02-18', $response->date->toString());

        self::assertCount(2, $http->getRequests()); // subsequent requests are cached, but we hit both history urls
    }
}
