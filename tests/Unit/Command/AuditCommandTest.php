<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Umanit\TiptapBundle\Audit\Classification\CkeditorLossRule;
use Umanit\TiptapBundle\Audit\Classification\LossClassifier;
use Umanit\TiptapBundle\Audit\Source\ContentSourceRegistry;
use Umanit\TiptapBundle\Command\AuditCommand;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSourceProvider;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeRoundTripRunner;

#[CoversClass(AuditCommand::class)]
final class AuditCommandTest extends TestCase
{
    private FakeRoundTripRunner $runner;

    public function testListShowsTheDeclaredSourcesWithoutAuditingAnything(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute(['--list' => true]));
        $this->assertStringContainsString('article.body', $tester->getDisplay());
        $this->assertStringContainsString('emailing', $tester->getDisplay());
        $this->assertSame([], $this->runner->html);
    }

    public function testAuditsTheSelectedSourceAndRendersTheReport(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute(['--source' => ['article.body'], '--workers' => '2']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertCount(3, $this->runner->html);
        $this->assertSame(2, $this->runner->workers);
        $this->assertStringContainsString('article.body — 3 contents, 3 with a loss (100 %)', $tester->getDisplay());
        $this->assertStringContainsString('Text or background color', $tester->getDisplay());
        $this->assertStringContainsString('convertible', $tester->getDisplay());
    }

    public function testLegacyStylesFileIsPassedToTheRunner(): void
    {
        $tester = $this->tester();

        $tester->execute(['--source' => ['article.body'], '--legacy-styles' => 'palette.json']);

        $this->assertSame('palette.json', $this->runner->legacyStylesFile);
    }

    public function testLimitCapsTheContentsReadBySource(): void
    {
        $tester = $this->tester();

        $tester->execute(['--source' => ['article.body'], '--limit' => '2']);

        $this->assertCount(2, $this->runner->html);
    }

    public function testJsonFormatOutputsOnlyTheReport(): void
    {
        $tester = $this->tester();

        $tester->execute(['--source' => ['article.body'], '--format' => 'json']);

        /** @var array{totals: array{values: int, with_loss: int}} $payload */
        $payload = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(3, $payload['totals']['values']);
        $this->assertSame(3, $payload['totals']['with_loss']);
    }

    public function testGroupSelectsTheSourcesApart(): void
    {
        $tester = $this->tester();

        $tester->execute(['--group' => 'emailing', '--format' => 'json']);

        /** @var array{sources: array<string, mixed>} $payload */
        $payload = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(['newsletter.content'], array_keys($payload['sources']));
    }

    public function testInvalidOptionsAreRejected(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute(['--format' => 'yaml']));
        $this->assertStringContainsString('Unknown format', $tester->getDisplay());

        $this->assertSame(Command::FAILURE, $tester->execute(['--workers' => '0']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--limit' => 'abc']));
        $this->assertSame(Command::FAILURE, $tester->execute(['--source' => ['nope.nope']]));
        $this->assertStringContainsString('Unknown content source', $tester->getDisplay());
    }

    public function testNothingMatchingTheGroupIsNotAnError(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute(['--group' => 'unknown']));
        $this->assertStringContainsString('No content source matches', $tester->getDisplay());
    }

    public function testRunnerFailureIsReportedAsFailure(): void
    {
        $tester = $this->tester(new \RuntimeException('The script of the editor cannot be found'));

        $this->assertSame(Command::FAILURE, $tester->execute(['--source' => ['article.body']]));
        $this->assertStringContainsString('cannot be found', $tester->getDisplay());
    }

    private function tester(?\RuntimeException $runnerFailure = null): CommandTester
    {
        $this->runner = new FakeRoundTripRunner($runnerFailure);

        return new CommandTester(
            new AuditCommand(
                new ContentSourceRegistry([
                    new FakeContentSourceProvider([
                        new FakeContentSource('article.body'),
                        new FakeContentSource('newsletter.content', 'emailing'),
                    ]),
                ]),
                $this->runner,
                new LossClassifier([new CkeditorLossRule()]),
            ),
        );
    }
}
