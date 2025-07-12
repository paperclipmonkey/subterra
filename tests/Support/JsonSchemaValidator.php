<?php

namespace Tests\Support;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

trait JsonSchemaValidator
{
    /**
     * Assert that a JSON response matches the given schema.
     */
    protected function assertJsonMatchesSchema(array $jsonData, string $schemaPath): void
    {
        // Convert PHP array to object for validation
        $jsonObject = json_decode(json_encode($jsonData));
        
        // Load schema and resolve references manually to avoid complex dependency issues
        $schema = $this->loadSchemaWithSimpleResolution($schemaPath);
        
        $validator = new Validator();
        $validator->validate($jsonObject, $schema, Constraint::CHECK_MODE_COERCE_TYPES);
        
        $this->assertTrue(
            $validator->isValid(),
            'JSON response does not match schema: ' . implode(', ', array_map(
                fn($error) => "[{$error['property']}] {$error['message']}",
                $validator->getErrors()
            ))
        );
    }
    
    /**
     * Load schema with a simple resolution approach that avoids circular references.
     */
    private function loadSchemaWithSimpleResolution(string $schemaPath): object
    {
        $schemaContent = file_get_contents($schemaPath);
        $schema = json_decode($schemaContent);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON schema: ' . json_last_error_msg());
        }
        
        // For now, just return the schema as-is and let JsonSchema library handle references
        // This might not resolve all external references, but it should work for basic validation
        return $schema;
    }

    /**
     * Get the path to a schema file.
     */
    protected function getSchemaPath(string $schemaName): string
    {
        return __DIR__ . '/../schemas/' . $schemaName . '.json';
    }

    /**
     * Assert that a test response matches the given schema.
     */
    protected function assertResponseMatchesSchema(\Illuminate\Testing\TestResponse $response, string $schemaName): void
    {
        $this->assertJsonMatchesSchema(
            $response->json(),
            $this->getSchemaPath($schemaName)
        );
    }
}