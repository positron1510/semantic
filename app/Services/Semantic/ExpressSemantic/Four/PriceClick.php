<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Semantic\ExpressSemantic\Four\Pthreads\DataProvider;


/**
 * Class PriceClick
 * @package App\Semantic\Four
 *
 * Класс расчета цены за клик через апи spywords
 */
class PriceClick extends DecorateSemantic implements MultiThreadsInterface
{
    /**
     *
     * API url
     */
    const API_URL = 'https://api.spywords.ru/?method=KeywordOverview&word=%s&login=%s&token=%s';

    /**
     *
     * Файл с промежуточным результатом
     */
    const RESULT_FILE = 'price_click';

    /**
     *
     * Логирующий файл
     */
    const LOG_FILE = '%s/semantic/four/%s/price_click.txt';

    /**
     *
     * Класс обработчик потока
     */
    const HANDLER_CLASS = '\App\Services\Semantic\ExpressSemantic\Four\Handlers\PriceClickHandler';

    /**
     * @param array $result
     * @return mixed|void
     *
     * Запуск класса
     */
    public function execute(array &$result)
    {
        if ($result['error']) goto finish;

        $provider = new DataProvider();

        $api_urls = $this->getApiUrls($result);
        $this->runChunks($provider, $api_urls);
        $this->postCalculation($provider, $result);

        finish:
        $this->semantic->execute($result);
    }

    /**
     * @param array $result
     * @return array
     *
     * Получение массива урлов для дальнейшей многопоточной обработки
     */
    public function getApiUrls(array &$result): array
    {
        $api_urls = [];
        foreach ($result['keywords'] as $phrase=>$one) {
            $api_urls[$phrase] = sprintf(self::API_URL, urlencode(iconv('utf-8', 'windows-1251', $phrase)), self::LOGIN, self::TOKEN);
        }

        return $api_urls;
    }

    /**
     * @param DataProvider $provider
     * @param array $result
     *
     * Постобработка после завершения многопоточного анализа
     */
    public function postCalculation(DataProvider $provider, array &$result): void
    {
        $prices = $provider->getPrices();

        foreach ($prices as $keyword=>$price) {
            if ('error' === $keyword) {
                if (!$result['error']) {
                    $result['error'] = [];
                }
                $result['error'][] = $price;
            }else {
                $result['keywords'][$keyword]['price'] = $price;
            }
        }

        foreach ($result['keywords'] as $word=>&$one) {
            $one['price'] = $one['price'] ?? 0.03;
        }

        if ($result['error']) {
            $result['error'] = sprintf('Ошибки при расчёте цены за клик: %s%s', PHP_EOL . PHP_EOL, json_encode($result['error']));
            file_put_contents(sprintf(self::LOG_FILE, public_path(), $result['domain']),
                sprintf('[%s] домен - %s ,некорректные данные:%s%s', date('Y-m-d H:i:s'), $result['domain'], PHP_EOL . PHP_EOL, $result['error']), FILE_APPEND);
        }

        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }
    }
}