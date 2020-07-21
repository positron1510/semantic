<?php

namespace App\Services\Semantic\ExpressSemantic\Four\Handlers;

use App\Services\Multistreams\Curl;
use Volatile;


class SpywordsSemanticCalculateHandler extends Volatile
{
    /**
     * @var string
     *
     * Текущий url по которому надо получить результат
     */
    private $api_url;

    /**
     * @var string
     *
     * Ключевик привязаный к урлу
     */
    private $url;

    /**
     * @var string
     *
     * Индикатор ошибки
     */
    private $error = '';

    /**
     * @var array
     *
     * Данные из коллбэка
     */
    private $data = [];

    /**
     * SpywordsSemanticCalculateHandler constructor.
     * @param string $api_url
     * @param string $url
     */
    public function __construct(string $api_url, string $url)
    {
        $this->api_url = $api_url;
        $this->url = $url;
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
        $curl->execute_single_thread($this->api_url, $this->url);

        # Синхронизируем получение данных
        $provider->synchronized(function($provider) {
            $semantic = $provider->getSpywordsSemantic();

            if (!$this->error) {
                $semantic->{$this->url} = $this->data;
            }else {
                $semantic->error = $this->error;
            }

            $provider->setSpywordsSemantic($semantic);
        }, $provider);
    }

    /**
     * @param $task
     * @param $id
     *
     * Коллбек отрабатывающийся мультикурлом
     */
    public function callback($task, $id)
    {
        if (!is_string($task)) {
            $this->error = sprintf('Ключ "%s", адрес %s, результат некорректен, пришла не строка', $this->url, $this->api_url);
            goto finish;
        }

        $task = iconv('windows-1251', 'utf-8', $task);

        if (mb_strpos('_' . $task, 'ERROR')) {
            $this->error = 'Spywords error';
            goto finish;
        }

        $this->data = array_map(function ($el){$arr = explode("\t", $el); return ['keyword' => isset($arr[0]) ? $arr[0] : '',
            'volume' => isset($arr[2]) ? (int)str_ireplace(' ', '', $arr[2]) : 0,
            'pos' => isset($arr[3]) ? (int)$arr[3] : 0, 'url' => isset($arr[6]) ? $arr[6] : ''];}, explode("\r\n", $task));

        $data = new \stdClass();

        foreach ($this->data as $key=>$item) {
            $f = false;
            foreach ($item as $one) {
                if (!$one) {
                    $f = true;
                    break 1;
                }
            }
            if ($f) {
                unset($this->data[$key]);
                continue;
            }

            $data->{$key} = new \stdClass();
            $data->{$key}->keyword = $item->keyword;
            $data->{$key}->volume = $item->volume;
            $data->{$key}->pos = $item->pos;
            $data->{$key}->url = $item->url;
        }

        $this->data = $data;

        finish:
        unset($id);
    }
}