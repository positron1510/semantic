<?php

namespace App\Services\Semantic\ExpressSemantic\Four;


use App\Services\Semantic\ExpressSemantic\Four\Pthreads\DataProvider;

/**
 * Class CostCalculate
 * @package App\Services\Semantic\ExpressSemantic\Four
 *
 * Расчет стоимости через апи элемента
 */
class CostCalculate extends Semantic implements MultiThreadsInterface
{
    /**
     *
     * Путь апи элемента для получения цен
     */
    const API_URL = 'http://api.mysite.ru:8888/estimate_all/?common_wordstat=%s&accurate_wordstat=%s&click_price_avg=%s&documents_count=0&position=%s&top=10';

    /**
     *
     * Файл с промежуточным результатом
     */
    const RESULT_FILE = 'cost';

    /**
     *
     * Логирующий файл
     */
    const LOG_FILE = '%s/semantic/four/%s/cost.txt';

    /**
     *
     * Кол-во одной порции урлов в многопоточную обработку
     */
    const CHUNK_SIZE = 70;

    /**
     *
     * Класс обработчик потока
     */
    const HANDLER_CLASS = '\App\Services\Semantic\ExpressSemantic\Four\Handlers\CostCalculateHandler';

    /**
     * @param array $result
     * @return array|mixed
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
        # логирование окончательного результата
        $this->finalLog($result);

        return $result;
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
        foreach ($result['keywords'] as $keyword=>$one) {
            $price = $one['price'];
            $api_urls[$keyword] = sprintf(self::API_URL, $one['common_ws'], $one['accurate_ws'], $price, $one['pos']);
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
        $costs = $provider->getCosts();

        foreach ($costs as $keyword=>$data) {
            if ('error' === $keyword) {
                if (!$result['error']) {
                    $result['error'] = [];
                }
                $result['error'][] = $data;
            }else {
                $result['keywords'][$keyword]['min'] = $data->min;
                $result['keywords'][$keyword]['max'] = $data->max;
                $result['keywords'][$keyword]['price'] = $data->price;
                $result['keywords'][$keyword]['term_promotion'] = $data->term_promotion;
                $result['keywords'][$keyword]['complexity_common'] = $data->complexity_common;
                $result['keywords'][$keyword]['complexity'] = $data->complexity;
            }
        }

        foreach ($result['keywords'] as $keyword=>&$one) {
            $one['pos'] = $one['pos'] > 100 ? '> 100' : $one['pos'];
            unset($one['lemm_keyword']);

            if (!isset($one['complexity_common'])) {
                $one['complexity_common'] = '|';
                $one['complexity'] = 1;
                $one['term_promotion'] = '-';
                continue;
            }

            $complexity = '';
            for ($i = 1; $i <= $one['complexity_common']; $i++) $complexity .= '|';
            $one['complexity_common'] = $complexity;
        }

        if ($result['error']) {
            $result['error'] = sprintf('Ошибки при расчёте стоимости из апи : %s%s', PHP_EOL . PHP_EOL, json_encode($result['error']));
            file_put_contents(sprintf(self::LOG_FILE, public_path(), $result['domain']),
                sprintf('[%s] домен - %s ,некорректные данные:%s%s', date('Y-m-d H:i:s'), $result['domain'], PHP_EOL . PHP_EOL, $result['error']), FILE_APPEND);
        }

        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }
    }
}