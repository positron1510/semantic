<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\SemanticRepository;
use App\Services\Semantic\ExpressSemantic\Four\SpywordsPotential;
use App\Services\Semantic\ExpressSemantic\Four\SpywordsURLS;
use App\Services\Semantic\ExpressSemantic\Four\StopWords;
use App\Services\Semantic\ExpressSemantic\Four\EmptySemantic;
use App\Services\Semantic\ExpressSemantic\Four\SpywordsSemanticCalculate;


/**
 * Class SpywordsCommand
 * @package App\Console\Commands
 *
 * Основная семантика собранная с помощью spywords
 */
class SpywordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spywords:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Основная семантика собираемая спайвордсом';

    /**
     * @var \GearmanWorker
     *
     * Экземпляр класса gearman-воркера
     */
    private $worker;

    /**
     * @var SemanticRepository
     *
     * Экземпляр доступа к репозиторию модели
     */
    private static $repository;

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
        $this->worker->addFunction(env('QUEUE_SPYWORDS'), sprintf('\\%s::%s', __CLASS__, 'start'));
        self::$repository = new SemanticRepository();
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

        $result = [
            'domain' => $domain,
            'error' => '',
            'debug' => false
        ];

        # декоратор
        $semantic = new SpywordsPotential(
            new SpywordsURLS(
                new SpywordsSemanticCalculate(
                    new StopWords(
                        new EmptySemantic()
                    ))));

        $semantic->execute($result);
        self::$repository->updateSpywords($domain, $result);

        echo sprintf('[%s] %s finished!', date('Y-m-d H:i:s'), $domain) . PHP_EOL;
    }
}
