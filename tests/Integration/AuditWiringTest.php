<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Umanit\TiptapBundle\Tests\Fixtures\TestKernel;

/**
 * The audit as an application uses it: the command is registered, and the sources and rules of the application are
 * picked up by the autoconfiguration.
 */
#[CoversNothing]
final class AuditWiringTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheSourcesOfTheApplicationAreListed(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute(['--list' => true]));
        $this->assertStringContainsString('fixture.body', $tester->getDisplay());
    }

    public function testTheRulesOfTheApplicationWinOverTheOnesOfTheBundle(): void
    {
        if (!is_dir(__DIR__ . '/../../assets/node_modules/jsdom')) {
            self::markTestSkipped('The JavaScript dependencies are not installed.');
        }

        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute(['--source' => ['fixture.body'], '--format' => 'json']));

        /** @var array{sources: array{'fixture.body': array{total: int, categories: list<array{key: string}>}}} $report */
        $report = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        $source = $report['sources']['fixture.body'];

        $this->assertSame(2, $source['total']);
        // `style:color` is sorted by the application rule ("brand"), not by the one of the bundle ("color")
        $this->assertSame(['brand'], array_column($source['categories'], 'key'));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('umanit:tiptap:audit'));
    }
}
