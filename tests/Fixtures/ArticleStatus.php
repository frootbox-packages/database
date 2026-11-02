<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Fixtures;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
