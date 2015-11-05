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
 * <https://github.com/baleen/migrations>.
 */

namespace Baleen\Storage;

use Baleen\Migrations\Exception\StorageException;
use Baleen\Migrations\Storage\AbstractStorage;
use Baleen\Migrations\Version;
use Baleen\Migrations\Version\Collection\Migrated;
use Baleen\Migrations\Version\VersionInterface;
use League\Flysystem\File;
use League\Flysystem\FilesystemInterface;

/**
 * {@inheritDoc}
 *
 * @author Gabriel Somoza <gabriel@strategery.io>
 */
final class FlyStorage extends AbstractStorage
{
    const DEFAULT_FILENAME = '.baleen_versions';

    /** @var File */
    private $file;

    /**
     * @param FilesystemInterface $filesystem
     * @param string              $fileName
     *
     * @throws StorageException
     */
    public function __construct(FilesystemInterface $filesystem, $fileName = self::DEFAULT_FILENAME)
    {
        if (!$filesystem->has($fileName)) {
            $filesystem->write($fileName, '');
        }
        $handler = $filesystem->get($fileName);
        if (!$handler->isFile()) {
            throw new StorageException(
                sprintf(
                    'Expected path "%s" to be a file but its a "%s".',
                    $handler->getPath(),
                    $handler->getType()
                )
            );
        }
        $this->file = $handler;
    }

    /**
     * {@inheritdoc}
     *
     * @param VersionInterface $version
     *
     * @return bool|int
     *
     * @throws StorageException
     */
    public function save(VersionInterface $version)
    {
        $result = false;
        $stored = $this->fetchAll();
        if (!$stored->getById($version->getId())) {
            $stored->add($version);
            $result = $this->saveCollection($stored);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param Migrated $versions
     *
     * @return int
     *
     * @throws StorageException
     */
    public function saveCollection(Migrated $versions)
    {
        $ids = array_map(
            function (VersionInterface $v) {
                return $v->getId();
            },
            $versions->toArray()
        );
        $contents = implode("\n", $ids);

        $result = $this->file->write($contents);
        if ($result === false) {
            throw new StorageException(
                sprintf(
                    'Could not write to file "%s".',
                    $this->file->getPath()
                )
            );
        }

        return (bool) $result;
    }

    /**
     * @{inheritdoc}
     * @param VersionInterface $version
     * @return bool|int
     * @throws StorageException
     */
    public function delete(VersionInterface $version)
    {
        $result = false;
        $stored = $this->fetchAll();
        $element = $stored->getById($version->getId());
        if ($element) {
            $stored->removeElement($element);
            $result = $this->saveCollection($stored);
        }

        return $result;
    }

    /**
     * Reads versions from the storage file.
     *
     * @return VersionInterface[]
     *
     * @throws StorageException
     */
    protected function doFetchAll()
    {
        $contents = $this->file->read();
        $lines = explode("\n", $contents);

        $collection = new Migrated();
        foreach ($lines as $versionId) {
            $versionId = trim($versionId);
            if (!empty($versionId)) { // skip empty lines
                $version = new Version($versionId);
                $version->setMigrated(true); // if its in storage its because it has been migrated
                $collection->add($version);
            }
        }

        return $collection;
    }
}
