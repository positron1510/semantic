<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Multistreams\Curl;


class Wordstat extends  DecorateSemantic
{
    /**
     *
     * Файл с промежуточным результатом
     */
    const RESULT_FILE = 'wordstat';

    /**
     *
     * Форматный путь к апи элемента
     */
    const API_URL = 'http://robots.element.ru:8888/ws/?phrases=%s&region=1&accurate=%s&is_phrase=0';

    /**
     *
     * Нормировочный коэффицент
     */
    const TOP_COEFFICIENT = 0.17;

    /**
     *
     * Таймаут выполнения скрипта
     */
    const TIMEOUT = 300;

    /**
     * @var array
     *
     * Массив с урлами
     */
    public $urls = [];

    /**
     * @var array
     *
     * Массив с урлами в обработке
     */
    public $work_urls = [];

    /**
     * @var bool
     *
     * Флаг лага
     */
    public $lag = false;

    /**
     * @var array
     *
     * Массив с результатом
     */
    private $result = [];

    /**
     * @param array $result
     *
     * Отрабатываем класс
     */
    public function execute(array &$result)
    {
        if ($result['error']) goto finish;

        $offset = 0;
        $length = 100;

        foreach (['top10Keywords', 'top50Keywords'] as $item) {
            foreach ($result[$item] as $keyword=>$data) {
                $this->urls[$keyword . '_accurate'] = sprintf(self::API_URL, rawurlencode($keyword), 1);
                $this->urls[$keyword . '_common'] = sprintf(self::API_URL, rawurlencode($keyword), 0);
            }
        }

        $this->result['domain'] = $result['domain'];
        $this->result['top10Keywords'] = $result['top10Keywords'];
        unset($result['top10Keywords']);
        $this->result['top50Keywords'] = $result['top50Keywords'];
        unset($result['top50Keywords']);

        $count_all_urls = count($this->urls);
        $this->work_urls = array_slice($this->urls, $offset, $length);

        $curl = new Curl([CURLOPT_HTTPHEADER => ['X_API_USER: ' . self::X_API_USER,'X_API_PASS: ' . self::X_API_PASS]]);
        $curl->setCallback([$this, 'callback']);

        $step = 1;
        $counter = 0;

        while ($count_all_urls) {
            $count_work_urls = count($this->work_urls);

            while ($this->work_urls) {
                $this->lag = false;
                $curl->execute($this->work_urls);

                if ($this->work_urls || $this->lag) {
                    sleep(10);
                    $counter += 10;
                }

                if ($counter > self::TIMEOUT) {
                    break(2);
                }
            }

            $offset += $length;
            $count_all_urls -= $count_work_urls;
            $this->work_urls = array_slice($this->urls, $offset, $length);

            $step++;
        }

        if ($counter > self::TIMEOUT) {
            $result['error'] = sprintf('Домен %s. Не снялся вордстат. Таймаут > %s сек.', $this->result['domain'], self::TIMEOUT);
            goto finish;
        }

        $result['top10Keywords'] = $this->filterKeywords($this->result['top10Keywords']);
        unset($this->result['top10Keywords']);
        $result['top50Keywords'] = $this->filterKeywords($this->result['top50Keywords']);
        unset($this->result['top50Keywords']);

        $result['top10Keywords'] = array_filter($result['top10Keywords'], function ($el){return $el['accurate_ws'] > 0;});
        $result['top50Keywords'] = array_filter($result['top50Keywords'], function ($el){return $el['accurate_ws'] > 0;});

        $count = ceil(self::TOP_COEFFICIENT * count($result['top50Keywords']));

        $result['top10Keywords'] = array_slice($result['top10Keywords'], 0, $count);
        $result['keywords'] = $result['top10Keywords'] + $result['top50Keywords'];

        uasort($result['keywords'], function ($a, $b){return $a['accurate_ws'] <= $b['accurate_ws'];});

        unset($result['top10Keywords']);
        unset($result['top50Keywords']);

        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }

        finish:
        $this->semantic->execute($result);
    }

    /**
     * @param $task
     * @param $type
     * @return bool
     *
     * Просто коллбэк
     */
    public function callback($task, $type)
    {
        $arr = explode('_', $type);
        $keyword = $arr[0];
        $accurate = $arr[1];

        if (isset($this->result['top10Keywords'][$keyword])) {
            if ('accurate' === $accurate) {
                $this->result['top10Keywords'][$keyword]['accurate_ws'] = -1;
            }else {
                $this->result['top10Keywords'][$keyword]['common_ws'] = -1;
            }
            $this->result['top10Keywords'][$keyword]['price'] = 0;
        }

        if (isset($this->result['top50Keywords'][$keyword])) {
            if ('accurate' === $accurate) {
                $this->result['top50Keywords'][$keyword]['accurate_ws'] = -1;
            }else {
                $this->result['top50Keywords'][$keyword]['common_ws'] = -1;
            }
            $this->result['top50Keywords'][$keyword]['price'] = 0;
        }

        if (!is_string($task)) {
            $this->lag = true;
            return false;
        }

        $task = json_decode($task);

        if (!isset($task[0]) || !isset($task[0]->ws)) {
            $this->lag = true;
            return false;
        }

        $price = 0;
        if (isset($task[0]->min) && isset($task[0]->max) && isset($task[0]->premium_min) && isset($task[0]->premium_max)) {
            $price = round(($task[0]->min + $task[0]->max + $task[0]->premium_min + $task[0]->premium_max) / 4, 2);
        }

        if (isset($this->result['top10Keywords'][$keyword])) {
            if ('accurate' === $accurate) {
                $this->result['top10Keywords'][$keyword]['accurate_ws'] = $task[0]->ws;
            }else {
                $this->result['top10Keywords'][$keyword]['common_ws'] = $task[0]->ws;
            }
            $this->result['top10Keywords'][$keyword]['price'] = $price;
        }

        if (isset($this->result['top50Keywords'][$keyword])) {
            if ('accurate' === $accurate) {
                $this->result['top50Keywords'][$keyword]['accurate_ws'] = $task[0]->ws;
            }else {
                $this->result['top50Keywords'][$keyword]['common_ws'] = $task[0]->ws;
            }
            $this->result['top50Keywords'][$keyword]['price'] = $price;
        }

        unset($this->work_urls[$type]);
    }

    /**
     * @param array $keywords
     * @return array
     *
     * Фильтрация фраз с "неправильным" вордстатом
     */
    private function filterKeywords(array $keywords)
    {
        foreach ($keywords as $keyword=>$one) {
            if ($one['accurate_ws'] <= 0) {
                unset($keywords[$keyword]);
                continue;
            }
            $rel = round($one['common_ws'] / $one['accurate_ws'], 1);
            if (($one['accurate_ws'] < 10 && $rel > 100) || ($one['accurate_ws'] < 30 && $rel > 130) || ($one['accurate_ws'] < 100 && $rel > 200) || $rel > 500) {
                unset($keywords[$keyword]);
                continue;
            }
        }

        return $keywords;
    }
}