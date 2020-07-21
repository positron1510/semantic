<?php

namespace App\Console\Commands;

use App\Repositories\SemanticRepository;
use App\Services\Semantic\BaseSemantic\Position\Aparser;
use App\Services\Semantic\BaseSemantic\Position\InvalidArgumentException;
use Illuminate\Console\Command;
use DB;


class PositionCommand extends Command
{
    /**
     *
     * Url апарсера
     */
    const API_URL = 'http://aparser.element.ru:9092/API';

    /**
     *
     * пароль к апи апарсера
     */
    const API_PASS = 'hermes1987';

    /**
     *
     * Максимальное время обработки в while
     */
    const TIME_EXECUTE = 1200;

    /**
     *
     * Задержка в секундах
     */
    const DELAY = 10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'position:collect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда съема позиций яндекс-гугл';

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
     * @var \stdClass
     *
     * Сюда складываем результат вычисления
     * по ключевикам
     */
    private static $phrases;

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
        $this->worker->addFunction(env('QUEUE_POSITION'), sprintf('\\%s::%s', __CLASS__, 'start'));
        self::$repository = new SemanticRepository();
    }

    /**
     * @throws InvalidArgumentException
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

        self::$phrases = self::$repository->distributionByThreads($domain);
        $queries = self::makeAparserQueries($domain);
        if (!$queries) goto finish;

        $aparser = new Aparser(self::API_URL, self::API_PASS);

        $options = [
            'parsers' => [['SE::Yandex::Position','yandex_top_50_seocalc']],
            'resultsFormat' => '$p1.preset',
            'resultsFileName' => 'seocalc__yandex' . time() . '.txt',
        ];
        $taskUidYandex = $aparser->addTask('300 Threads', false, 'text', $queries, $options);

        $options['parsers'] = [['SE::Google::Position','google_top_50_seocalc']];
        $options['resultsFileName'] = 'seocalc__google' . time() . '.txt';
        $taskUidGoogle = $aparser->addTask('300 Threads', false, 'text', $queries, $options);

        $timer = 0;
        $taskUid = $taskUidYandex;
        $search_engine_position = 'yandex_position';

        while (true) {
            $status = $aparser->getTaskState($taskUid);

            if ($status["status"] == 'completed') {
                $result_link = $aparser->getTaskResultsFile($taskUid);
                $res = file_get_contents($result_link);

                if ($res) {
                    self::addAparserData($res, $search_engine_position);
                    $aparser->deleteTaskResultsFile($taskUid);
                    $aparser->changeTaskStatus($taskUid, 'deleting');

                    if (isset($taskUidYandex)) {
                        unset($taskUidYandex);
                        $taskUid = $taskUidGoogle;
                        $search_engine_position = 'google_position';
                    }elseif (isset($taskUidGoogle)) {
                        break;
                    }
                }
            }

            sleep(self::DELAY);
            $timer += self::DELAY;

            if ($timer >= self::TIME_EXECUTE) {
                break;
            }
        }

        finish:
        self::$repository->updatePositions($domain, self::$phrases);

        echo sprintf('[%s] %s finished!', date('Y-m-d H:i:s'), $domain) . PHP_EOL;
    }

    /**
     * @param $domain
     * @return array
     *
     * Запросы в нужном виде для отправки в апарсер
     */
    private static function makeAparserQueries($domain): array
    {
        $data = [];
        foreach (self::$phrases as $num_clust=>$cluster) {
            foreach ($cluster as $one) {
                $data[] = sprintf('%s,www.%s %s', $domain, $domain, $one->phrase);
            }
        }

        return $data;
    }

    /**
     * @param string $res
     * @param string $search_engine_position
     *
     * Добавляем позиции поисковой системы в объкект поля $phrases
     */
    private static function addAparserData(string $res, string $search_engine_position): void
    {
        $data = array_map(function ($el) {
            $arr = explode(';', $el);
            if (isset($arr[0]) && isset($arr[2])) {
                return [$arr[0] => $arr[2]];
            }
            return [];
        }, explode(PHP_EOL, $res));

        foreach (self::$phrases as $num_clust=>$cluster) {
            foreach ($cluster as $k=>$one) {
                $filter = array_filter($data, function ($el)use($one){return isset($el[$one->phrase]);});
                if ($filter) {
                    $first = array_shift($filter);
                    if (isset($first[$one->phrase])) {
                        $one->{$search_engine_position} = (int) $first[$one->phrase];
                        self::$phrases->{$num_clust}->{$k} = $one;
                    }
                }
            }
        }
    }
}
