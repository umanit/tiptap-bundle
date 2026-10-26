<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Command\PaletteCommand;
use Umanit\TiptapBundle\Palette\PaletteBuilder;
use Umanit\TiptapBundle\Palette\PaletteRenderer;
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
        $this->assertSame(Command::FAILURE, $tester->execute(['--max-distance' => '300']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--output-dir' => sys_get_temp_dir()]));
        $this->assertSame(Command::FAILURE, $tester->execute([
            '--builder-class' => 'not a class',
            '--output-dir'    => sys_get_temp_dir(),
        ]));
    }

    public function testProposesAPaletteAndWritesItsFiles(): void
    {
        $directory = sys_get_temp_dir() . '/umanit_tiptap_palette_' . bin2hex(random_bytes(4));
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute([
            '--source'        => ['article.body'],
            '--output-dir'    => $directory,
            '--builder-class' => 'App\\Wysiwyg\\LegacyColorsSetBuilder',
        ]));

        $this->assertStringContainsString('Proposed palette — 1 colors, 1 sizes', $tester->getDisplay());
        $this->assertStringContainsString('red-1', $tester->getDisplay());
        $this->assertStringContainsString('ut-fs-14', $tester->getDisplay());

        $this->assertFileExists($directory . '/LegacyColorsSetBuilder.php');
        $this->assertStringContainsString('.ut-bg-red-1', (string) file_get_contents($directory . '/palette.css'));

        /** @var array{color: array<string, string>} $legacyStyles */
        $legacyStyles = json_decode((string) file_get_contents($directory . '/legacy-styles.json'), true);

        $this->assertSame('ut-color-red-1', $legacyStyles['color']['#ff0000']);

        array_map(unlink(...), glob($directory . '/*') ?: []);
        rmdir($directory);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(
            new PaletteCommand(
                new ContentSourceRegistry([
                    new FakeContentSourceProvider([
                        new FakeContentSource('article.body', 'content', ['<p>a</p>', '<p>b</p>', '<p>c</p>']),
                    ]),
                ]),
                new FakeRoundTripRunner(),
                new PaletteBuilder(),
                new PaletteRenderer(),
            ),
        );
    }
}
