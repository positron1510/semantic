<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

/**
 * Trait Logger
 * @package App\Services\Semantic\ExpressSemantic\Four
 *
 * Трейт логирования промежуточных и окончательного результата
 */
trait Logger
{
    /**
     * @param array $result
     *
     * Логирование промежуточного результата
     */
    protected function mediumLog(array &$result)
    {
        file_put_contents(sprintf(self::$result_file_path, $result['domain'], static::RESULT_FILE), serialize($result), FILE_USE_INCLUDE_PATH);
        chmod(sprintf(self::$result_file_path, $result['domain'], static::RESULT_FILE), 0777);
    }

    /**
     * @param array $result
     *
     * Логирование окончательного результата
     */
    protected function finalLog(array &$result)
    {
        file_put_contents(sprintf(self::$result_file_path, $result['domain'], self::RESULT_FILE), serialize($result), FILE_USE_INCLUDE_PATH);
        chmod(sprintf(self::$result_file_path, $result['domain'], self::RESULT_FILE), 0777);
    }
}