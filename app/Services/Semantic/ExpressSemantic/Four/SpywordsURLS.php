<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Multistreams\Curl;


/**
 * Class SpywordsURLS
 * @package App\Semantic\Four
 *
 * Spyword urls
 */
class SpywordsURLS extends DecorateSemantic
{
    /**
     *
     * API url
     */
    const API_URL = 'https://api.spywords.ru/?method=DomainUrl&site=%s&se=yandex&login=%s&token=cc71bf1355d6880089cb84616aa6d75c&limit=200&desc=Top10KeysOrgTot';

    /**
     *
     * Логирующий файл
     */
    const LOG_FILE = '%s/semantic/four/%s/spywords_urls.txt';

    /**
     * @var array
     *
     * Массив данных из апи
     */
    private $data = [];

    /**
     * @var array
     *
     * Массив со стоп-словами урлов
     */
    private $stop_urls_words = [];

    /**
     * SpywordsURLS constructor.
     * @param Semantic $semantic
     */
    public function __construct(Semantic $semantic)
    {
        parent::__construct($semantic);
        $this->stop_urls_words = StopWords::getStopUrlsWords();
    }

    /**
     * @param array $result
     *
     * Запуск класса
     */
    public function execute(array &$result)
    {
        $this->domain = $result['domain'];
        if ($result['error']) goto finish;
        $api_url = sprintf(self::API_URL, $result['domain'], self::LOGIN);

        $curl = new Curl();
        $curl->setCallback([$this, 'callback']);
        $curl->execute_single_thread($api_url);

        if (!$this->data) {
            $result['error'] = sprintf('Домен %s. Ошибка при вычислении урлов по домену. %s. Данные из spywords: %s',
                $result['domain'], $api_url, sprintf(self::LOG_FILE, env('APP_URL'), $result['domain']));
            goto finish;
        }
        if (isset($this->data['error'])) {
            $result['error'] = $this->data['error'];
            goto finish;
        }

        point:
        $result['data'] = $this->data;

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

        $this->data = array_map(function ($el){$arr = explode("\t", $el); return ['url' => array_shift($arr), 'id' => array_pop($arr),
            'Top50KeysOrgTot' => isset($arr[2]) ? (int)$arr[2] : 0];}, explode("\r\n", $task));

        array_shift($this->data);
        array_pop($this->data);
        usort($this->data, function ($a, $b){return $a['Top50KeysOrgTot'] <= $b['Top50KeysOrgTot'];});

        $pattern = "/\.pdf$|\.jpg$|\.rtf$|\.gif$|\.png$|\.xls$|\.xlsx$|\.doc$|\.docx$/uisU";
        $count_urls = 0;
        $summ = 0;
        $flag = false;

        foreach ($this->data as $k=>&$one) {
            if ($flag || $one['Top50KeysOrgTot'] === 0) {
                unset($this->data[$k]);
                continue;
            }

            foreach ($this->stop_urls_words as $word) {
                $parse_url = parse_url($one['url']);
                $path = $parse_url['path'] ?? '';
                $query = $parse_url['query'] ?? '';
                $path .= $query;

                if (mb_strpos('_'.$path, $word) || preg_match($pattern, $path)) {
                    unset($this->data[$k]);
                    continue;
                }
            }

            if (!isset($this->data[$k])) continue;

            $count_urls++;
            if ($one['Top50KeysOrgTot'] > 200) $one['Top50KeysOrgTot'] = 200;
            $summ += $one['Top50KeysOrgTot'] > 100 ? 100 : $one['Top50KeysOrgTot'];

            if (($count_urls >= 5 && $summ >= 1000) || $count_urls >= 30) $flag = true;

            $one['url'] = $one['id'];
            unset($one['id']);
            unset($one['Top50KeysOrgTot']);
        }

        point:
        if (!$this->data || isset($this->data['error'])) {
            file_put_contents(sprintf(self::LOG_FILE, public_path(), $this->domain),
                sprintf('[%s] домен - %s, некорректные данные:%s%s%s%s', date('Y-m-d H:i:s'), $this->domain, PHP_EOL, $task, PHP_EOL, PHP_EOL), FILE_USE_INCLUDE_PATH);
        }
    }
}