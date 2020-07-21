<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

/**
 * Class DecorateSemantic
 * @package App\Semantic
 *
 * Класс-родитель для всех декораторов
 */
abstract class DecorateSemantic extends Semantic
{
    /**
     *
     * Логин в спайвордс
     */
    const LOGIN = 'some_login';

    /**
     *
     * Токен в спайвордс
     */
    const TOKEN = 'some_token';

    /**
     * @var Semantic
     *
     * Экземпляр класса для декоратора
     */
    protected $semantic;

    /**
     * @var string
     *
     * Домен в поле для логирования
     */
    protected $domain;

    /**
     * DecorateSemantic constructor.
     * @param Semantic $semantic
     */
    public function __construct(Semantic $semantic)
    {
        $this->semantic = $semantic;
        self::$result_file_path = sprintf('%s/%s', public_path(), self::RESULT_PATH);
    }

    /**
     * @param string $domain
     *
     * Создаем папку куда будем складывать результирующие файлы
     * если таковой нет
     */
    protected function makeResultDir(string $domain)
    {
        $result_dir = rtrim(sprintf(self::$result_file_path, $domain, ''), '/');
        if (!is_dir($result_dir)) {
            mkdir($result_dir);
            chmod($result_dir, 0777);
        }
    }
}