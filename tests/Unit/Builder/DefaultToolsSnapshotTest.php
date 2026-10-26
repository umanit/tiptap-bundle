<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Umanit\TiptapBundle\Builder\DefaultToolsBuilder;
use Umanit\TiptapBundle\DTO\Tool;

/**
 * The JavaScript tests read the same snapshot: they check that the editor can run every command of the tools of the
 * bundle, and read every state they declare. Refresh it with `UPDATE_TOOLS_SNAPSHOT=1 vendor/bin/phpunit`.
 */
#[CoversClass(DefaultToolsBuilder::class)]
final class DefaultToolsSnapshotTest extends TestCase
{
    private const string SNAPSHOT = __DIR__ . '/../../../assets/tests/fixtures/default-tools.json';

    public function testSnapshotShownToTheJavaScriptTestsIsUpToDate(): void
    {
        $tools = array_map(
            static fn(Tool $tool): array => [
                'name'      => $tool->name,
                'command'   => $tool->command,
                'args'      => $tool->args,
                'active'    => $tool->active,
                'component' => $tool->component,
            ],
            [...new DefaultToolsBuilder()->build()],
        );
        $json = json_encode($tools, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n";

        if ('1' === getenv('UPDATE_TOOLS_SNAPSHOT')) {
            file_put_contents(self::SNAPSHOT, $json);
        }

        $this->assertStringEqualsFile(
            self::SNAPSHOT,
            $json,
            'The tools of the bundle changed: refresh the snapshot with `UPDATE_TOOLS_SNAPSHOT=1 vendor/bin/phpunit`.',
        );
    }
}
