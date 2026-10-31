<?php

/*
 * SPDX-License-Identifier: LGPL-3.0-only
 */

declare(strict_types=1);

namespace Frootbox\Database\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Frootbox\Database\Values;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Values::class)]
final class ValuesTest extends TestCase
{
    public function testStoresTimesInTheTimeZoneOfPhp(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            self::assertSame('2026-11-01 14:00:00', Values::toDatabase(new DateTimeImmutable('2026-11-01T18:00:00+05:00')));
            self::assertSame('2026-11-01 14:00:00', Values::toDatabase(new DateTimeImmutable('2026-11-01 14:00:00', new DateTimeZone('Europe/Berlin'))));
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
