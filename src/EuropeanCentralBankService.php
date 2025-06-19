<?php

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Clock\SystemClock;
use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use DateInterval;
use Override;
use Peso\Core\Exceptions\ConversionRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Exceptions\RuntimeException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Core\Services\ExchangeRateServiceInterface;
use Peso\Core\Services\IndirectExchangeService;
use Peso\Core\Services\ReversibleService;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\CacheFailureException;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Peso\Core\Types\Decimal;
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
        private CacheInterface $cache = new NullCache(),
        private DateInterval $currentTtl = new DateInterval('PT1H'),
        private DateInterval $historyTtl = new DateInterval('P60D'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    public static function reversible(
        CacheInterface $cache = new NullCache(),
        DateInterval $currentTtl = new DateInterval('PT1H'),
        DateInterval $historyTtl = new DateInterval('P60D'),
        ClientInterface $httpClient = new DiscoveredHttpClient(),
        RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        ClockInterface $clock = new SystemClock(),
    ): ExchangeRateServiceInterface {
        return new ReversibleService(new self($cache, $currentTtl, $historyTtl, $httpClient, $requestFactory, $clock));
    }

    public static function universal(
        CacheInterface $cache = new NullCache(),
        DateInterval $currentTtl = new DateInterval('PT1H'),
        DateInterval $historyTtl = new DateInterval('P60D'),
        ClientInterface $httpClient = new DiscoveredHttpClient(),
        RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        ClockInterface $clock = new SystemClock(),
    ): ExchangeRateServiceInterface {
        return new IndirectExchangeService(
            self::reversible($cache, $currentTtl, $historyTtl, $httpClient, $requestFactory, $clock),
            'EUR'
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(object $request): ErrorResponse|SuccessResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return self::performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return self::performHistoricalRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performCurrentRequest(CurrentExchangeRateRequest $request): ErrorResponse|SuccessResponse
    {
        if ($request->baseCurrency !== 'EUR') {
            return new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
        }

        $ratesXml = $this->getXmlData(self::ENDPOINT_DAILY, $this->currentTtl);
        $rates = array_pop($ratesXml); // there is only one date

        return isset($rates[$request->quoteCurrency]) ?
            new SuccessResponse(new Decimal($rates[$request->quoteCurrency])) :
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
    }

    private function performHistoricalRequest(HistoricalExchangeRateRequest $request): ErrorResponse|SuccessResponse
    {
        if ($request->baseCurrency !== 'EUR') {
            return new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
        }
        $today = Calendar::fromDateTime($this->clock->now());

        $rates = null;
        if ($today->sub($request->date) < 0) {
            return new ErrorResponse(new ConversionRateNotFoundException('Date seems to be in future'));
        }
        if ($today->sub($request->date) <= 90) {
            $ratesXml = $this->getXmlData(self::ENDPOINT_90DAYS, $this->currentTtl);
            $rates = $this->findDayRates($request->date, $ratesXml);
        }
        if ($rates === null) { // not found or not in the last 90 days
            $ratesXml = $this->getXmlData(self::ENDPOINT_HISTORY, $this->historyTtl);
            $rates = $this->findDayRates($request->date, $ratesXml);
        }

        return isset($rates[$request->quoteCurrency]) ?
            new SuccessResponse(new Decimal($rates[$request->quoteCurrency])) :
            new ErrorResponse(ConversionRateNotFoundException::fromRequest($request));
    }

    private function findDayRates(Date $date, array $ratesXml): array|null
    {
        $date = $date->toString();

        if (isset($ratesXml[$date])) { // easy mode
            return $ratesXml[$date];
        }

        foreach ($ratesXml as $dateKey => $rates) {
            if (strcmp($dateKey, $date) > 0) { // skip bigger values
                continue;
            }
            return $rates;
        }

        return null;
    }

    /**
     * @throws RuntimeException
     */
    private function getXmlData(string $url, DateInterval $ttl): array
    {
        $cacheKey = hash('sha1', __CLASS__ . '|' . $url);

        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw HttpFailureException::fromResponse($request, $response);
        }

        $data = EuropeanCentralBankService\XmlFile::parse((string)$response->getBody());

        $this->cache->set($cacheKey, $data, $ttl) ?: throw new CacheFailureException('Cache service error');

        return $data;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function supports(object $request): bool
    {
        return ($request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest)
            && $request->baseCurrency === 'EUR';
    }
}
