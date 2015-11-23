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

use Baleen\Migrations\Exception\Version\Repository\StorageException;
use Baleen\Migrations\Version;
use Baleen\Migrations\Version\Collection\Migrated;
use Baleen\Migrations\Version\Repository\Mapper\VersionMapperInterface;
use Baleen\Migrations\Version\VersionId;
use Baleen\Storage\FlyVersionMapper;
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
     * @return FlyVersionMapper
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
        return new FlyVersionMapper($filesystem, $fileName);
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
        $mapper = $this->getInstance($hasPath, $isFile);

        $this->assertInstanceOf(VersionMapperInterface::class, $mapper);

        $this->filesystem->shouldHaveReceived('has')->with(m::type('string'))->once();
    }

    /**
     * constructorProvider
     * @return array
     */
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
    public function testFetchAll($contents, $expectedIds)
    {
        $mapper = $this->getInstance();
        $this->handler->shouldReceive('read')->once()->andReturn((string) $contents);

        $ids = $mapper->fetchAll();
        $this->assertTrue(is_array($ids));

        $resultIds = array_map(function(VersionId $id) {
            return $id->toString();
        }, $ids);

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
     * @param array $versions
     * @param array $expected
     * @param       $result
     *
     * @throws StorageException
     * @dataProvider saveAllProvider
     */
    public function testSaveAll(array $versions, $expected, $result)
    {
        $mapper = $this->getInstance();

        $this->handler->shouldReceive('put')->once()->with($expected)->andReturn($result);
        if ($result === false) {
            $this->setExpectedException(StorageException::class);
        }

        $mapper->saveAll($versions);
    }

    /**
     * saveAllProvider
     * @return array
     */
    public function saveAllProvider()
    {
        $versions = array_map(function($id) {
            return new VersionId($id);
        }, $this->versionIds);

        /** @var VersionId $firstVersion */
        $firstVersion = reset($versions);

        $expected = implode( "\n", $this->versionIds );

        return [
            [[$firstVersion], $firstVersion->toString(), true],
            [$versions, $expected, true],
            [$versions, $expected, false],
        ];
    }

    /**
     * Test 'save' and 'remove'
     *
     * @param $method
     * @param VersionId $version
     *
     * @dataProvider saveRemoveProvider
     */
    public function testSaveRemove($method, VersionId $version)
    {
        $mapper = $this->getInstance();

        $this->handler->shouldReceive('read')->once()->andReturn(implode("\n", $this->versionIds));

        $expected = $this->versionIds;

        $pos = array_search($version->toString(), $expected);

        $willWrite = false;
        if ($method == 'save' && $pos === false) {
            array_push($expected, $version->toString());
            $willWrite = true;
        } elseif ($method == 'delete' && $pos !== false) {
            unset($expected[$pos]);
            $willWrite = true;
        }
        if ($willWrite) {
            $this->handler->shouldReceive('put')->once()->with(implode("\n", $expected))->andReturn(true);
        }

        $result = $mapper->$method($version);
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
        $versions = [
            new VersionId('v1'), // first
            new VersionId('v3'), // middle
            new VersionId('v5'), // last
            new VersionId('v6'), // doesn't exist
        ];
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

    /**
     * testFetch
     * @dataProvider fetchAllProvider
     * @param $contents
     * @param $availableIds
     */
    public function testFetch($contents, $availableIds)
    {
        $mapper = $this->getInstance();
        $this->handler->shouldReceive('read')->atLeast(1)->andReturn((string) $contents);

        foreach ($availableIds as $id) {
            $versionId = new VersionId($id);
            $res = $mapper->fetch($versionId);
            $this->assertInstanceOf(VersionId::class, $res);
        }
    }
}
