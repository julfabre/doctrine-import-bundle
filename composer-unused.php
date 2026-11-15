<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static fn (Configuration $config): Configuration => $config
    // Used at runtime by AbstractBundle and by the PHP loader of config/services.php,
    // which extends Symfony\Component\Config\Loader\FileLoader. No class is referenced directly.
    ->addNamedFilter(NamedFilter::fromString('symfony/config'))
;
