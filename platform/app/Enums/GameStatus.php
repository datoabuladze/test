<?php

namespace App\Enums;

enum GameStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Archived = 'archived';
}
