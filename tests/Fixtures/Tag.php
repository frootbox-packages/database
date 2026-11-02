<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests\Fixtures;

use Frootbox\Database\Entity;

final readonly class Tag extends Entity
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $parentId = null,
    ) {}
}
