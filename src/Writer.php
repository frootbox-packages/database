<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database;

/**
 * Writes single rows by their id. Repositories write only through it, so
 * an application can decide how writing works: directly (DirectWriter) or,
 * as in the Frootbox CMS, only inside actions and recorded for undo.
 */
interface Writer
{
    /**
     * @param array<string, mixed> $values by property or column name
     *
     * @return array<string, mixed> the new row as stored
     */
    public function insert(string $table, array $values): array;

    /**
     * @param array<string, mixed> $values by property or column name
     *
     * @return array<string, mixed> the changed row as stored
     *
     * @throws EntityNotFoundException
     */
    public function update(string $table, int|string $id, array $values): array;

    /**
     * @return bool false if there was no such row
     */
    public function delete(string $table, int|string $id): bool;
}
