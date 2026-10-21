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
 * The cleaning as an application uses it: the command is registered with the rules of the bundle, in order.
 */
#[CoversNothing]
final class CleanWiringTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheRulesOfTheBundleAreRegisteredInOrder(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('umanit:tiptap:clean'));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--list-rules' => true]));

        $display = $tester->getDisplay();
        $names = ['word-language', 'word-classes', 'font-tag', 'word-styles', 'table-layout', 'small-indent'];
        $positions = \array_map(static fn(string $name): int|false => \strpos($display, $name), $names);

        $this->assertNotContains(false, $positions);

        $sorted = $positions;
        \sort($sorted);

        // Listed in the order they run
        $this->assertSame($sorted, $positions);
        $this->assertStringContainsString('empty-paragraphs', $display);
    }
}
