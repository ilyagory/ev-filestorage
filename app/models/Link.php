<?php

use Phalcon\Mvc\Model;

/**
 * @property Stored $stored
 */
class Link extends Model
{
    /**
     * @var int
     */
    public $file;
    /**
     * @var string
     */
    public $link;
    /**
     * @var bool
     */
    public $secret = false;

    public function initialize()
    {
        $this->belongsTo(
            'file',
            Stored::class,
            'id',
            ['alias' => 'stored']
        );
    }
}