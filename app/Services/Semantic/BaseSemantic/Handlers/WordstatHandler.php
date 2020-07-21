<?php

namespace App\Services\Semantic\BaseSemantic\Handlers;

use App\Services\Multistreams\Curl;
use Volatile;


class WordstatHandler extends Volatile
{
    /**
     *
     * Форматный путь к апи элемента
     */
    const API_URL = 'http://robots.mysite.ru:8888/ws/?phrases=%s&region=213&accurate=%s&is_phrase=0';

    /**
     *
     * Логин API элемента
     */
    const X_API_USER = 'some_user';

    /**
     *
     * Пароль API элемента
     */
    const X_API_PASS = 'some_pass';

    /**
     *
     * Максимальное время обработки в while
     */
    const TIME_EXECUTE = 300;

    /**
     *
     * Задержка в секундах
     */
    const DELAY = 10;

    /**
     * @var int
     *
     * Номер потока
     */
    private $num_thread;

    /**
     * @var \stdClass
     *
     * Ключевики данного потока
     */
    private $phrases;

    /**
     * @var array
     *
     * Массив с результатом вычислений
     */
    public $data = [];

    /**
     * Wordstat constructor.
     * @param int $num_thread
     */
    public function __construct(int $num_thread)
    {
        $this->num_thread = $num_thread;
    }

    /**
     *
     * Запуск потока
     */
    public function run()
    {
        $provider = $this->worker->getProvider();
        $this->phrases = $provider->getPhrases()->{$this->num_thread};

        $curl = new Curl([CURLOPT_HTTPHEADER => ['X_API_USER: ' . self::X_API_USER, 'X_API_PASS: ' . self::X_API_PASS]]);
        $curl->setCallback([$this, 'callback']);

        $count_all = $this->countPhrases();
        $timer = 0;

        while (true) {
            $count = 0;
            foreach ($this->phrases as $key=>$one) {
                if (isset($this->data[$one->id])) {
                    $count++;
                    continue;
                }

                foreach ([1, 0] as $accurate) {
                    $api_url = sprintf(self::API_URL, rawurlencode($one->phrase), $accurate);
                    $postfix = 1 === $accurate ? '_accurate' : '_common';
                    $curl->execute_single_thread($api_url, $key . $postfix);
                }
            }

            if ($count >= $count_all) break;
            sleep(self::DELAY);

            $timer += self::DELAY;
            if ($timer >= self::TIME_EXECUTE) break;
        }

        $arr_string = '';
        foreach ($this->data as $one) {
            if (isset($one['id'])) {
                $arr_string .= sprintf('{%s,%s,%s},', $one['id'], $one['ws'] ?? 0, $one['postfix'] ?? 1);
            }
        }
        $arr_string = rtrim($arr_string, ',');

        # Синхронизируем получение данных
        $provider->synchronized(function($provider) use($arr_string) {
            $string = $provider->getArrString();
            $string = sprintf('%s,%s', $string, $arr_string);
            $provider->setArrString($string);
        }, $provider);
    }

    /**
     * @param string $task
     * @param string $key
     *
     * Коллбэк для однопоточного курла
     */
    public function callback(string $task, string $key)
    {
        if (is_string($task)) {
            $task = json_decode($task, false,512,JSON_UNESCAPED_UNICODE);
            if (isset($task[0]) && isset($task[0]->ws)) {
                $arr = explode('_', $key);
                $key = (int) $arr[0];
                $postfix = $arr[1] === 'accurate' ? 1 : 0;
                $id = $this->phrases->{$key}->id;
                $this->data[] = ['id' => $id, 'ws' => $task[0]->ws, 'postfix' => $postfix];
            }
        }
    }

    /**
     * @return int
     *
     * Количество ключевиков в кластере
     */
    private function countPhrases(): int
    {
        $count_all = 0;
        foreach ($this->phrases as $key=>$one) {
            $count_all++;
        }

        return $count_all;
    }
}