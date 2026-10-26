<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Audit\Source\AuditedContent;
use Umanit\TiptapBundle\Rewrite\ContentRewriter;
use Umanit\TiptapBundle\Rewrite\PlannedChange;
use Umanit\TiptapBundle\Rewrite\RewritePlan;
use Umanit\TiptapBundle\Tests\Fixtures\Audit\FakeContentSource;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryMigrationBackup;
use Umanit\TiptapBundle\Tests\Fixtures\Migration\InMemoryWritableSource;

#[CoversClass(ContentRewriter::class)]
#[CoversClass(RewritePlan::class)]
#[CoversClass(PlannedChange::class)]
final class ContentRewriterTest extends TestCase
{
    public function testItSeparatesTheSourcesWhichCannotBeWritten(): void
    {
        $writable = new InMemoryWritableSource('db', []);

        [$sources, $readOnly] = new ContentRewriter()->partition([$writable, new FakeContentSource('files')]);

        $this->assertSame(['db' => $writable], $sources);
        $this->assertSame(['files'], $readOnly);
    }

    public function testItRefusesToApplyWithoutBackup(): void
    {
        $this->expectException(\RuntimeException::class);

        new ContentRewriter()->assertCanSave(true);
    }

    public function testItWritesDistinctRowsAndPathsWhoseKeysLookLikeIntegersAndSeparators(): void
    {
        $source = new InMemoryWritableSource(
            'db',
            ['1|a' => ['2|b' => '<p>a</p>', '3' => '<p>b</p>'], '1' => ['2|b' => '<p>c</p>']],
        );
        $backup = new InMemoryMigrationBackup();
        $plan = new RewritePlan();

        foreach ($source->rows as $id => $paths) {
            foreach ($paths as $path => $html) {
                $plan->add(
                    new PlannedChange(
                        new AuditedContent('db', 'default', (string) $id, null, (string) $path, $html),
                        $html . '!',
                        $id . '/' . $path,
                    ),
                );
            }
        }

        $written = [];
        new ContentRewriter($backup)->write(
            ['db' => $source],
            $plan,
            'run',
            true,
            static function (PlannedChange $change, bool $replaced) use (&$written): void {
                $written[$change->reference] = $replaced;
            },
        );

        $this->assertSame(['1|a/2|b' => true, '1|a/3' => true, '1/2|b' => true], $written);
        $this->assertSame(
            ['1|a' => ['2|b' => '<p>a</p>!', '3' => '<p>b</p>!'], '1' => ['2|b' => '<p>c</p>!']],
            $source->rows,
        );
    }

    public function testItReportsAContentEditedInTheMeantimeAndForgetsItsBackup(): void
    {
        $source = new InMemoryWritableSource('db', ['1' => ['' => '<p>edited</p>']]);
        $backup = new InMemoryMigrationBackup();
        $plan = new RewritePlan();
        $plan->add(
            new PlannedChange(new AuditedContent('db', 'default', '1', null, '', '<p>read</p>'), '<p>new</p>', '1'),
        );

        $written = null;
        new ContentRewriter($backup)->write(
            ['db' => $source],
            $plan,
            'run',
            true,
            static function (PlannedChange $change, bool $replaced) use (&$written): void {
                $written = $replaced;
            },
        );

        $this->assertFalse($written);
        $this->assertSame(['1' => ['' => '<p>edited</p>']], $source->rows);
        $this->assertSame([], $backup->findToRestore('run'));
    }

    public function testASimulationWritesNothing(): void
    {
        $source = new InMemoryWritableSource('db', ['1' => ['' => '<p>a</p>']]);
        $plan = new RewritePlan();
        $plan->add(new PlannedChange(new AuditedContent('db', 'default', '1', null, '', '<p>a</p>'), '<p>b</p>', '1'));

        $written = null;
        new ContentRewriter()->write(
            ['db' => $source],
            $plan,
            'run',
            false,
            static function (PlannedChange $change, bool $replaced) use (&$written): void {
                $written = $replaced;
            },
        );

        $this->assertTrue($written);
        $this->assertSame(['1' => ['' => '<p>a</p>']], $source->rows);
    }
}
