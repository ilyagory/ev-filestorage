<?php

namespace App\Util;

class NotFoundException extends HttpException
{
    protected $code = 404;
    protected $message = 'Not Found';
}