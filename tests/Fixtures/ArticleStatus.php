<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Fixtures;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
