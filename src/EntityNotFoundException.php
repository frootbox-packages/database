<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database;

use RuntimeException;

final class EntityNotFoundException extends RuntimeException
{
    public static function for(string $table, int|string $id): self
    {
        return new self(\sprintf('There is no row #%s in %s.', $id, $table));
    }
}
