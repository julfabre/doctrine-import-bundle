<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static fn (Configuration $config): Configuration => $config
    // Provides the "doctrine" service (the ManagerRegistry injected in the command). No class is referenced directly.
    ->addNamedFilter(NamedFilter::fromString('doctrine/doctrine-bundle'))
;
