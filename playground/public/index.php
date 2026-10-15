<?php

declare(strict_types=1);

use Playground\Kernel;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

return static fn(array $context): \Playground\Kernel => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
