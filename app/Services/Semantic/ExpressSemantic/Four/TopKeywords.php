<?php

namespace App\Services\Semantic\ExpressSemantic\Four;


class TopKeywords extends DecorateSemantic
{
    /**
     *
     * Файл с промежуточным результатом
     */
    const RESULT_FILE = 'top_keywords';

    /**
     *
     * Кол-во топ 10 ключевиков
     */
    const TOP10_COUNT = 50;

    /**
     *
     * Кол-во топ 50 ключевиков
     */
    const TOP50_COUNT = 150;

    /**
     *
     * Нормировочный коэффицент
     */
    const TOP_COEFFICIENT = 0.17;

    /**
     * @param array $result
     *
     * Отрабатываем класс
     */
    public function execute(array &$result)
    {
        if ($result['error']) goto finish;

        $top10Keywords = array_filter($result['data'], function ($el){return $el['pos'] >= 1 && $el['pos'] < 11;});
        usort($top10Keywords, function ($a, $b){return $a['volume'] <= $b['volume'];});
        $top10Keywords = array_slice($top10Keywords, 0, self::TOP10_COUNT);

        $top50Keywords = array_filter($result['data'], function ($el){return $el['pos'] >= 11 && $el['pos'] < 51;});
        usort($top50Keywords, function ($a, $b){return $a['volume'] <= $b['volume'];});
        $top50Keywords = array_slice($top50Keywords, 0, self::TOP50_COUNT);

        unset($result['data']);

        $result['top10Keywords'] = $top10Keywords;
        $result['top50Keywords'] = $top50Keywords;
        $top10Keywords = [];
        $top50Keywords = [];

        foreach ($result['top10Keywords'] as $one) {
            $keyword = $one['keyword'];
            unset($one['keyword']);
            $top10Keywords[$keyword] = $one;
        }

        foreach ($result['top50Keywords'] as $one) {
            $keyword = $one['keyword'];
            unset($one['keyword']);
            $top50Keywords[$keyword] = $one;
        }

        $result['top10Keywords'] = $top10Keywords;
        $result['top50Keywords'] = $top50Keywords;

        $count = ceil(self::TOP_COEFFICIENT * count($result['top50Keywords']));
        $result['top10Keywords'] = array_slice($result['top10Keywords'], 0, $count);
        $result['keywords'] = $result['top10Keywords'] + $result['top50Keywords'];
        $result['keywords'] = array_map(function ($el){$el['accurate_ws'] = $el['volume']; $el['common_ws'] = $el['volume'] * 10; return $el;}, $result['keywords']);

        uasort($result['keywords'], function ($a, $b){return $a['accurate_ws'] <= $b['accurate_ws'];});

        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }

        unset($result['top10Keywords']);
        unset($result['top50Keywords']);

        finish:
        $this->semantic->execute($result);
    }
}