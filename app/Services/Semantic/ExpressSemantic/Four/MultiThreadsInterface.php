<?php

namespace App\Services\Semantic\ExpressSemantic\Four;

use App\Services\Semantic\ExpressSemantic\Four\Pthreads\DataProvider;


interface MultiThreadsInterface
{
    function getApiUrls(array &$result): array;

    function runChunks(DataProvider &$provider, array $api_urls): void;

    function runThreads(array $data, DataProvider &$provider): void;

    function postCalculation(DataProvider $provider, array &$result): void;
}