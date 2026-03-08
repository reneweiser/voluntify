<?php

namespace App\Exceptions;

class CancellationCutoffPassedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The cancellation cutoff has passed for this shift.');
    }
}
