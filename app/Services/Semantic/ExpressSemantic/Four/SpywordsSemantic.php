<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Multistreams\Curl;


class SpywordsSemantic extends DecorateSemantic
{
    /**
     *
     * Файл промежуточного результата
     */
    const RESULT_FILE = 'spywords';

    /**
     *
     * API url spywords
     */
    const API_URL = 'https://api.spywords.ru/?method=DomainUrlOrganic&site=%s&url=%s&se=yandex&login=%s&token=%s&limit=100';

    /**
     *
     * Логирующий файл
     */
    const LOG_FILE = '%s/semantic/four/%s/spywords_semantic.txt';

    /**
     * @var array
     *
     * Массив с данными
     */
    private $data = [];

    /**
     * @param array $result
     * @return mixed|void
     *
     * Запуск класса
     */
    public function execute(array &$result)
    {
        $this->domain = $result['domain'];
        if ($result['error']) goto finish;

        $api_urls = [];
        foreach ($result['data'] as $one) {
            $api_urls[$one['url']] = sprintf(self::API_URL, $result['domain'], $one['url'], self::LOGIN, self::TOKEN);
        }

        $curl = new Curl();
        $curl->setCallback([$this, 'callback']);

        foreach ($api_urls as $id=>$api_url) {
            $curl->execute_single_thread($api_url, $id);
        }

        if (isset($this->data['error'])) {
            $result['error'] = $this->data['error'];
            goto finish;
        }

        $keywords = [];
        $data = [];

        foreach ($this->data as $item) {
            foreach ($item as $one) {
                if (!in_array($one['keyword'], $keywords)) {
                    $keywords[] = $one['keyword'];
                    $data[] = $one;
                }
            }
        }
        unset($this->data);

        if (!$data) {
            $result['error'] = sprintf('Домен %s. Spywords прислал пустое значение. Данные из spywords: %s',
                $result['domain'], sprintf(self::LOG_FILE, env('APP_URL'), $result['domain']));
            goto finish;
        }

        $result['data'] = $data;
        $this->clearKeywords($result);

        # если режим отладки логируем этап
        if ($result['debug']) {
            $this->mediumLog($result);
        }

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
        $task = iconv('windows-1251', 'utf-8', $task);

        if (mb_strpos('_' . $task, 'ERROR')) {
            $this->data['error'] = 'Spywords error';
            goto point;
        }

        $data = array_map(function ($el){$arr = explode("\t", $el); return ['keyword' => isset($arr[0]) ? $arr[0] : '', 'volume' => isset($arr[2]) ? (int)str_ireplace(' ', '', $arr[2]) : 0,
            'pos' => isset($arr[3]) ? (int)$arr[3] : 0, 'url' => isset($arr[6]) ? $arr[6] : ''];}, explode("\r\n", $task));
        $data = array_filter($data, function ($el){return $el['keyword'] && $el['volume'] && $el['pos'] && $el['url'];});

        $this->data[] = $data;

        point:
        if (!$this->data || isset($this->data['error'])) {
            file_put_contents(sprintf(self::LOG_FILE, public_path(), $this->domain),
                sprintf('[%s] домен - %s, url - %s ,некорректные данные:%s%s%s%s', date('Y-m-d H:i:s'), $this->domain, $id, PHP_EOL, $task, PHP_EOL, PHP_EOL), FILE_APPEND);
        }
    }

    /**
     * @param $result
     *
     * Чистка ключевиков от всякого говна
     */
    protected function clearKeywords(&$result)
    {
        foreach ($result['data'] as &$one) {
            $one['keyword'] = implode(' ', array_map(function ($el){return trim($el, "{}[].?!:;()-&$@*%#+-/<>|~`");},
                preg_split("/[\s]+/uisU", $one['keyword'], -1, PREG_SPLIT_NO_EMPTY)));
        }
    }
}