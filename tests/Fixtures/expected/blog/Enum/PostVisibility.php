<?php

declare(strict_types=1);

namespace App\Enum;

enum PostVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Unlisted = 'unlisted';
}
