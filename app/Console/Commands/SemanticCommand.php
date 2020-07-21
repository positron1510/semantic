<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Semantic\ExpressSemantic\Four\SpywordsPotential;
use App\Services\Semantic\ExpressSemantic\Four\SpywordsURLS;
use App\Services\Semantic\ExpressSemantic\Four\StopWords;
use App\Services\Semantic\ExpressSemantic\Four\TopKeywords;
use App\Services\Semantic\ExpressSemantic\Four\PriceClick;
use App\Services\Semantic\ExpressSemantic\Four\CostCalculate;
use App\Services\Semantic\ExpressSemantic\Four\SpywordsSemanticCalculate;
use Illuminate\Support\Facades\Mail;


/**
 * Class SemanticCommand
 * @package App\Console\Commands
 *
 * Команда вытаскивающая задачи на получение семантики
 * из очереди
 */
class SemanticCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semantic:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Команда на получение семантики из очереди';

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
        $this->worker->addFunction(env('QUEUE_SEMANTIC'), sprintf('\\%s::%s', __CLASS__, 'start'));
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
     *
     * Обработка домена из очереди
     */
    public static function start($job)
    {
        $workload = $job->workload();
        $response = json_decode($workload, true);

        echo sprintf('[%s] %s started...', date('Y-m-d H:i:s'), $response['domain']) . PHP_EOL;

        $result = [
            'domain' => $response['domain'],
            'error' => '',
            'debug' => false
        ];

        # декоратор
        $semantic = new SpywordsPotential(
            new SpywordsURLS(
                new SpywordsSemanticCalculate(
                    new StopWords(
                        new TopKeywords(
                            new PriceClick(
                                new CostCalculate()
                            ))))));

        $semantic->execute($result);

        if (($result['error'] && $result['error'] !== 'Spywords error') || (is_array($result['error']) && !in_array('Spywords error', $result['error']))) {
            Mail::send('emails.feedback', ['feedback' => $result['error']], function ($m) {
                $m->from('m.yanko@optimism.ru', 'Сервис экспресс-семантики');
                $m->to(env('ADMIN_EMAIL'), 'Maxim Yanko')->subject('Ошибка сбора семантики');
            });
            Mail::send('emails.feedback', ['feedback' => $result['error']], function ($m) {
                $m->from('m.yanko@optimism.ru', 'Сервис экспресс-семантики');
                $m->to('ap@optimism.ru', 'Maxim Yanko')->subject('Ошибка сбора семантики');
            });

            if (is_array($result['error'])) {
                $result['error'] = json_encode($result['error']);
            }

            echo $result['error'] . PHP_EOL;
        }

        echo sprintf('[%s] %s finished!', date('Y-m-d H:i:s'), $response['domain']) . PHP_EOL;
    }

    /**
     *
     * Дебаг домена
     */
    public static function debug()
    {
        $response['domain'] = 'optimism.ru';
        echo sprintf('[%s] %s started...', date('Y-m-d H:i:s'), $response['domain']) . PHP_EOL;

        $result = [
            'domain' => $response['domain'],
            'error' => '',
            'debug' => false
        ];

        # декоратор
        $semantic = new SpywordsPotential(
            new SpywordsURLS(
                new SpywordsSemanticCalculate(
                    new StopWords(
                        new TopKeywords(
                            new PriceClick(
                                new CostCalculate()
                            ))))));

        $semantic->execute($result);

        if (is_array($result['error'])) {
            $result['error'] = json_encode($result['error']);
        }

        if ($result['error']) {
            echo $result['error'] . PHP_EOL;
        }

        echo sprintf('[%s] %s finished!', date('Y-m-d H:i:s'), $response['domain']) . PHP_EOL;
    }
}
