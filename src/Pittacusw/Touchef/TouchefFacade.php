<?php

namespace Pittacusw\Touchef;

use Illuminate\Support\Facades\Facade;

class TouchefFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'Touchef';
    }
}
