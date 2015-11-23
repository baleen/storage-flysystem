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

use Baleen\Migrations\Exception\Version\Repository\StorageException;
use Baleen\Migrations\Shared\ValueObjectInterface;
use Baleen\Migrations\Version\Repository\Mapper\VersionMapperInterface;
use Baleen\Migrations\Version\VersionId;
use League\Flysystem\File;
use League\Flysystem\FilesystemInterface;

/**
 * {@inheritDoc}
 *
 * @author Gabriel Somoza <gabriel@strategery.io>
 */
final class FlyVersionMapper implements VersionMapperInterface
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
     * @inheritdoc
     */
    public function save(VersionId $id)
    {
        $result = false;
        $stored = $this->fetchAll();

        $index = $this->indexOf($id, $stored);
        if (null === $index) {
            $stored[] = $id;
            $result = $this->saveAll($stored);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function delete(VersionId $id)
    {
        $result = false;
        $index = null;

        $stored = $this->fetchAll();

        $index = $this->indexOf($id, $stored);
        if (null !== $index) {
            unset($stored[$index]);
            $result = $this->saveAll($stored);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function fetchAll()
    {
        $contents = $this->file->read();
        $lines = explode("\n", $contents);

        $collection = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) { // skip empty lines
                $collection[] = new VersionId($line);
            }
        }

        return $collection;
    }

    /**
     * @inheritdoc
     */
    public function fetch(VersionId $id)
    {
        $result = null;
        $stored = $this->fetchAll();

        $index = $this->indexOf($id, $stored);

        return null !== $index ? $stored[$index] : null;
    }

    /**
     * @inheritdoc
     */
    public function saveAll(array $ids)
    {
        $ids = array_map(
            function (VersionId $v) {
                return $v->toString();
            },
            $ids
        );
        $contents = implode("\n", $ids);

        $result = $this->file->put($contents);
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
     * Finds a needle VersionId in a VersionId[] haystack
     * @param VersionId $needle
     * @param array $haystack
     * @return int|null
     */
    private function indexOf(VersionId $needle, array $haystack) {
        foreach ($haystack as $index => $item) {
            /** @var ValueObjectInterface $item */
            if ($needle->isSameValueAs($item)) {
                return $index;
            }
        }
        return null;
    }
}
