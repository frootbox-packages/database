<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Database;

use Doctrine\DBAL\Connection;

/**
 * Writes rows straight to the database.
 */
final readonly class DirectWriter implements Writer
{
    public function __construct(private Connection $connection) {}

    public function insert(string $table, array $values): array
    {
        $row = Values::row($values);
        $this->connection->insert($this->quote($table), $this->quoteKeys($row));
        $id = $row['id'] ?? null;
        $id = \is_int($id) || \is_string($id) ? $id : $this->connection->lastInsertId();

        return $this->find($table, $id) ?? throw EntityNotFoundException::for($table, $id);
    }

    public function update(string $table, int|string $id, array $values): array
    {
        $row = Values::row($values);
        unset($row['id']);

        if ([] !== $row) {
            $this->connection->update($this->quote($table), $this->quoteKeys($row), [$this->quote('id') => $id]);
        }

        return $this->find($table, $id) ?? throw EntityNotFoundException::for($table, $id);
    }

    public function delete(string $table, int|string $id): bool
    {
        return 0 < (int) $this->connection->delete($this->quote($table), [$this->quote('id') => $id]);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @phpstan-impure
     */
    public function find(string $table, int|string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM %s WHERE %s = ?', $this->quote($table), $this->quote('id')),
            [$id],
        );

        return false === $row ? null : $row;
    }

    private function quote(string $identifier): string
    {
        return $this->connection->quoteSingleIdentifier($identifier);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function quoteKeys(array $row): array
    {
        $quoted = [];

        foreach ($row as $column => $value) {
            $quoted[$this->quote($column)] = $value;
        }

        return $quoted;
    }
}
