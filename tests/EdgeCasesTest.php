<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Clock\StaticClock;
use Arokettu\Date\Calendar;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Services\EuropeanCentralBankService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EdgeCasesTest extends TestCase
{
    public function testInvalidRequest(): void
    {
        $service = new EuropeanCentralBankService();

        $response = $service->send(new stdClass());
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals('Unsupported request type: "stdClass"', $response->exception->getMessage());
    }

    public function testFutureDate(): void
    {
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $future = Calendar::parse('2025-06-19');

        $service = new EuropeanCentralBankService(clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'USD', $future));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Date seems to be in future', $response->exception->getMessage());
    }

    public function testHttpFailure(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(404, body: 'Not found or something'));

        $service = new EuropeanCentralBankService(httpClient: $http);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage('HTTP error 404. Response is "Not found or something"');
        $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
    }
}
