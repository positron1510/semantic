<?php

namespace App\Repositories;

use DB;


/**
 * Class SemanticRepository
 * @package App\Repositories
 *
 * Работа с базой калькулятора, на предмет получения семантики
 */
class SemanticRepository extends Repository
{
    /**
     *
     * Количество потоков
     */
    const COUNT_THREADS = 30;

    /**
     * @param string $domain
     * @return \stdClass
     *
     * Распределение ключевиков по потокам
     */
    public function distributionByThreads(string $domain): \stdClass
    {
        $sql = sprintf("SELECT w2.id,w2.phrase FROM sem_yadro s2 
                                INNER JOIN wordstat w2 ON s2.phrase_id = w2.id 
                                INNER JOIN domain d2 ON s2.domain_id = d2.id 
                                WHERE d2.id=(SELECT id FROM domain d1 WHERE d1.domain='%s') 
                                AND ((date(CURRENT_TIMESTAMP) - date(s2.position_update)) > 0 AND (date(CURRENT_TIMESTAMP) - date(s2.position_update)) >= 0) 
                                AND ((date(CURRENT_TIMESTAMP) - date(w2.frequency_update)) > 0 AND (date(CURRENT_TIMESTAMP) - date(w2.frequency_update)) >= 0)", $domain);
        $phrases = DB::select($sql);

        $data = new \stdClass();
        $step = 0;
        $k = 0;

        foreach ($phrases as $item) {
            if ($step >= self::COUNT_THREADS) {
                $k++;
                $step = 0;
            }

            if (!isset($data->{$step})) $data->{$step} = new \stdClass();
            $data->{$step}->{$k} = $item;

            $step++;
        }

        return $data;
    }

    /**
     * @param string $domain
     * @param \stdClass $phrases
     *
     * Обновление позиций по яндексу и гуглу в семантическом ядре
     */
    public function updatePositions(string $domain, \stdClass $phrases): void
    {
        $arr_phrases = '{';
        foreach ($phrases as $num_clust=>$cluster) {
            foreach ($cluster as $one) {
                if (isset($one->yandex_position) && isset($one->google_position)) {
                    $arr_phrases .= sprintf('{%s,%s,%s},', $one->id, $one->yandex_position, $one->google_position);
                }
            }
        }
        $arr_phrases = rtrim($arr_phrases, ',') . '}';

        DB::select('SELECT * FROM seocalc_update_positions(?,?)', [$domain, $arr_phrases]);
    }

    /**
     * @param string $domain
     * @param string $arr_phrases
     *
     * Обновление вордстата в семантическом ядре
     */
    public function updateWordstat(string $domain, string $arr_phrases)
    {
        DB::select('SELECT * FROM seocalc_update_wordstat(?,?)', [$domain, $arr_phrases]);
    }

    /**
     * @param string $domain
     * @param array $result
     *
     * Семантика spywords в базу данных
     */
    public function updateSpywords(string $domain, array $result)
    {
        $arr_phrases = '{';
        foreach ($result as $one) {
            $arr_phrases .= $one['keyword'] . ',';
        }

        $arr_phrases = rtrim($arr_phrases, ',') . '}';
        DB::select('SELECT * FROM seocalc_update_spywords(?,?)', [$domain, $arr_phrases]);
    }
}