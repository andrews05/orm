<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Mapping\Symfony;

use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\Tests\DoctrineTestCase;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function unlink;

/**
 * @group DDC-1418
 */
abstract class AbstractDriverTest extends DoctrineTestCase
{
    public function testFindMappingFile()
    {
        $driver = $this->getDriver(
            [
            'MyNamespace\MySubnamespace\EntityFoo' => 'foo',
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
            ]
        );

        touch($filename = $this->dir . '/Foo' . $this->getFileExtension());
        self::assertEquals($filename, $driver->getLocator()->findMappingFile('MyNamespace\MySubnamespace\Entity\Foo'));
    }

    public function testFindMappingFileInSubnamespace()
    {
        $driver = $this->getDriver(
            [
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
            ]
        );

        touch($filename = $this->dir . '/Foo.Bar' . $this->getFileExtension());
        self::assertEquals($filename, $driver->getLocator()->findMappingFile('MyNamespace\MySubnamespace\Entity\Foo\Bar'));
    }

    public function testFindMappingFileNamespacedFoundFileNotFound()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('No mapping file found named');

        $driver = $this->getDriver(
            [
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
            ]
        );

        $driver->getLocator()->findMappingFile('MyNamespace\MySubnamespace\Entity\Foo');
    }

    public function testFindMappingNamespaceNotFound()
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage("No mapping file found named 'Foo" . $this->getFileExtension() . "' for class 'MyOtherNamespace\MySubnamespace\Entity\Foo'.");

        $driver = $this->getDriver(
            [
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
            ]
        );

        $driver->getLocator()->findMappingFile('MyOtherNamespace\MySubnamespace\Entity\Foo');
    }

    protected function setUp()
    {
        $this->dir = sys_get_temp_dir() . '/abstract_driver_test';
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown()
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $path) {
            if ($path->isDir()) {
                @rmdir((string) $path);
            } else {
                @unlink((string) $path);
            }
        }

        @rmdir($this->dir);
    }

    abstract protected function getFileExtension();
    abstract protected function getDriver(array $paths = []);
}
