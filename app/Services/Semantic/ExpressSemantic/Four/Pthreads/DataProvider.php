<?php

namespace App\Services\Semantic\ExpressSemantic\Four\Pthreads;

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
     * Объект с ценами за клик
     */
    private $prices;

    /**
     * @var \stdClass
     *
     * Объект со стоимостями
     */
    private $costs;

    /**
     * @var \stdClass
     *
     * Семантика спайвордса
     */
    private $spywords_semantic;

    /**
     * @return \stdClass
     */
    public function getPrices(): \stdClass
    {
        if (is_null($this->prices)) {
            return new \stdClass();
        }

        return $this->prices;
    }

    /**
     * @param \stdClass $prices
     */
    public function setPrices(\stdClass $prices)
    {
        $this->prices = $prices;
    }

    /**
     * @return \stdClass
     */
    public function getCosts(): \stdClass
    {
        if (is_null($this->costs)) {
            return new \stdClass();
        }

        return $this->costs;
    }

    /**
     * @param \stdClass $costs
     */
    public function setCosts(\stdClass $costs)
    {
        $this->costs = $costs;
    }

    /**
     * @return \stdClass
     */
    public function getSpywordsSemantic(): \stdClass
    {
        if (is_null($this->spywords_semantic)) {
            return new \stdClass();
        }

        return $this->spywords_semantic;
    }

    /**
     * @param \stdClass $spywords_semantic
     */
    public function setSpywordsSemantic(\stdClass $spywords_semantic)
    {
        $this->spywords_semantic = $spywords_semantic;
    }
}