<?php

namespace App\Services\Intake;

interface CutListParser
{
    public function parse(string $rawInput): ParsedCutList;
}
