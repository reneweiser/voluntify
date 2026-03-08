<?php

namespace App\Exceptions;

class MemberAlreadyExistsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This user is already a member.');
    }
}
