<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Multistreams\Curl;


/**
 * Class SpywordsPotential
 * @package App\Semantic\Four
 *
 * Вычисление потенциала домена
 */
class SpywordsPotential extends DecorateSemantic
{
    /**
     *
     * API url
     */
    const API_URL = 'https://api.spywords.ru/?method=DomainOverview&site=%s&login=%s&token=cc71bf1355d6880089cb84616aa6d75c';

    /**
     *
     * Логирующий файл
     */
    const LOG_FILE = '%s/semantic/four/%s/spywords_potential.txt';

    /**
     * @var array
     *
     * Массив с данными
     */
    private $data = [];

    /**
     * @param array $result
     *
     * Запуск класса
     */
    public function execute(array &$result)
    {
        $this->makeResultDir($result['domain']);
        $this->domain = $result['domain'];
        $api_url = sprintf(self::API_URL, $result['domain'], self::LOGIN);

        $curl = new Curl();
        $curl->setCallback([$this, 'callback']);
        $curl->execute_single_thread($api_url);

        if (!$this->data) {
            $result['error'] = sprintf('Домен %s. Нулевой потенциал домена. %s. Данные из spywords: %s',
                $result['domain'], $api_url, sprintf(self::LOG_FILE, env('APP_URL'), $result['domain']));
            goto finish;
        }
        if (isset($this->data['error'])) {
            $result['error'] = $this->data['error'];
            goto finish;
        }

        $result['potential'] = $this->data;

        finish:
        $this->semantic->execute($result);
    }

    /**
     * @param $task
     * @param $id
     *
     * Коллбек отрабатывающийся мультикурлом
     */
    public function callback($task, $id)
    {
        unset($id);
        $task = iconv('windows-1251', 'utf-8', $task);

        if (mb_strpos('_' . $task, 'ERROR')) {
            $this->data['error'] = 'Spywords error';
            goto point;
        }

        $this->data = array_map(function ($el) {
            $arr = explode("\t", $el);
            return isset($arr[6]) ? ['Top50KeysOrgTot' => (int)str_ireplace(' ', '', $arr[6])] : ['Top50KeysOrgTot' => 0];
            }, explode("\r\n", $task));

        if (!$this->data) {
            $this->data['error'] = 'Некорректные данные от сайвордс';
            goto point;
        }

        array_shift($this->data);
        array_pop($this->data);

        $one = array_shift($this->data);
        $this->data = $one['Top50KeysOrgTot'];

        point:
        if (!$this->data || isset($this->data['error'])) {
            file_put_contents(sprintf(self::LOG_FILE, public_path(), $this->domain),
                sprintf('[%s] домен - %s, некорректные данные:%s%s%s%s', date('Y-m-d H:i:s'), $this->domain, PHP_EOL, $task, PHP_EOL, PHP_EOL), FILE_USE_INCLUDE_PATH);
        }
    }
}