<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
}
