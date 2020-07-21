<?php

namespace App\Services\Semantic\ExpressSemantic\Four\Handlers;

use App\Services\Multistreams\Curl;
use Volatile;


class CostCalculateHandler extends Volatile
{
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
     * @var string
     *
     * Текущий url по которому надо получить результат
     */
    private $url;

    /**
     * @var string
     *
     * Ключевик привязаный к урлу
     */
    private $phrase;

    /**
     * @var array
     *
     * Массив с результатом потока
     */
    private $data = [];

    /**
     * @var string
     *
     * Индикатор ошибки
     */
    private $error = '';

    /**
     * PriceClickHandler constructor.
     * @param string $url
     * @param string $phrase
     */
    public function __construct(string $url, string $phrase)
    {
        $this->url = $url;
        $this->phrase = $phrase;
    }

    /**
     *
     * Запуск потока
     */
    public function run()
    {
        $provider = $this->worker->getProvider();

        $curl = new Curl([
            CURLOPT_HTTPHEADER => [
                'X_API_USER: ' . self::X_API_USER,
                'X_API_PASS: ' . self::X_API_PASS
            ]]);

        $curl->setCallback([$this, 'callback']);
        $curl->execute_single_thread($this->url, $this->phrase);

        # Синхронизируем получение данных
        $provider->synchronized(function($provider) {
            $costs = $provider->getCosts();

            if (!$this->error) {
                $costs->{$this->phrase} = new \stdClass();
                $costs->{$this->phrase}->min = $this->data['min'];
                $costs->{$this->phrase}->max = $this->data['max'];
                $costs->{$this->phrase}->price = $this->data['price'];
                $costs->{$this->phrase}->term_promotion = $this->data['term_promotion'];
                $costs->{$this->phrase}->complexity_common = $this->data['complexity_common'];
                $costs->{$this->phrase}->complexity = $this->data['complexity'];
            }else {
                $costs->error = $this->error;
            }

            $provider->setCosts($costs);
        }, $provider);
    }

    /**
     * @param $task
     * @param $keyword
     *
     *  Коллбэк для курла
     */
    public function callback($task, $keyword)
    {
        if (!is_string($task)) {
            $this->error = sprintf('Ключевое слово "%s", адрес %s, результат некорректен, пришла не строка', $this->phrase, $this->url);
            goto finish;
        }

        $task = json_decode($task);

        if (!is_object($task)) {
            $this->error = sprintf('Ключевое слово "%s", адрес %s, результат некорректен, не является сторокой в формате json', $this->phrase, $this->url);
            goto finish;
        }

        $this->data['min'] = $task->min;
        $this->data['max'] = $task->max;
        $this->data['price'] = $task->price;
        $this->data['term_promotion'] = $task->min > 0 ? 'от ' . $task->min . ' мес.' : '-';
        $this->data['complexity_common'] = $this->getComplexity($task->complexity_common);
        $this->data['complexity'] = $this->getComplexity($task->complexity);

        unset($keyword);
        finish:
    }

    /**
     * @param $complexity
     * @return float|int
     *
     * Расчет complexity
     */
    private function getComplexity($complexity)
    {
        $value = log($complexity, 2.3);

        if ($value >= 10) {
            $count = 10;
        } else if ($value <= 1) {
            $count = 1;
        } else {
            $count = round($value, 0);
        }

        return $count;
    }
}