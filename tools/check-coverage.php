<?php

declare(strict_types=1);

// PHPUnit has no built-in line coverage threshold: read the Clover report instead.

$file = $argv[1] ?? 'var/coverage/clover.xml';
$minimum = (float) ($argv[2] ?? 95);

if (!is_file($file)) {
    fwrite(\STDERR, sprintf('Coverage report "%s" not found. Run "composer test:coverage" first.%s', $file, \PHP_EOL));
    exit(1);
}

$report = simplexml_load_file($file);
$nodes = false === $report ? null : $report->xpath('/coverage/project/metrics');
$metrics = is_array($nodes) ? $nodes[0] ?? null : null;
if (null === $metrics) {
    fwrite(\STDERR, sprintf('Coverage report "%s" has no project metrics.%s', $file, \PHP_EOL));
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$coverage = 0 === $statements ? 100.0 : 100 * $covered / $statements;

printf('Line coverage: %.2f %% (%d/%d), minimum %.2f %%.%s', $coverage, $covered, $statements, $minimum, \PHP_EOL);

exit($coverage >= $minimum ? 0 : 1);
