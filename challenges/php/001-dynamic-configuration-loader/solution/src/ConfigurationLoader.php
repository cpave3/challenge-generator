<?php

class ConfigurationLoader {
    public function load(string $environment): array {
        $filename = __DIR__ . "/../config/config.$environment.json";
        if (!file_exists($filename)) {
            throw new Exception("Configuration file not found.");
        }

        $json = file_get_contents($filename);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Malformed JSON in configuration file.");
        }

        return $data;
    }
}
