<?php

namespace App\Services\Lemmatizer;

use Morphy;


/**
 * Class Lemmatizer
 *
 * @package App\Process\Parser
 *
 * Лемматизация текстового контента элемента
 */
class Lemmatizer
{
    /**
     *
     * Максимальное количество символов, которые можно отправить в mystem
     * (вычислено эмпирическим путём)
     */
    const MAX_SYMBOLS = 126470;

    /**
     * @var string
     *
     * Путь к майстему
     */
    private static $mystemPath;

    /**
     * @param string $text_for_mystem
     * @param bool $point_service
     * @return string
     *
     * Возвращает лемматизированную строку
     */
    public static function lemmatizeString(string $text_for_mystem, bool $point_service=true): string
    {
        self::$mystemPath = public_path() . '/mystem';
        $text_for_mystem = self::breakdownIntoChunks($text_for_mystem);
        $words = array_map(
            function ($w) use($point_service) {
                $w = preg_replace('/\{|\}|\?/uisU', '', $w); if ($point_service && self::isService(explode("|", $w)[0])) {$w = '<' . $w . '>';
                } return $w;
            }, explode(' ', $text_for_mystem)
        );

        return implode(' ', $words);
    }

    /**
     * @param string $text
     * @return string
     *
     * Разбивка текста на чанки чтобы сука-блядь mystem не упал!
     */
    private static function breakdownIntoChunks(string $text): string
    {
        $chunks = [];
        $words = explode(" ", $text);
        $text = '';

        foreach ($words as $num_word=>$word) {
            if (strlen($text) >= self::MAX_SYMBOLS) {
                $chunks[] = rtrim($text . $word);
                $text = '';
            }else {
                $text .= $word . ' ';
            }
        }

        if ($text) {
            $chunks[] = rtrim($text);
        }

        $text = '';

        foreach ($chunks as $k=>$chunk) {
            exec('echo ' . $chunk . ' | ' . self::$mystemPath . " -c -l", $arr);
            if (isset($arr[0])) {
                $text .= $arr[0] . ' ';
            }
            unset($arr);
        }

        return rtrim($text);
    }

    /**
     * @param string $word
     * @return bool
     *
     * Является ли слово служебной частью речи
     */
    private static function isService(string $word): bool
    {
        $word = mb_strtoupper($word);
        $part = Morphy::getPartOfSpeech($word);

        if(in_array($part[0], ['СОЮЗ','МЕЖД','ЧАСТ','ПРЕДЛ','МС','МС-ПРЕДК','МС-П'])) {
            return true;
        }

        return false;
    }
}