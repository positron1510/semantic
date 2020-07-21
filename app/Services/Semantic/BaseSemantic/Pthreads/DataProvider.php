<?php

namespace App\Services\Semantic\BaseSemantic\Pthreads;

use Threaded;


/**
 * Class DataProvider
 * @package App\Pthreads
 *
 * Общие контекстные поля при исполнении потоков
 */
class DataProvider extends Threaded
{
    /**
     * @var \stdClass
     *
     * Ключевики группированные по кластерам
     */
    private $phrases;

    /**
     * @var string
     *
     * Строка с postgres-массивом
     */
    private $arr_string = '';

    /**
     * @return \stdClass
     */
    public function getPhrases(): \stdClass {
        return $this->phrases;
    }

    /**
     * @param \stdClass $phrases
     */
    public function setPhrases(\stdClass $phrases) {
        $this->phrases = $phrases;
    }

    /**
     * @return string
     */
    public function getArrString(): string
    {
        return $this->arr_string;
    }

    /**
     * @param string $arr_string
     */
    public function setArrString(string $arr_string)
    {
        $this->arr_string = $arr_string;
    }
}