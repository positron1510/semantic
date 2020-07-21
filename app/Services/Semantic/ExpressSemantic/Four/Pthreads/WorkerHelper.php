<?php

namespace App\Services\Semantic\ExpressSemantic\Four\Pthreads;

use Worker;
use Illuminate\Contracts\Console\Kernel;


/**
 * WorkerHelper тут используется, чтобы расшарить провайдер между экземплярами Work.
 */
class WorkerHelper extends  Worker
{
    /**
     * @var DataProvider
     */
    private $provider;

    /**
     * @param DataProvider $provider
     */
    public function __construct(DataProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Вызывается при отправке в Pool.
     */
    public function run()
    {
        require __DIR__ . '/../../../../../../vendor/autoload.php';
        $app = require __DIR__ . '/../../../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    /**
     * @param int $options
     * @return bool
     *
     * Отнаследованый метод
     */
    public function start($options = PTHREADS_INHERIT_ALL)
    {
        return parent::start(PTHREADS_INHERIT_INI);
    }

    /**
     * Возвращает провайдера
     *
     * @return DataProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }
}