<?php


namespace App\Services\Semantic\ExpressSemantic\Four\Handlers;

use App\Services\Multistreams\Curl;
use Volatile;


/**
 * Class PriceClickHandler
 * @package App\Services\Semantic\ExpressSemantic\Four\Handlers
 *
 * Класс-обработчик цены клика
 */
class PriceClickHandler extends Volatile
{
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
     * @var float
     *
     * Цена за клик
     */
    private $price = 0.0;

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

        $curl = new Curl();
        $curl->setCallback([$this, 'callback']);
        $curl->execute_single_thread($this->url, $this->phrase);

        # Синхронизируем получение данных
        $provider->synchronized(function($provider) {
            $prices = $provider->getPrices();

            if (!$this->error) {
                $prices->{$this->phrase} = $this->price;
            }else {
                $prices->error = $this->error;
            }

            $provider->setPrices($prices);
        }, $provider);
    }

    /**
     * @param $task
     * @param $id
     *
     * Коллбек отрабатывающийся курлом
     */
    public function callback($task, $id)
    {
        if (!is_string($task)) {
            $this->error = sprintf('Ключевое слово "%s", адрес %s, результат некорректен, пришла не строка', $this->phrase, $this->url);
            goto finish;
        }

        $task = explode("\r\n", iconv('windows-1251', 'utf-8', $task));
        $task = array_filter($task, function ($el){$arr = explode("\t", $el); return mb_strtolower($arr[0]) === 'яндекс';});

        if ($task) {
            $task = explode("\t", array_shift($task));
            $price = (float) str_ireplace(',', '.', array_pop($task));
            $this->price = !$price ? 0.03 : $price;
        }else {
            $this->error = sprintf('Ключевое слово "%s", адрес %s, результат некорректен', $this->phrase, $this->url);
        }

        finish:
        unset($id);
    }
}