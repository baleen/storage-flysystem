<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace BaleenTest\Migrations\Storage;

use Baleen\Migrations\Exception\StorageException;
use Baleen\Migrations\Storage\AbstractStorage;
use Baleen\Migrations\Version;
use Baleen\Migrations\Version\Collection\Migrated;
use Baleen\Storage\FlyStorage;
use League\Flysystem\File;
use League\Flysystem\FilesystemInterface;
use Mockery as m;

/**
 * @author Gabriel Somoza <gabriel@strategery.io>
 */
class FlyStorageTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var array This must correspond to versions inside __DIR__ . '/../data/storage.txt'
     */
    private $versionIds = ['v1', 'v2', 'v3', 'v4', 'v5'];

    /** @var \League\Flysystem\File|m\Mock */
    private $handler;

    /** @var FilesystemInterface|m\Mock */
    private $filesystem;

    /**
     * tearDown
     */
    public function tearDown()
    {
        m::close();
        $this->handler = null;
        $this->filesystem = null;
        $this->instance = null;
    }

    /**
     * getInstance
     *
     * @param bool $hasPath
     * @param bool $isFile
     *
     * @return FlyStorage
     */
    public function getInstance($hasPath = true, $isFile = true) {
        $fileName = 'test.txt';
        $this->handler = m::mock(File::class);
        $this->handler->shouldReceive('isFile')->zeroOrMoreTimes()->andReturn($isFile);
        $this->handler->shouldReceive('getPath')->zeroOrMoreTimes()->andReturn($fileName);
        $this->handler->shouldReceive('getType')->zeroOrMoreTimes()->andReturn($isFile ? 'file' : 'dir');
        /** @var FileSystemInterface|m\Mock $filesystem */
        $filesystem = m::mock(FilesystemInterface::class);
        $filesystem->shouldReceive('has')->with($fileName)->once()->andReturn($hasPath);
        if (!$hasPath) {
            $filesystem->shouldReceive('write')->once()->with($fileName, '')->andReturn(true);
        }
        $filesystem->shouldReceive('get')->with($fileName)->zeroOrMoreTimes()->andReturn($this->handler);
        $this->filesystem = $filesystem;
        return new FlyStorage($filesystem, $fileName);
    }

    /**
     * testConstructor
     * @param $hasPath
     * @param $isFile
     * @dataProvider constructorProvider
     */
    public function testConstructor($hasPath, $isFile) {
        if (!$isFile) {
            $this->setExpectedException(StorageException::class);
        }
        $instance = $this->getInstance($hasPath, $isFile);
        $this->assertInstanceOf(AbstractStorage::class, $instance);
        $this->filesystem->shouldHaveReceived('has')->with(m::type('string'))->once();
    }

    public function constructorProvider() {
        $trueFalse = [true, false];
        return $this->combinations([$trueFalse, $trueFalse]);
    }

    /**
     * @param string $contents
     * @param $expectedIds
     *
     * @dataProvider fetchAllProvider
     */
    public function testDoFetchAll($contents, $expectedIds)
    {
        $instance = $this->getInstance();
        $this->handler->shouldReceive('read')->once()->andReturn((string) $contents);

        $method = new \ReflectionMethod($instance, 'doFetchAll');
        $method->setAccessible(true);
        $result = $method->invoke($instance);
        $this->assertInstanceOf(Migrated::class, $result);

        /** @var Migrated $result */
        $resultIds = array_map(function(Version $v) {
            return $v->getId();
        }, $result->toArray());
        foreach ($expectedIds as $id) {
            $this->assertContains($id, $resultIds);
        }
    }

    /**
     * fetchAllProvider
     * @return array
     */
    public function fetchAllProvider()
    {
        return [
            ['', []],
            ['single-line', ['single-line']],
            [implode("\n", $this->versionIds), $this->versionIds],
        ];
    }

    /**
     * @param Migrated $versions
     *
     * @param $expected
     * @param $result
     *
     * @throws StorageException
     * @dataProvider writeMigratedVersionsProvider
     */
    public function testWriteMigratedVersions($versions, $expected, $result)
    {
        $versions = new Migrated($versions);
        $instance = $this->getInstance();
        $this->handler->shouldReceive('put')->once()->with($expected)->andReturn($result);
        if ($result === false) {
            $this->setExpectedException(StorageException::class);
        }
        $instance->saveCollection($versions);
    }

    /**
     * writeMigratedVersionsProvider
     * @return array
     */
    public function writeMigratedVersionsProvider()
    {
        $versions = [];
        foreach ($this->versionIds as $id) {
            $version = new Version($id);
            $version->setMigrated(true);
            $versions[$id] = $version;
        }
        /** @var Version $firstVersion */
        $firstVersion = reset($versions);

        $expected = implode( "\n", $this->versionIds );

        return [
            [[$firstVersion], $firstVersion->getId(), true],
            [$versions, $expected, true],
            [$versions, $expected, false],
        ];
    }

    /**
     * Test 'save' and 'remove'
     *
     * @param $method
     * @param Version $version
     *
     * @dataProvider saveRemoveProvider
     */
    public function testSaveRemove($method, Version $version)
    {
        $instance = $this->getInstance();
        $this->handler->shouldReceive('read')->once()->andReturn(implode("\n", $this->versionIds));
        $expected = $this->versionIds;
        $pos = array_search($version->getId(), $expected);

        $willWrite = false;
        if ($method == 'save' && $pos === false) {
            array_push($expected, $version->getId());
            $willWrite = true;
        } elseif ($method == 'delete' && $pos !== false) {
            unset($expected[$pos]);
            $willWrite = true;
        }
        if ($willWrite) {
            $this->handler->shouldReceive('put')->once()->with(implode("\n", $expected))->andReturn(true);
        }

        $result = $instance->$method($version);
        $this->assertSame(
            $willWrite,
            $result,
            'Expected result to be the same as the result of the internal "saveCollection" call.'
        );
    }

    /**
     * saveRemoveProvider
     * @return array
     */
    public function saveRemoveProvider()
    {
        $methods = ['save', 'delete'];
        $versions = Version::fromArray([
            'v1', // first
            'v3', // middle
            'v5', // last
            'v6', // doesn't exist
        ]);
        foreach ($versions as $v) {
            $v->setMigrated(true);
        }
        return $this->combinations([$methods, $versions]);
    }

    /**
     * @param $arrays
     * @param int $i
     * @return array
     */
    public function combinations($arrays, $i = 0) {
        if (!isset($arrays[$i])) {
            return array();
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = $this->combinations($arrays, $i + 1);

        $result = array();

        // concat each array from tmp with each element from $arrays[$i]
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ?
                    array_merge(array($v), $t) :
                    array($v, $t);
            }
        }

        return $result;
    }
}
