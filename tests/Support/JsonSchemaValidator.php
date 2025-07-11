<?php

namespace Tests\Support;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriRetriever;

trait JsonSchemaValidator
{
    /**
     * Assert that a JSON response matches the given schema.
     */
    protected function assertJsonMatchesSchema(array $jsonData, string $schemaPath): void
    {
        // Create a URI retriever for proper reference resolution
        $retriever = new UriRetriever();
        
        // Convert file path to file:// URI for proper resolution
        $schemaUri = 'file://' . realpath($schemaPath);
        
        // Load the schema with proper URI context for reference resolution
        $schema = $retriever->retrieve($schemaUri);
        
        // Convert PHP array to object for validation
        $jsonObject = json_decode(json_encode($jsonData));
        
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