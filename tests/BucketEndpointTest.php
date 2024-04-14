<?php

declare(strict_types=1);

namespace Zing\Flysystem\Obs\Tests;

class BucketEndpointTest extends ValidAdapterTest
{
    protected function getEndpoint(): string
    {
        return (string) getenv('OBS_BUCKET_ENDPOINT') ?: sprintf('%s.%s', $this->getBucket(), parent::getEndpoint());
    }

    protected function isBucketEndpoint(): bool
    {
        return true;
    }
}
