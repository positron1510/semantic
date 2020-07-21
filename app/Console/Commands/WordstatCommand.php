<?php

namespace App\Console\Commands;

use App\Services\Semantic\BaseSemantic\Handlers\WordstatHandler;
use App\Services\Semantic\BaseSemantic\Pthreads\DataProvider;
use App\Services\Semantic\BaseSemantic\Pthreads\WorkerHelper;
use Illuminate\Console\Command;
use App\Repositories\SemanticRepository;
use Pool;
use DB;


class WordstatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wordstat:collect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Многопоточный сбор вордстата';

    /**
     * @var SemanticRepository
     *
     * Экземпляр доступа к репозиторию модели
     */
    private static $repository;

    /**
     * @var \GearmanWorker
     *
     * Экземпляр класса gearman-воркера
     */
    private $worker;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->worker = new \GearmanWorker();
        $this->worker->addServer();
        $this->worker->addFunction(env('QUEUE_WORDSTAT'), sprintf('\\%s::%s', __CLASS__, 'start'));
    }

    /**
     * @return SemanticRepository
     *
     * Получение экземпляра репозитория
     */
    public static function getRepository()
    {
        if (is_null(self::$repository)) {
            return new SemanticRepository();
        }

        return self::$repository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        while (1) {
            $this->worker->work();
            if ($this->worker->returnCode() != GEARMAN_SUCCESS) break;
            sleep(2);
        }
    }

    /**
     * @param $job
     *
     * Запуск домена из очереди
     */
    public static function start($job)
    {
        $workload = $job->workload();
        $response = json_decode($workload, true);
        $domain = $response['domain'];

        echo sprintf('[%s] %s started...', date('Y-m-d H:i:s'), $domain) . PHP_EOL;

        $phrases = self::getRepository()->distributionByThreads($domain);

        $provider = new DataProvider();
        $provider->setPhrases($phrases);

        if (!isset($phrases->{0})) goto finish;

        $stack = new \SplStack();

        $num_thread = 0;
        foreach ($phrases as $num_thread=>$phrase) {
            $stack->push(new WordstatHandler($num_thread));
        }

        $threads = $num_thread + 1;
        $pool = new Pool($threads, WorkerHelper::class, [$provider]);

        $stack->rewind();
        while ($stack->valid()) {
            $pool->submit($stack->current());
            $stack->next();
        }
        $pool->shutdown();

        finish:
        $arr_string = '{' . ltrim($provider->getArrString(), ',') . '}';
        self::getRepository()->updateWordstat($domain, $arr_string);

        echo sprintf('[%s] %s finished!', date('Y-m-d H:i:s'), $domain) . PHP_EOL;
    }
}
