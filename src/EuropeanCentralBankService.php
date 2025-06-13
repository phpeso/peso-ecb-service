<?php

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Clock\SystemClock;
use DateInterval;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18Client;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Core\Services\ExchangeRateServiceInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class EuropeanCentralBankService implements ExchangeRateServiceInterface
{
    /**
     * @see https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html
     */
    private const ENDPOINT_DAILY = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    private const ENDPOINT_90DAYS = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
    private const ENDPOINT_HISTORY = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    public function __construct(
        private CacheInterface $cache = new EmptyCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        private ClientInterface $httpClient = new Psr18Client(),
        private RequestFactoryInterface $requestFactory = new Psr17Factory(),
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    public function send(object $request): ErrorResponse|SuccessResponse
    {
        // TODO: Implement send() method.
    }

    public function supports(object $request): bool
    {
        return $request instanceof CurrentExchangeRateRequest &&
            ($request->baseCurrency === 'EUR' || $request->quoteCurrency === 'EUR');
    }
}
