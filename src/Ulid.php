<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database;

/**
 * Universally unique, lexicographically sortable identifiers (ULID):
 * 26 characters, the first 10 encode the time in milliseconds, the rest
 * is random. They sort by creation time and can be created before a row
 * exists, e.g. for drafts that refer to each other.
 *
 *     $id = Ulid::generate(); // "01JBQ7Z9XK3M8N2P4R6T8V0W2Y"
 */
final class Ulid
{
    public const string PATTERN = '^[0-7][0-9A-HJKMNP-TV-Z]{25}$';

    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(?int $milliseconds = null): string
    {
        $time = $milliseconds ?? (int) floor(microtime(true) * 1000);
        $encoded = '';

        for ($i = 0; $i < 10; ++$i) {
            $encoded = self::ALPHABET[$time & 31] . $encoded;
            $time >>= 5;
        }

        // 80 random bits as two blocks of 40 bits, 8 characters each.
        foreach (str_split(random_bytes(10), 5) as $block) {
            $bits = 0;

            foreach (str_split($block) as $byte) {
                $bits = ($bits << 8) | \ord($byte);
            }

            $chunk = '';

            for ($i = 0; $i < 8; ++$i) {
                $chunk = self::ALPHABET[$bits & 31] . $chunk;
                $bits >>= 5;
            }

            $encoded .= $chunk;
        }

        return $encoded;
    }

    public static function isValid(string $value): bool
    {
        return 1 === preg_match('/' . self::PATTERN . '/', $value);
    }
}
