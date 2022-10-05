<?php

namespace App\Enum;

enum ErrorResponseType: string
{
    case SERVER_ERROR = 'server_error';
    case INVALID_REQUEST = 'invalid_request';
}
