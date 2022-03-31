<?php

namespace App\Util;

use Exception;

class HttpException extends Exception
{
    const TXT_BAD_REQUEST = 'Bad Request';
    const TXT_NOT_FOUND = 'Not Found';
    const TXT_INTERNAL_SERVER = 'Internal Server Error';
}