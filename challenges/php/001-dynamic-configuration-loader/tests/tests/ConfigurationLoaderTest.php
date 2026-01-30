<?php

use PHPUnit\Framework\TestCase;

class ConfigurationLoaderTest extends TestCase {
    public function testLoadConfiguration() {
        $loader = new ConfigurationLoader();
        $config = $loader->load('development');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('app_name', $config);
    }

    public function testFileNotFound() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configuration file not found.');

        $loader = new ConfigurationLoader();
        $loader->load('missing');
    }

    public function testMalformedJson() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Malformed JSON in configuration file.');

        // Simulate malformed JSON scenario
        file_put_contents(__DIR__ . '/../config/config.malformed.json', '{invalid json}');

        $loader = new ConfigurationLoader();
        $loader->load('malformed');
    }
}
