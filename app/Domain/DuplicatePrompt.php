<?php

namespace App\Domain;

use App\Models\Patient;

class DuplicatePrompt extends \Exception
{
    public function __construct(public Patient $patient, public string $kind)
    {
        parent::__construct('duplicate');
    }
}
