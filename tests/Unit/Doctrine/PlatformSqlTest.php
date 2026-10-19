<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Doctrine\Storage\PlatformSql;
use Umanit\TiptapBundle\Doctrine\Value;

#[CoversClass(PlatformSql::class)]
#[CoversClass(Value::class)]
final class PlatformSqlTest extends TestCase
{
    /**
     * @return iterable<string, array{AbstractPlatform, string, string}>
     */
    public static function platforms(): iterable
    {
        yield 'postgresql' => [new PostgreSQLPlatform(), 'CAST(c AS TEXT)', 'c = :p'];
        yield 'sqlite' => [new SQLitePlatform(), 'CAST(c AS TEXT)', 'c = :p'];
        yield 'mysql' => [new MySQLPlatform(), 'CAST(c AS CHAR)', 'BINARY c = :p'];
        yield 'mariadb' => [new MariaDBPlatform(), 'CAST(c AS CHAR)', 'BINARY c = :p'];
    }

    #[DataProvider('platforms')]
    public function testSqlFollowsThePlatform(AbstractPlatform $platform, string $cast, string $comparison): void
    {
        $this->assertSame($cast, PlatformSql::castToText($platform, 'c'));
        $this->assertSame($comparison, PlatformSql::equalsExactly($platform, 'c', ':p'));
    }

    public function testValuesFromTheDatabaseAreCast(): void
    {
        $this->assertSame('42', Value::toString(42));
        $this->assertSame('', Value::toString(null));
        $this->assertSame('', Value::toString([]));
        $this->assertSame(7, Value::toInt('7'));
        $this->assertSame(0, Value::toInt('abc'));
    }
}
