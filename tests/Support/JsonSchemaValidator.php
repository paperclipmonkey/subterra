<?php

namespace Tests\Support;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Uri\UriRetriever;

trait JsonSchemaValidator
{
    /**
     * Assert that a JSON response matches the given schema.
     */
    protected function assertJsonMatchesSchema(array $jsonData, string $schemaPath): void
    {
        // Convert PHP array to object for validation
        $jsonObject = json_decode(json_encode($jsonData));
        
        // Load schema with proper URI resolution
        $schema = $this->loadSchemaWithUriResolver($schemaPath);
        
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
     * Load schema with proper URI resolution for references.
     */
    private function loadSchemaWithUriResolver(string $schemaPath): object
    {
        $retriever = new UriRetriever();
        
        // Convert to absolute file URI for proper reference resolution
        $absolutePath = realpath($schemaPath);
        if (!$absolutePath) {
            throw new \InvalidArgumentException("Schema file not found: $schemaPath");
        }
        
        $schemaUri = 'file://' . $absolutePath;
        
        // Load the schema using UriRetriever which handles references properly
        $schema = $retriever->retrieve($schemaUri);
        
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