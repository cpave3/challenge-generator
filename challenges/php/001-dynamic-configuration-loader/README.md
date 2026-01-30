# Dynamic Configuration Loader

## Difficulty
Level 2 - Mid

## Skills
- File Handling
- OOP Design
- JSON Parsing
- Error Handling

## Estimated Time
1-2 hours

## Context
In many applications, configurations are stored in files and need to be dynamically loaded based on different environments (development, testing, production). This challenge will test your ability to create a robust configuration loader that can handle different environments and gracefully manage errors.

## Requirements
1. Implement a `ConfigurationLoader` class that loads configuration from JSON files.
2. The class should handle different environments by loading the appropriate configuration file (e.g., `config.development.json`, `config.production.json`).
3. Implement error handling to manage scenarios where a file is missing or the JSON is malformed.
4. Use object-oriented principles to design the class structure.

## Definition of Done
- A `ConfigurationLoader` class is implemented with methods to load configuration based on an environment variable.
- The class handles file not found and JSON parsing errors gracefully.
- The solution is fully tested with PHPUnit.

## Acceptance Tests
1. **Test Configuration Loading**: Ensure the loader correctly loads configuration files based on the environment.
2. **Test Error Handling**: The loader should throw appropriate exceptions for missing files or malformed JSON.
3. **Test Object-Oriented Design**: Verify the class structure follows OOP principles.

## Hints
- Use PHP's built-in JSON functions for parsing.
- Consider using exceptions for error handling.
- Abstract file reading logic to make it testable.

## Extensions
- Enhance the loader to support multiple file formats (e.g., YAML, XML).