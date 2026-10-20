<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $temporaryStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->temporaryStoragePath = sys_get_temp_dir().'/freeksrecords-tests-'.getmypid();

        File::ensureDirectoryExists($this->temporaryStoragePath);

        $this->app->useStoragePath($this->temporaryStoragePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStoragePath);

        parent::tearDown();
    }
}
