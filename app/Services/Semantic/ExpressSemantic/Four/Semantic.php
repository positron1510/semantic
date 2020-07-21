<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Semantic\ExpressSemantic\Four\Pthreads\DataProvider;
use App\Services\Semantic\ExpressSemantic\Four\Pthreads\WorkerHelper;
use Pool;


abstract class Semantic
{
    use Logger;

    /**
     *
     * Путь к файлу с результатом
     */
    const RESULT_PATH = 'semantic/four/%s/%s';

    /**
     *
     * Имя файла с результатом
     */
    const RESULT_FILE = 'result';

    /**
     *
     * Кол-во одной порции урлов в многопоточную обработку
     * (как вариант переопределяется в дочернем классе)
     */
    const CHUNK_SIZE = 50;

    /**
     *
     * Класс обработчик потока переопределяется
     * в дочернем классе
     */
    const HANDLER_CLASS = '';

    /**
     * @var string
     *
     * Полный путь к файлу с результатом
     * (определяется в дочерних классах)
     */
    public static $result_file_path = '';

    /**
     * @param array $result
     * @return mixed
     *
     * Метод который нужно переопределить в дочерних классах
     */
    abstract function execute(array &$result);

    /**
     * @param DataProvider $provider
     * @param array $api_urls
     *
     * Разбиваем урлы на чанки и в while запускаем каждый чанк
     */
    public function runChunks(DataProvider &$provider, array $api_urls): void
    {
        $offset = 0;
        $chunk = array_slice($api_urls, $offset, static::CHUNK_SIZE, true);

        while ($chunk) {
            $this->runThreads($chunk, $provider);
            $offset += static::CHUNK_SIZE;
            $chunk = array_slice($api_urls, $offset, static::CHUNK_SIZE, true);
            sleep(1);
        }
    }

    /**
     * @param array $data
     * @param DataProvider $provider
     *
     * Отработка чанка с апи-урлами
     */
    public function runThreads(array $data, DataProvider &$provider): void
    {
        $stack = new \SplStack();
        $handlerClass = static::HANDLER_CLASS;

        $threads = 0;
        foreach ($data as $key=>$url) {
            $stack->push(new $handlerClass($url, $key));
            $threads++;
        }

        $pool = new Pool($threads, WorkerHelper::class, [$provider]);

        $stack->rewind();
        while ($stack->valid()) {
            $pool->submit($stack->current());
            $stack->next();
        }

        $pool->shutdown();
    }
}