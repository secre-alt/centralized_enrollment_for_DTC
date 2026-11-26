<?php

namespace App\Exceptions;

class LifecycleOperationDeclined extends \RuntimeException
{
    public function __construct(public $outcome)
    {
        parent::__construct('Lifecycle operation declined.');
    }
}
