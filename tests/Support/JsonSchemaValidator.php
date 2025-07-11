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
        $schema = json_decode(file_get_contents($schemaPath));
        
        $validator = new Validator();
        $validator->validate($jsonData, $schema, Constraint::CHECK_MODE_COERCE_TYPES);
        
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