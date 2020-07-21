<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Lemmatizer\Lemmatizer;
use DB;


/**
 * Class StopWords
 * @package App\Semantic\Four
 *
 * Отсеивает по стоп-словам в ключевиках и урлах, а также с переставленными словами
 */
class StopWords extends DecorateSemantic
{
    /**
     *
     * Файл с промежуточным результатом
     */
    const RESULT_FILE = 'stop_words';

    /**
     * @param array $result
     *
     * Запуск процесса обработки
     */
    public function execute(array &$result)
    {
        if ($result['error']) goto finish;

        $keywords = implode(' 11abcd22 ', array_map(function ($el){return $el['keyword'];}, $result['data']));
        $arr_lemm_keywords = explode(' 11abcd22 ', Lemmatizer::lemmatizeString($keywords, false));

        foreach ($result['data'] as $k=>$one) {
            $result['data'][$k]['lemm_keyword'] = $arr_lemm_keywords[$k];
        }

        $words = DB::select("SELECT * FROM stop_dictionary");
        $stop_words = [];

        foreach ($words as $one) {
            $one->word = trim($one->word);
            if ($one->word) {
                $stop_words[] = $one->word;
            }
        }

        $words = DB::select('SELECT word FROM urls_dictionary');
        $stop_urls_words = [];

        foreach ($words as $one) {
            $one->word = trim($one->word);
            if ($one->word) {
                $stop_urls_words[] = $one->word;
            }
        }
        unset($words);

        $this->rearrangementOfWords($result);
        $this->cleanByStopWords($result, $stop_words, $stop_urls_words);

        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }

        finish:
        $this->semantic->execute($result);
    }

    /**
     * @param array $result
     *
     * Фильтрация по перестановке слов
     */
    private function rearrangementOfWords(array &$result)
    {
        $keywords = [];
        foreach ($result['data'] as $num=>$one) {
            $arr_keyword = explode(' ', $one['keyword']);
            $flag = false;
            foreach ($keywords as $k=>$keyword) {
                if (count($keyword) === count($arr_keyword) && !array_diff($keyword, $arr_keyword) && !array_diff($arr_keyword, $keyword)) {
                    unset($result['data'][$num]);
                    $flag = true;
                    break(1);
                }
            }
            if (!$flag) {
                $keywords[] = $arr_keyword;
            }
        }
    }

    /**
     * @param array $result
     * @param array $stop_words
     * @param array $stop_urls_words
     *
     * Чистка по стоп-словам
     */
    private function cleanByStopWords(array &$result, array $stop_words, array $stop_urls_words)
    {
        foreach ($result['data'] as $num=>$one) {
            $arr_words_keyword = array_map(function ($el){return explode('|', $el)[0];}, explode(' ', $one['lemm_keyword']));

            if (1 === count($arr_words_keyword)) {
                unset($result['data'][$num]);
                continue;
            }

            foreach ($stop_words as $phr) {
                $arr_phr = explode(' ', $phr);
                if (count(array_intersect($arr_words_keyword, $arr_phr)) === count($arr_phr)) {
                    unset($result['data'][$num]);
                    break(1);
                }
            }

            foreach ($stop_urls_words as $word) {
                if (mb_strpos('_'.$one['url'], $word)) {
                    unset($result['data'][$num]);
                    break(1);
                }
            }
        }
    }

    /**
     * @return array
     *
     * Получение массива из стоп-слов для урлов
     */
    public static function getStopUrlsWords()
    {
        $words = DB::select('SELECT word FROM urls_dictionary');
        $stop_urls_words = [];

        foreach ($words as $one) {
            $one->word = trim($one->word);
            if ($one->word) {
                $stop_urls_words[] = $one->word;
            }
        }

        return $stop_urls_words;
    }
}