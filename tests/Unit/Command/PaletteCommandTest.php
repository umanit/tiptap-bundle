<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Command\PaletteCommand;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeRoundTripRunner;

#[CoversClass(PaletteCommand::class)]
final class PaletteCommandTest extends TestCase
{
    public function testInventoriesTheInlineStylesOfTheSelectedSource(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute(['--source' => ['article.body'], '--format' => 'json']));

        /** @var array{contents: int, color: array<string, int>, fontSize: array<string, int>} $payload */
        $payload = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(3, $payload['contents']);
        $this->assertSame(['#ff0000' => 3], $payload['color']);
        $this->assertSame(['14px' => 3], $payload['fontSize']);
    }

    public function testRendersTables(): void
    {
        $tester = $this->tester();

        $tester->execute(['--source' => ['article.body']]);

        $this->assertStringContainsString('Text colors — 1 distinct values', $tester->getDisplay());
        $this->assertStringContainsString('#ff0000', $tester->getDisplay());
    }

    public function testRejectsAnInvalidOption(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute(['--format' => 'xml']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--workers' => '0']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--source' => ['nope']]));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new PaletteCommand(
            new ContentSourceRegistry([new FakeContentSourceProvider([
                new FakeContentSource('article.body', 'content', ['<p>a</p>', '<p>b</p>', '<p>c</p>']),
            ])]),
            new FakeRoundTripRunner(),
        ));
    }
}
