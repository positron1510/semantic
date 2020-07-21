<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Semantic\ExpressSemantic\Four\Pthreads\DataProvider;


class SpywordsSemanticCalculate extends DecorateSemantic implements MultiThreadsInterface
{
    /**
     *
     * API url spywords
     */
    const API_URL = 'https://api.spywords.ru/?method=DomainUrlOrganic&site=%s&url=%s&se=yandex&login=%s&token=%s&limit=100';

    /**
     *
     * Файл промежуточного результата
     */
    const RESULT_FILE = 'spywords';

    /**
     *
     * Логирующий файл
     */
    const LOG_FILE = '%s/semantic/four/%s/spywords_semantic.txt';

    /**
     *
     * Класс обработчик потока
     */
    const HANDLER_CLASS = '\App\Services\Semantic\ExpressSemantic\Four\Handlers\SpywordsSemanticCalculateHandler';

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
        foreach ($result['data'] as $one) {
            $api_urls[$one['url']] = sprintf(self::API_URL, $result['domain'], $one['url'], self::LOGIN, self::TOKEN);
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
        $spywords_semantic = $provider->getSpywordsSemantic();

        $keywords = [];
        $data = [];

        foreach ($spywords_semantic as $key=>$item) {
            if ('error' === $key) {
                if (!$result['error']) {
                    $result['error'] = [];
                }
                $result['error'][] = $item;

                continue;
            }

            foreach ($item as $one) {
                if (!in_array($one->keyword, $keywords)) {
                    $keywords[] = $one->keyword;
                    $data[] = (array) $one;
                }
            }
        }

        if ($result['error']) {
            $result['error'] = sprintf('Ошибки при расчёте семантики спайвордса: %s%s', PHP_EOL . PHP_EOL, json_encode($result['error']));
            file_put_contents(sprintf(self::LOG_FILE, public_path(), $result['domain']),
                sprintf('[%s] домен - %s ,некорректные данные:%s%s', date('Y-m-d H:i:s'), $result['domain'], PHP_EOL . PHP_EOL, $result['error']), FILE_APPEND);
            goto finish;
        }

        $result['data'] = $data;
        $this->clearKeywords($result);

        finish:
        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }
    }

    /**
     * @param $result
     *
     * Чистка ключевиков от всякого говна
     */
    protected function clearKeywords(&$result)
    {
        foreach ($result['data'] as &$one) {
            $one['keyword'] = implode(' ', array_map(function ($el){return trim($el, "{}[].?!:;()-&$@*%#+-/<>|~`");},
                preg_split("/[\s]+/uisU", $one['keyword'], -1, PREG_SPLIT_NO_EMPTY)));
        }
    }
}