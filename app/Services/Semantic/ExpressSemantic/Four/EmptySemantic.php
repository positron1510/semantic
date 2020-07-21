<?php

namespace App\Services\Semantic\ExpressSemantic\Four;


class EmptySemantic extends Semantic
{
    public function execute(array &$result)
    {
        return $result;
    }
}