<?php

namespace App\Enums;

enum ServiceMode: string
{
    case LIVE = 'live';
    case TEST = 'test';
    case DUMMY = 'dummy';
}
