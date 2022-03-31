<?php

namespace App\Util;

class BadRequestException extends HttpException
{
    protected $code = 400;
    protected $message = 'Bad Request';
}