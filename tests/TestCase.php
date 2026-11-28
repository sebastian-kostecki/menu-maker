<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\PassportKeys;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * The `api` guard builds its resource server from the Passport keys on every request, so each test gets
     * a throwaway pair instead of key files or secrets in the repository.
     */
    protected function setUp(): void
    {
        parent::setUp();

        PassportKeys::install();
    }
}
