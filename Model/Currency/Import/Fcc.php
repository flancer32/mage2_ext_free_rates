<?php
/**
 * Authors:
 *      krybbio <https://magento.stackexchange.com/users/15051/krybbio>
 *      Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Flancer32\FreeRates\Model\Currency\Import;


class Fcc
    extends \Magento\Directory\Model\Currency\Import\AbstractImport
{
    /**
     * @var string
     */
    const CURRENCY_CONVERTER_URL = 'https://free.currencyconverterapi.com/api/v3/convert?apiKey={{API_KEY}}&q={{CURRENCY_FROM}}_{{CURRENCY_TO}}';

    /** @var \Flancer32\FreeRates\Helper\Config */
    private $hlpCfg;
    /** @var \Magento\Framework\HTTP\ZendClient */
    private $httpClient;
    /** @var \Magento\Framework\Json\Helper\Data */
    private $jsonHelper;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\HTTP\ZendClient $httpClient,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Flancer32\FreeRates\Helper\Config $hlpCfg
    ) {
        parent::__construct($currencyFactory);
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->jsonHelper = $jsonHelper;
        $this->hlpCfg = $hlpCfg;
    }

    /**
     * @param string $currencyFrom
     * @param string $currencyTo
     * @param int $retry
     * @return float|null
     */
    protected function _convert($currencyFrom, $currencyTo, $retry = 0)
    {
        $result = null;
        $apiKey = $this->hlpCfg->getApiKey();
        $url = str_replace('{{API_KEY}}', $apiKey, self::CURRENCY_CONVERTER_URL);
        $url = str_replace('{{CURRENCY_FROM}}', $currencyFrom, $url);
        $url = str_replace('{{CURRENCY_TO}}', $currencyTo, $url);
        $this->logger->info("Currency rates request: $url");
        $delay = $this->hlpCfg->getDelay();
        $timeout = $this->hlpCfg->getTimeout();
        try {
            sleep($delay);
            $this->httpClient->setUri($url);
            $this->httpClient->setConfig(['timeout' => $timeout,]);
            $response = $this->httpClient->request('GET');
            $body = $response->getBody();
            $resultKey = $currencyFrom . '_' . $currencyTo;
            $data = $this->jsonHelper->jsonDecode($body);
            $results = $data['results'][$resultKey];
            $queryCount = $data['query']['count'];
            if (!$queryCount && !isset($results)) {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
            } else {
                $result = (float)$results['val'];
            }
        } catch (\Exception $e) {
            if ($retry == 0) {
                $this->_convert($currencyFrom, $currencyTo, 1);
            } else {
                $this->_messages[] = __('We can\'t retrieve a rate from %1.', $url);
            }
        }
        return $result;
    }
}
