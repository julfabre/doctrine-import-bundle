<?php

declare(strict_types=1);

namespace Juf\DoctrineImportBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;

/**
 * An entity manager reading attribute mappings from a directory of generated entities.
 */
final class DoctrineTestConfiguration
{
    public static function entityManager(Connection $connection, string $entityDirectory): EntityManager
    {
        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([$entityDirectory]));

        // PHP 8.4+ lazy objects replace the generated proxies, whose configuration is then deprecated.
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);

            return new EntityManager($connection, $configuration);
        }

        $configuration->setProxyDir(sys_get_temp_dir().'/juf_doctrine_import/proxies');
        $configuration->setProxyNamespace('JufGeneratedProxies');

        return new EntityManager($connection, $configuration);
    }
}
