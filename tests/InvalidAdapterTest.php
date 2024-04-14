<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Obs\ObsClient;
use Obs\ObsException;
use Zing\Flysystem\Obs\ObsAdapter;
use Zing\Flysystem\Obs\Plugins\FileUrl;
use Zing\Flysystem\Obs\Plugins\TemporaryUrl;

class InvalidAdapterTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const CONFIG = [
        'key' => 'aW52YWxpZC1rZXk=',
        'secret' => 'aW52YWxpZC1zZWNyZXQ=',
        'bucket' => 'test',
        'endpoint' => 'obs.cn-east-3.myhuaweicloud.com',
        'path_style' => '',
        'region' => '',
    ];

    /**
     * @var \Zing\Flysystem\Obs\ObsAdapter
     */
    private $obsAdapter;

    /**
     * @var \Obs\ObsClient
     */
    private $obsClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->obsClient = new ObsClient(self::CONFIG);
        $this->obsAdapter = new ObsAdapter($this->obsClient, self::CONFIG['endpoint'], self::CONFIG['bucket']);
    }

    public function testUpdate(): void
    {
        $this->assertFalse($this->obsAdapter->update('file.txt', 'test', new Config()));
    }

    public function testUpdateStream(): void
    {
        $this->assertFalse(
            $this->obsAdapter->updateStream('file.txt', $this->streamFor('test')->detach(), new Config())
        );
    }

    public function testCopy(): void
    {
        $this->assertFalse($this->obsAdapter->copy('file.txt', 'copy.txt'));
    }

    public function testCreateDir(): void
    {
        $this->assertFalse($this->obsAdapter->createDir('path', new Config()));
    }

    public function testSetVisibility(): void
    {
        $this->assertFalse($this->obsAdapter->setVisibility('file.txt', AdapterInterface::VISIBILITY_PUBLIC));
    }

    public function testRename(): void
    {
        $this->assertFalse($this->obsAdapter->rename('from.txt', 'to.txt'));
    }

    public function testDeleteDir(): void
    {
        $this->expectException(ObsException::class);
        $this->assertFalse($this->obsAdapter->deleteDir('path'));
    }

    public function testWriteStream(): void
    {
        $this->assertFalse(
            $this->obsAdapter->writeStream('file.txt', $this->streamFor('test')->detach(), new Config())
        );
    }

    public function testDelete(): void
    {
        $this->assertFalse($this->obsAdapter->delete('file.txt'));
    }

    public function testWrite(): void
    {
        $this->assertFalse($this->obsAdapter->write('file.txt', 'test', new Config()));
    }

    public function testRead(): void
    {
        $this->assertFalse($this->obsAdapter->read('file.txt'));
    }

    public function testReadStream(): void
    {
        $this->assertFalse($this->obsAdapter->readStream('file.txt'));
    }

    public function testGetVisibility(): void
    {
        $this->assertFalse($this->obsAdapter->getVisibility('file.txt'));
    }

    public function testGetMetadata(): void
    {
        $this->assertFalse($this->obsAdapter->getMetadata('file.txt'));
    }

    public function testListContents(): void
    {
        $this->expectException(ObsException::class);
        $this->assertEmpty($this->obsAdapter->listContents());
    }

    public function testGetSize(): void
    {
        $this->assertFalse($this->obsAdapter->getSize('file.txt'));
    }

    public function testGetTimestamp(): void
    {
        $this->assertFalse($this->obsAdapter->getTimestamp('file.txt'));
    }

    public function testGetMimetype(): void
    {
        $this->assertFalse($this->obsAdapter->getMimetype('file.txt'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->obsAdapter->has('file.txt'));
    }

    public function testGetUrl(): void
    {
        $this->assertSame(
            'https://test.obs.cn-east-3.myhuaweicloud.com/file.txt',
            $this->obsAdapter->getUrl('file.txt')
        );
    }

    public function testSignUrl(): void
    {
        $this->assertFalse($this->obsAdapter->signUrl('file.txt', 10, [], null));
    }

    public function testGetTemporaryUrl(): void
    {
        $this->assertFalse($this->obsAdapter->getTemporaryUrl('file.txt', 10, [], null));
    }

    public function testSetBucket(): void
    {
        $this->assertSame('test', $this->obsAdapter->getBucket());
        $this->obsAdapter->setBucket('bucket');
        $this->assertSame('bucket', $this->obsAdapter->getBucket());
    }

    public function testGetClient(): void
    {
        $this->assertInstanceOf(ObsClient::class, $this->obsAdapter->getClient());
    }

    public function testGetUrlWithUrl(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, '', '', '', [
            'url' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        $this->assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetUrlWithBucketEndpoint(): void
    {
        $client = \Mockery::mock(ObsClient::class);
        $obsAdapter = new ObsAdapter($client, 'https://oss.cdn.com', '', '', [
            'bucket_endpoint' => true,
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new FileUrl());
        $this->assertSame('https://oss.cdn.com/test', $filesystem->getUrl('test'));
    }

    public function testGetTemporaryUrlWithUrl(): void
    {
        $obsAdapter = new ObsAdapter($this->obsClient, 'https://oss.cdn.com', '', '', [
            'temporary_url' => 'https://oss.cdn.com',
        ]);
        $filesystem = new Filesystem($obsAdapter);
        $filesystem->addPlugin(new TemporaryUrl());
        $this->assertStringStartsWith('https://oss.cdn.com/test', (string) $filesystem->getTemporaryUrl('test', 10));
    }
}
