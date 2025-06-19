<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Helpers\Calculator;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Services\EuropeanCentralBankService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;

class WrappedServicesTest extends TestCase
{
    public function testReversible(): void
    {
        $http = MockClient::get();

        $baseService = new EuropeanCentralBankService(httpClient: $http);
        $service = EuropeanCentralBankService::reversible(httpClient: $http);

        $request = new CurrentExchangeRateRequest('USD', 'EUR');

        // base service doesn't support
        self::assertInstanceOf(ErrorResponse::class, $baseService->send($request));

        $response = $service->send($request);
        self::assertInstanceOf(SuccessResponse::class, $response);
        // ignore calculator changes
        self::assertEquals('0.86896', Calculator::instance()->round($response->rate, 5)->value);
    }

    public function testUniversal(): void
    {
        $http = MockClient::get();

        $baseService = new EuropeanCentralBankService(httpClient: $http);
        $service = EuropeanCentralBankService::universal(httpClient: $http);

        $request = new CurrentExchangeRateRequest('AUD', 'NZD');

        // base service doesn't support
        self::assertInstanceOf(ErrorResponse::class, $baseService->send($request));

        $response = $service->send($request);
        self::assertInstanceOf(SuccessResponse::class, $response);
        // ignore calculator changes
        self::assertEquals('1.07869', Calculator::instance()->round($response->rate, 5)->value);
    }
}
