<?php

namespace App\Services\Multistreams;

/**
 * Class Curl
 * @package App\Multistreams
 *
 * Класс для работы с курлом в однопоточном и многопоточном режиме
 */
class Curl
{
    private $options = [];
    private $callback;

    public $response;
    public $id;

    /**
     * Curl constructor.
     * @param array $options
     */
    public function __construct(array $options=[])
    {
        $this->options = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 300,
        ];
        $this->options += $options;

        $this->setCallback([$this, 'defaultCallback']);
    }

    /**
     * @param string $api_url
     * @param string $id
     * @param string $page
     *
     * Запуск однопоточного режима
     */
    public function execute_single_thread(string $api_url, string $id='', string $page='')
    {
        if ($page) $api_url .= $page;

        $ch = curl_init();
        $this->options[CURLOPT_URL] = $api_url;
        curl_setopt_array($ch, $this->options);

        $response = curl_exec($ch);
        call_user_func($this->callback, $response, $id);
    }

    /**
     * @param array $urls
     * @param string $page
     * @return $this
     *
     * Запуск многопоточного режима
     */
    public function execute(array $urls, string $page='')
    {
        $cmh = curl_multi_init();
        $tasks = [];

        foreach ($urls as $id=>$api_url) {
            if ($page) {
                $api_url .= $page;
            }

            $ch = curl_init();
            $this->options[CURLOPT_URL] = $api_url;

            curl_setopt_array($ch, $this->options);
            $tasks[$id] = $ch;
            curl_multi_add_handle($cmh, $ch);
        }

        # количество активных потоков
        $active = null;

        # запускаем выполнение потоков
        do {
            $mrc = curl_multi_exec($cmh, $active);
            sleep(1);
        }
        while ($mrc == CURLM_CALL_MULTI_PERFORM);

        # выполняем, пока есть активные потоки
        while ($active && ($mrc == CURLM_OK))  {
            $mrc = curl_multi_exec($cmh, $active);
            # если какой-либо поток готов к действиям
            if (curl_multi_select($cmh) != -1) {
                # ждем, пока что-нибудь изменится
                do {
                    $mrc = curl_multi_exec($cmh, $active);
                    # получаем информацию о потоке
                    $info = curl_multi_info_read($cmh);
                    # если поток завершился
                    if ($info['msg'] == CURLMSG_DONE) {
                        $ch = $info['handle'];
                        # ищем урл страницы по дескриптору потока в массиве заданий
                        $id = array_search($ch, $tasks);
                        unset($tasks[$id]);

                        $response = curl_multi_getcontent($ch);
                        call_user_func($this->callback, $response, $id);
                        unset($response);

                        curl_multi_remove_handle($cmh, $ch);
                        curl_close($ch);
                    }
                }
                while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        curl_multi_close($cmh);

        return $this;
    }

    /**
     * @param array $callback
     *
     * Установка коллбэка
     */
    public function setCallback(array $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param string $response
     * @param string $id
     *
     * Коллбэк по умолчанию просто заносит значения в поля
     */
    private function defaultCallback(string $response, string $id)
    {
        $this->response = $response;
        $this->id = $id;
    }
}