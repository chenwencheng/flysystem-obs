<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use League\Flysystem\Visibility;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

class ValidAdapterTest extends TestCase
{
    private ObsAdapter $obsAdapter;

    private function getKey(): string
    {
        return (string) getenv('OBS_KEY') ?: '';
    }

    private function getSecret(): string
    {
        return (string) getenv('OBS_SECRET') ?: '';
    }

    protected function getBucket(): string
    {
        return (string) getenv('OBS_BUCKET') ?: '';
    }

    protected function getEndpoint(): string
    {
        return (string) getenv('OBS_ENDPOINT') ?: 'obs.cn-east-3.myhuaweicloud.com';
    }

    protected function isBucketEndpoint(): bool
    {
        return false;
    }

    protected function signature(): ?string
    {
        return null;
    }

    protected function setUp(): void
    {
        if ((string) getenv('MOCK') !== 'false') {
            $this->markTestSkipped('Mock tests enabled');
        }

        parent::setUp();

        $config = [
            'key' => $this->getKey(),
            'secret' => $this->getSecret(),
            'bucket' => $this->getBucket(),
            'endpoint' => $this->getEndpoint(),
            'is_cname' => $this->isBucketEndpoint(),
            'signature' => $this->signature(),
            'path_style' => '',
            'region' => '',
        ];

        $this->obsAdapter = new ObsAdapter(new ObsClient($config), $this->getBucket());
        $this->obsAdapter->write('fixture/read.txt', 'read-test', new Config());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->obsAdapter->deleteDirectory('fixture');
    }

    public function testCopy(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->obsAdapter->copy('fixture/file.txt', 'fixture/copy.txt', new Config());
        $this->assertSame('write', $this->obsAdapter->read('fixture/copy.txt'));
    }

    public function testCopyWithoutRetainVisibility(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->obsAdapter->copy('fixture/file.txt', 'fixture/copy.txt', new Config([
            'retain_visibility' => false,
        ]));
        $this->assertSame(Visibility::PRIVATE, $this->obsAdapter->visibility('fixture/copy.txt')->visibility());
    }

    public function testCreateDir(): void
    {
        $this->obsAdapter->createDirectory('fixture/path', new Config());
        $this->assertTrue($this->obsAdapter->directoryExists('fixture/path'));
        $this->assertSame([], iterator_to_array($this->obsAdapter->listContents('fixture/path', false)));
        $this->assertSame([], iterator_to_array($this->obsAdapter->listContents('fixture/path/', false)));
        $this->obsAdapter->write('fixture/path1/file.txt', 'test', new Config());
        $contents = iterator_to_array($this->obsAdapter->listContents('fixture/path1', false));
        $this->assertCount(1, $contents);
        $file = $contents[0];
        $this->assertSame('fixture/path1/file.txt', $file['path']);
        $this->obsAdapter->deleteDirectory('fixture/path');
        $this->assertFalse($this->obsAdapter->directoryExists('fixture/path'));
        $this->obsAdapter->deleteDirectory('fixture/path1');
        $this->assertFalse($this->obsAdapter->directoryExists('fixture/path1'));
    }

    public function testDirectoryExists(): void
    {
        $this->assertFalse($this->obsAdapter->directoryExists('fixture/exists-directory'));
        $this->obsAdapter->createDirectory('fixture/exists-directory', new Config());
        $this->assertTrue($this->obsAdapter->directoryExists('fixture/exists-directory'));
    }

    public function testSetVisibility(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->assertSame(Visibility::PRIVATE, $this->obsAdapter->visibility('fixture/file.txt')['visibility']);
        $this->obsAdapter->setVisibility('fixture/file.txt', Visibility::PUBLIC);
        $this->assertSame(Visibility::PUBLIC, $this->obsAdapter->visibility('fixture/file.txt')['visibility']);
    }

    public function testRename(): void
    {
        $this->obsAdapter->write('fixture/from.txt', 'write', new Config());
        $this->assertTrue($this->obsAdapter->fileExists('fixture/from.txt'));
        $this->assertFalse($this->obsAdapter->fileExists('fixture/to.txt'));
        $this->obsAdapter->move('fixture/from.txt', 'fixture/to.txt', new Config());
        $this->assertFalse($this->obsAdapter->fileExists('fixture/from.txt'));
        $this->assertSame('write', $this->obsAdapter->read('fixture/to.txt'));
        $this->obsAdapter->delete('fixture/to.txt');
    }

    public function testDeleteDir(): void
    {
        $this->obsAdapter->deleteDirectory('fixture');
        $this->assertFalse($this->obsAdapter->directoryExists('fixture'));
    }

    public function testWriteStream(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamForResource('write'), new Config());
        $this->assertSame('write', $this->obsAdapter->read('fixture/file.txt'));
    }

    /**
     * @return \Iterator<string[]>
     */
    public static function provideWriteStreamWithVisibilityCases(): \Iterator
    {
        yield [Visibility::PUBLIC];

        yield [Visibility::PRIVATE];
    }

    /**
     * @dataProvider provideWriteStreamWithVisibilityCases
     */
    public function testWriteStreamWithVisibility(string $visibility): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamForResource('write'), new Config([
            'visibility' => $visibility,
        ]));
        $this->assertSame($visibility, $this->obsAdapter->visibility('fixture/file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamForResource('write'), new Config([
            'Expires' => 20,
        ]));
        $this->assertSame('write', $this->obsAdapter->read('fixture/file.txt'));
    }

    public function testWriteStreamWithMimetype(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamForResource('write'), new Config([
            'ContentType' => 'image/png',
        ]));
        $this->assertSame('image/png', $this->obsAdapter->mimeType('fixture/file.txt')['mime_type']);
    }

    public function testDelete(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamForResource('test'), new Config());
        $this->assertTrue($this->obsAdapter->fileExists('fixture/file.txt'));
        $this->obsAdapter->delete('fixture/file.txt');
        $this->assertFalse($this->obsAdapter->fileExists('fixture/file.txt'));
    }

    public function testWrite(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->assertSame('write', $this->obsAdapter->read('fixture/file.txt'));
    }

    public function testRead(): void
    {
        $this->assertSame('read-test', $this->obsAdapter->read('fixture/read.txt'));
    }

    public function testReadStream(): void
    {
        $this->assertSame('read-test', stream_get_contents($this->obsAdapter->readStream('fixture/read.txt')));
    }

    public function testGetVisibility(): void
    {
        $this->assertSame(Visibility::PRIVATE, $this->obsAdapter->visibility('fixture/read.txt')->visibility());
    }

    public function testListContents(): void
    {
        $this->assertNotEmpty(iterator_to_array($this->obsAdapter->listContents('fixture', false)));
        $this->assertEmpty(iterator_to_array($this->obsAdapter->listContents('path1', false)));
        $this->obsAdapter->createDirectory('fixture/path/dir', new Config());
        $this->obsAdapter->write('fixture/path/dir/file.txt', 'test', new Config());

        /** @var \League\Flysystem\StorageAttributes[] $contents */
        $contents = iterator_to_array($this->obsAdapter->listContents('fixture/path', true));
        $this->assertContainsOnlyInstancesOf(StorageAttributes::class, $contents);
        $this->assertCount(2, $contents);

        /** @var \League\Flysystem\FileAttributes $file */
        /** @var \League\Flysystem\DirectoryAttributes $directory */
        [$file,$directory] = $contents[0]->isFile() ? [$contents[0], $contents[1]] : [$contents[1], $contents[0]];
        $this->assertInstanceOf(FileAttributes::class, $file);
        $this->assertSame('fixture/path/dir/file.txt', $file->path());
        $this->assertSame(4, $file->fileSize());

        $this->assertNull($file->mimeType());
        $this->assertNotNull($file->lastModified());
        $this->assertNull($file->visibility());
        $this->assertIsArray($file->extraMetadata());
        $this->assertInstanceOf(DirectoryAttributes::class, $directory);
        $this->assertSame('fixture/path/dir', $directory->path());
    }

    public function testGetSize(): void
    {
        $this->assertSame(9, $this->obsAdapter->fileSize('fixture/read.txt')->fileSize());
    }

    public function testGetTimestamp(): void
    {
        $this->assertGreaterThan(time() - 10, $this->obsAdapter->lastModified('fixture/read.txt')->lastModified());
    }

    public function testGetMimetype(): void
    {
        $this->assertSame('text/plain', $this->obsAdapter->mimeType('fixture/read.txt')->mimeType());
    }

    public function testHas(): void
    {
        $this->assertTrue($this->obsAdapter->fileExists('fixture/read.txt'));
    }

    public function testGetTemporaryUrl(): void
    {
        $this->assertSame(
            'read-test',
            file_get_contents($this->obsAdapter->getTemporaryUrl('fixture/read.txt', 10, []))
        );
    }

    public function testImage(): void
    {
        $contents = file_get_contents('https://avatars.githubusercontent.com/u/26657141');
        if ($contents === false) {
            $this->markTestSkipped('Require image contents');
        }

        $this->obsAdapter->write('fixture/image.png', $contents, new Config());

        /** @var array{int, int} $info */
        $info = getimagesize($this->obsAdapter->getTemporaryUrl('fixture/image.png', 10, [
            'x-image-process' => 'image/crop,w_200,h_100',
        ]));

        $this->assertSame(200, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    public function testForceMimetype(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'test', new Config([
            'mimetype' => 'image/png',
        ]));
        $this->assertSame('image/png', $this->obsAdapter->mimeType('fixture/file.txt')->mimeType());
        $this->obsAdapter->write('fixture/file2.txt', 'test', new Config([
            'ContentType' => 'image/png',
        ]));
        $this->assertSame('image/png', $this->obsAdapter->mimeType('fixture/file2.txt')->mimeType());
    }

    public function testMovingAFileWithVisibility(): void
    {
        $adapter = $this->obsAdapter;
        $adapter->write(
            'fixture/source.txt',
            'contents to be copied',
            new Config([
                Config::OPTION_VISIBILITY => Visibility::PUBLIC,
            ])
        );
        $adapter->move('fixture/source.txt', 'fixture/destination.txt', new Config([
            Config::OPTION_VISIBILITY => Visibility::PRIVATE,
        ]));
        $this->assertFalse(
            $adapter->fileExists('fixture/source.txt'),
            'After moving a file should no longer exist in the original location.'
        );
        $this->assertTrue(
            $adapter->fileExists('fixture/destination.txt'),
            'After moving, a file should be present at the new location.'
        );
        $this->assertSame(Visibility::PRIVATE, $adapter->visibility('fixture/destination.txt')->visibility());
        $this->assertSame('contents to be copied', $adapter->read('fixture/destination.txt'));
    }

    public function testCopyingAFileWithVisibility(): void
    {
        $adapter = $this->obsAdapter;
        $adapter->write(
            'fixture/source.txt',
            'contents to be copied',
            new Config([
                Config::OPTION_VISIBILITY => Visibility::PUBLIC,
            ])
        );

        $adapter->copy('fixture/source.txt', 'fixture/destination.txt', new Config([
            Config::OPTION_VISIBILITY => Visibility::PRIVATE,
        ]));

        $this->assertTrue($adapter->fileExists('fixture/source.txt'));
        $this->assertTrue($adapter->fileExists('fixture/destination.txt'));
        $this->assertSame(Visibility::PRIVATE, $adapter->visibility('fixture/destination.txt')->visibility());
        $this->assertSame('contents to be copied', $adapter->read('fixture/destination.txt'));
    }
}
