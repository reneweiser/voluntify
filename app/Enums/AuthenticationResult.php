<?php

namespace App\Enums;

enum AuthenticationResult: string
{
    case Success = 'success';
    case Expired = 'expired';
    case NotYetActive = 'not_yet_active';
    case InvalidCode = 'invalid_code';
}
