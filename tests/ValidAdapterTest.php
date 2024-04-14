<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

class ValidAdapterTest extends TestCase
{
    /**
     * @var \Zing\Flysystem\Obs\ObsAdapter
     */
    private $obsAdapter;

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
            'path_style' => '',
            'region' => '',
        ];

        $this->obsAdapter = new ObsAdapter(new ObsClient($config), $this->getEndpoint(), $this->getBucket());
        $this->obsAdapter->write('fixture/read.txt', 'read-test', new Config());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->obsAdapter->deleteDir('fixture');
    }

    public function testUpdate(): void
    {
        $this->obsAdapter->update('fixture/file.txt', 'update', new Config());
        $this->assertSame('update', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testUpdateStream(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->obsAdapter->updateStream('fixture/file.txt', $this->streamFor('update')->detach(), new Config());
        $this->assertSame('update', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testCopy(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->obsAdapter->copy('fixture/file.txt', 'fixture/copy.txt');
        $this->assertSame('write', $this->obsAdapter->read('fixture/copy.txt')['contents']);
    }

    public function testCreateDir(): void
    {
        $this->obsAdapter->createDir('fixture/path', new Config());
        $this->assertFalse($this->obsAdapter->has('fixture/path'));
    }

    public function testSetVisibility(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->obsAdapter->getVisibility('fixture/file.txt')['visibility']
        );
        $this->obsAdapter->setVisibility('fixture/file.txt', AdapterInterface::VISIBILITY_PUBLIC);
        $this->assertSame(
            AdapterInterface::VISIBILITY_PUBLIC,
            $this->obsAdapter->getVisibility('fixture/file.txt')['visibility']
        );
    }

    public function testRename(): void
    {
        $this->obsAdapter->write('fixture/from.txt', 'write', new Config());
        $this->assertTrue((bool) $this->obsAdapter->has('fixture/from.txt'));
        $this->assertFalse((bool) $this->obsAdapter->has('fixture/to.txt'));
        $this->obsAdapter->rename('fixture/from.txt', 'fixture/to.txt');
        $this->assertFalse((bool) $this->obsAdapter->has('fixture/from.txt'));
        $this->assertSame('write', $this->obsAdapter->read('fixture/to.txt')['contents']);
        $this->obsAdapter->delete('fixture/to.txt');
    }

    public function testDeleteDir(): void
    {
        $this->assertTrue($this->obsAdapter->deleteDir('fixture'));
        $this->assertEmpty($this->obsAdapter->listContents('fixture'));
        $this->assertSame([], $this->obsAdapter->listContents('fixture/path/'));
        $this->obsAdapter->write('fixture/path1/file.txt', 'test', new Config());
        $contents = $this->obsAdapter->listContents('fixture/path1');
        $this->assertCount(1, $contents);
        $file = $contents[0];
        $this->assertSame('fixture/path1/file.txt', $file['path']);
    }

    public function testWriteStream(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config());
        $this->assertSame('write', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    /**
     * @return \Iterator<string[]>
     */
    public static function provideWriteStreamWithVisibilityCases(): \Iterator
    {
        yield [AdapterInterface::VISIBILITY_PUBLIC];

        yield [AdapterInterface::VISIBILITY_PRIVATE];
    }

    /**
     * @dataProvider provideWriteStreamWithVisibilityCases
     */
    public function testWriteStreamWithVisibility(string $visibility): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'visibility' => $visibility,
        ]));
        $this->assertSame($visibility, $this->obsAdapter->getVisibility('fixture/file.txt')['visibility']);
    }

    public function testWriteStreamWithExpires(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'Expires' => 20,
        ]));
        $this->assertSame('write', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testWriteStreamWithMimetype(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('write')->detach(), new Config([
            'mimetype' => 'image/png',
        ]));
        $this->assertSame('image/png', $this->obsAdapter->getMimetype('fixture/file.txt')['mimetype']);
    }

    public function testDelete(): void
    {
        $this->obsAdapter->writeStream('fixture/file.txt', $this->streamFor('test')->detach(), new Config());
        $this->assertTrue((bool) $this->obsAdapter->has('fixture/file.txt'));
        $this->obsAdapter->delete('fixture/file.txt');
        $this->assertFalse((bool) $this->obsAdapter->has('fixture/file.txt'));
    }

    public function testWrite(): void
    {
        $this->obsAdapter->write('fixture/file.txt', 'write', new Config());
        $this->assertSame('write', $this->obsAdapter->read('fixture/file.txt')['contents']);
    }

    public function testRead(): void
    {
        $this->assertSame('read-test', $this->obsAdapter->read('fixture/read.txt')['contents']);
    }

    public function testReadStream(): void
    {
        $this->assertSame(
            'read-test',
            stream_get_contents($this->obsAdapter->readStream('fixture/read.txt')['stream'])
        );
    }

    public function testGetVisibility(): void
    {
        $this->assertSame(
            AdapterInterface::VISIBILITY_PRIVATE,
            $this->obsAdapter->getVisibility('fixture/read.txt')['visibility']
        );
    }

    public function testGetMetadata(): void
    {
        $this->assertIsArray($this->obsAdapter->getMetadata('fixture/read.txt'));
    }

    public function testListContents(): void
    {
        $this->assertNotEmpty($this->obsAdapter->listContents('fixture'));
        $this->assertEmpty($this->obsAdapter->listContents('path1'));
        $this->obsAdapter->write('fixture/path/file.txt', 'test', new Config());
        $this->obsAdapter->listContents('a', true);
    }

    public function testGetSize(): void
    {
        $this->assertSame(9, $this->obsAdapter->getSize('fixture/read.txt')['size']);
    }

    public function testGetTimestamp(): void
    {
        $this->assertGreaterThan(time() - 10, $this->obsAdapter->getTimestamp('fixture/read.txt')['timestamp']);
    }

    public function testGetMimetype(): void
    {
        $this->assertSame('text/plain', $this->obsAdapter->getMimetype('fixture/read.txt')['mimetype']);
    }

    public function testHas(): void
    {
        $this->assertTrue((bool) $this->obsAdapter->has('fixture/read.txt'));
    }

    public function testSignUrl(): void
    {
        $this->assertSame('read-test', file_get_contents($this->obsAdapter->signUrl('fixture/read.txt', 10, [])));
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
        $this->obsAdapter->write(
            'fixture/image.png',
            file_get_contents('https://avatars.githubusercontent.com/u/26657141'),
            new Config()
        );
        $info = getimagesize($this->obsAdapter->signUrl('fixture/image.png', 10, [
            'x-image-process' => 'image/crop,w_200,h_100',
        ]));
        $this->assertSame(200, $info[0]);
        $this->assertSame(100, $info[1]);
    }
}
