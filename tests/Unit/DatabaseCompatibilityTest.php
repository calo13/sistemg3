<?php

namespace Tests\Unit;

use App\Support\DatabaseCompatibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseCompatibilityTest extends TestCase
{
    #[DataProvider('supportedServers')]
    public function test_supported_server_versions_are_accepted(string $driver, string $serverVersion): void
    {
        DatabaseCompatibility::assertSupported($driver, $serverVersion);

        $this->addToAssertionCount(1);
    }

    public static function supportedServers(): array
    {
        return [
            'MySQL exact minimum' => ['mysql', '8.0.16'],
            'MySQL next patch' => ['mysql', '8.0.17'],
            'MySQL later 8.0 release' => ['mysql', '8.0.36'],
            'MySQL LTS release' => ['mysql', '8.4.6'],
            'MySQL distribution suffix' => ['mysql', '8.4.6-0ubuntu0.24.04.2'],
            'MySQL commercial suffix' => ['mysql', '8.0.36-commercial'],
            'MySQL higher major release' => ['mysql', '9.0.0'],
            'MariaDB exact minimum for automatic JSON validation' => ['mariadb', '10.4.3-MariaDB'],
            'MariaDB patch comparison must be numeric' => ['mariadb', '10.4.10-MariaDB'],
            'MariaDB current XAMPP format' => ['mariadb', '10.6.28-MariaDB'],
            'MariaDB through mysql driver' => ['mysql', '10.6.28-MariaDB'],
            'MariaDB compatibility prefix' => ['mariadb', '5.5.5-10.6.28-MariaDB'],
            'MariaDB compatibility prefix through mysql driver' => ['mysql', '5.5.5-10.6.28-MariaDB'],
            'MariaDB minimum with compatibility prefix' => ['mysql', '5.5.5-10.4.3-MariaDB'],
            'MariaDB distribution suffix' => ['mysql', '10.11.14-MariaDB-0ubuntu0.24.04.1'],
            'MariaDB higher major release' => ['mariadb', '11.4.9-MariaDB'],
        ];
    }

    #[DataProvider('unsupportedServers')]
    public function test_unsupported_or_malformed_servers_are_rejected(string $driver, string $serverVersion): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseCompatibility::assertSupported($driver, $serverVersion);
    }

    public static function unsupportedServers(): array
    {
        return [
            'SQLite is unsupported' => ['sqlite', '3.45.1'],
            'PostgreSQL is unsupported' => ['pgsql', '17.0.0'],
            'Unknown driver is unsupported' => ['sqlsrv', '16.0.1000'],
            'Empty driver is unsupported' => ['', '8.4.6'],
            'MySQL 5.7 is too old' => ['mysql', '5.7.44'],
            'MySQL patch before minimum' => ['mysql', '8.0.15'],
            'MySQL comparison must be numeric' => ['mysql', '8.0.9'],
            'MySQL 8.0 first release is too old' => ['mysql', '8.0.0'],
            'MySQL lower major release is too old' => ['mysql', '7.9.99'],
            'MariaDB CHECK support alone is insufficient' => ['mariadb', '10.2.1-MariaDB'],
            'MariaDB minor release before minimum' => ['mariadb', '10.2.99-MariaDB'],
            'MariaDB Laravel minimum lacks automatic JSON validation' => ['mariadb', '10.3.0-MariaDB'],
            'MariaDB later 10.3 release is still too old' => ['mysql', '10.3.39-MariaDB'],
            'MariaDB patch before JSON validation minimum' => ['mariadb', '10.4.2-MariaDB'],
            'MariaDB older release through mysql driver' => ['mysql', '10.1.48-MariaDB'],
            'MariaDB compatibility prefix must not hide an old server' => ['mysql', '5.5.5-10.2.44-MariaDB'],
            'MariaDB higher than MySQL minimum but below MariaDB minimum' => ['mysql', '9.0.0-MariaDB'],
            'Empty version' => ['mysql', ''],
            'Blank version' => ['mariadb', '   '],
            'Unrecognized version text' => ['mysql', 'unknown'],
            'MySQL missing patch component' => ['mysql', '8.0'],
            'MySQL patch is not numeric' => ['mysql', '8.0.x'],
            'MySQL version must begin with digits' => ['mysql', 'MySQL 8.4.6'],
            'MySQL version with v prefix is malformed' => ['mysql', 'v8.4.6'],
            'MariaDB marker without version' => ['mariadb', '-MariaDB'],
            'MariaDB driver requires a MariaDB server version' => ['mariadb', '8.4.6'],
            'MariaDB missing patch component' => ['mariadb', '10.6-MariaDB'],
            'MariaDB incomplete version after compatibility prefix' => ['mysql', '5.5.5-10.6-MariaDB'],
            'MariaDB version must directly precede marker' => ['mysql', '8.4.6-invalid-MariaDB'],
        ];
    }
}
