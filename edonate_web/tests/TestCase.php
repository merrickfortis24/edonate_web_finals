<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function privacyAcknowledgment(): array
    {
        return ['privacy_version' => config('privacy.version'), 'privacy_acknowledged' => '1', 'purpose_accepted' => '1'];
    }
}
