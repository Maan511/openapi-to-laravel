<?php


beforeEach(function () {
    $this->command = new \Maan511\OpenapiToLaravel\Console\GenerateFormRequestsCommand();

    // Create mock application and set up command
    $this->application = Mockery::mock(\Illuminate\Console\Application::class);
    $this->application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(\Symfony\Component\Console\Helper\HelperSet::class));
    
    $this->command->setLaravel(Mockery::mock(\Illuminate\Contracts\Foundation\Application::class));
    $this->command->setApplication($this->application);
});

afterEach(function () {
    Mockery::close();
});

describe('GenerateFormRequestsCommand', function () {
    describe('command signature', function () {
        it('should have correct signature', function () {
            $signature = $this->command->getName();
            expect($signature)->toBe('openapi:generate');
        });

        it('should have correct description', function () {
            $description = $this->command->getDescription();
            expect($description)->toBe('Generate Laravel FormRequest classes from OpenAPI specification');
        });
    });

    describe('validateInputs', function () {
        it('should validate existing readable spec file', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, json_encode([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => []
            ]));

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('validateInputs');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->command,
                $tempFile,
                sys_get_temp_dir(),
                'App\\Http\\Requests'
            );

            expect($result['success'])->toBeTrue();

            unlink($tempFile);
        });

        it('should reject non-existent spec file', function () {
            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('validateInputs');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->command,
                '/non/existent/file.json',
                sys_get_temp_dir(),
                'App\\Http\\Requests'
            );

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('not found');
        });

        it('should reject invalid namespace format', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, '{}');

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('validateInputs');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->command,
                $tempFile,
                sys_get_temp_dir(),
                'invalid-namespace'
            );

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('Invalid namespace format');

            unlink($tempFile);
        });

        it('should create output directory if it does not exist', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, '{}');

            $tempDir = sys_get_temp_dir() . '/test_output_' . uniqid();

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('validateInputs');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->command,
                $tempFile,
                $tempDir,
                'App\\Http\\Requests'
            );

            expect($result['success'])->toBeTrue();
            expect(is_dir($tempDir))->toBeTrue();

            // Cleanup
            unlink($tempFile);
            rmdir($tempDir);
        });

        it('should reject non-writable output directory', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, '{}');

            // Create a directory with no write permissions
            $tempDir = sys_get_temp_dir() . '/readonly_' . uniqid();
            mkdir($tempDir, 0444);

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('validateInputs');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->command,
                $tempFile,
                $tempDir,
                'App\\Http\\Requests'
            );

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('not writable');

            // Cleanup
            unlink($tempFile);
            chmod($tempDir, 0755);
            rmdir($tempDir);
        });
    });

    describe('handleDryRun', function () {
        it('should display dry run results without creating files', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequests = [
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'TestRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: '/app/Http/Requests/TestRequest.php',
                    validationRules: ['name' => 'required|string'],
                    sourceSchema: $schema
                )
            ];

            $generator = new \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator(
                new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper(),
                new \Maan511\OpenapiToLaravel\Generator\TemplateEngine()
            );

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('handleDryRun');
            $method->setAccessible(true);

            // Mock the console output
            $outputMock = Mockery::mock(\Illuminate\Console\OutputStyle::class);
            $outputMock->shouldReceive('writeln')->andReturn(null);
            $outputMock->shouldReceive('write')->andReturn(null);
            $outputMock->shouldReceive('table')->andReturn(null);
            $outputMock->shouldReceive('info')->andReturn(null);
            $formatterMock = Mockery::mock(\Symfony\Component\Console\Formatter\OutputFormatterInterface::class);
            $formatterMock->shouldReceive('isDecorated')->andReturn(false);
            $formatterMock->shouldReceive('setDecorated')->andReturn(null);
            $formatterMock->shouldReceive('format')->andReturn('');
            $outputMock->shouldReceive('getFormatter')->andReturn($formatterMock);

            $this->command->setOutput($outputMock);

            $result = $method->invoke($this->command, $formRequests, $generator);

            expect($result)->toBe(0);
        });
    });

    describe('displayResults', function () {
        it('should display generation results correctly', function () {
            $results = [
                'summary' => [
                    'total' => 2,
                    'success' => 1,
                    'skipped' => 1,
                    'failed' => 0
                ],
                'results' => [
                    [
                        'success' => true,
                        'className' => 'CreateUserRequest',
                        'message' => 'Generated successfully'
                    ],
                    [
                        'success' => false,
                        'className' => 'UpdateUserRequest',
                        'message' => 'File already exists'
                    ]
                ]
            ];

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('displayResults');
            $method->setAccessible(true);

            // Mock the console output
            $outputMock = Mockery::mock(\Illuminate\Console\OutputStyle::class);
            $outputMock->shouldReceive('writeln')->andReturn(null);
            $outputMock->shouldReceive('write')->andReturn(null);

            $this->command->setOutput($outputMock);

            // Should not throw any exceptions
            expect(fn() => $method->invoke($this->command, $results, true))->not->toThrow(\Exception::class);
        });
    });

    describe('displayStats', function () {
        it('should display generation statistics correctly', function () {
            $stats = [
                'totalClasses' => 5,
                'totalRules' => 15,
                'averageComplexity' => 8.5,
                'estimatedTotalSize' => 25600,
                'namespaces' => ['App\\Http\\Requests'],
                'mostComplex' => [
                    'className' => 'ComplexRequest',
                    'complexity' => 25
                ]
            ];

            $reflection = new ReflectionClass($this->command);
            $method = $reflection->getMethod('displayStats');
            $method->setAccessible(true);

            // Mock the console output
            $outputMock = Mockery::mock(\Illuminate\Console\OutputStyle::class);
            $outputMock->shouldReceive('writeln')->andReturn(null);
            $outputMock->shouldReceive('write')->andReturn(null);

            $this->command->setOutput($outputMock);

            // Should not throw any exceptions
            expect(fn() => $method->invoke($this->command, $stats))->not->toThrow(\Exception::class);
        });
    });

    describe('handle method flow', function () {
        it('should handle valid OpenAPI specification file', function () {
            // Create a valid OpenAPI spec file
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            $validSpec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/users' => [
                        'post' => [
                            'operationId' => 'createUser',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'email' => ['type' => 'string', 'format' => 'email']
                                            ],
                                            'required' => ['name', 'email']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            file_put_contents($tempFile, json_encode($validSpec));

            $tempDir = sys_get_temp_dir() . '/test_output_' . uniqid();

            // Mock console input/output
            $inputMock = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
            $outputMock = Mockery::mock(\Illuminate\Console\OutputStyle::class);

            $inputMock->shouldReceive('getArgument')->with('spec')->andReturn($tempFile);
            $inputMock->shouldReceive('getOption')->with('output')->andReturn($tempDir);
            $inputMock->shouldReceive('getOption')->with('namespace')->andReturn('App\\Http\\Requests');
            $inputMock->shouldReceive('getOption')->with('force')->andReturn(false);
            $inputMock->shouldReceive('getOption')->with('dry-run')->andReturn(false);
            $inputMock->shouldReceive('getOption')->with('verbose')->andReturn(false);

            $outputMock->shouldReceive('writeln')->andReturn(null);
            $outputMock->shouldReceive('write')->andReturn(null);

            $this->command->setInput($inputMock);
            $this->command->setOutput($outputMock);

            $exitCode = $this->command->handle();

            expect($exitCode)->toBe(0);

            // Cleanup
            unlink($tempFile);
        });

        it('should return error code for invalid specification', function () {
            // Create an invalid spec file
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, 'invalid json');

            // Mock console input/output
            $inputMock = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
            $outputMock = Mockery::mock(\Illuminate\Console\OutputStyle::class);

            $inputMock->shouldReceive('getArgument')->with('spec')->andReturn($tempFile);
            $inputMock->shouldReceive('getOption')->with('output')->andReturn(sys_get_temp_dir());
            $inputMock->shouldReceive('getOption')->with('namespace')->andReturn('App\\Http\\Requests');
            $inputMock->shouldReceive('getOption')->with('force')->andReturn(false);
            $inputMock->shouldReceive('getOption')->with('dry-run')->andReturn(false);
            $inputMock->shouldReceive('getOption')->with('verbose')->andReturn(false);

            $outputMock->shouldReceive('writeln')->andReturn(null);
            $outputMock->shouldReceive('write')->andReturn(null);

            $this->command->setInput($inputMock);
            $this->command->setOutput($outputMock);

            $exitCode = $this->command->handle();

            expect($exitCode)->toBe(1);

            // Cleanup
            unlink($tempFile);
        });
    });

    describe('error handling', function () {
        it('should handle exceptions gracefully', function () {
            // Mock console input/output to trigger an exception
            $inputMock = Mockery::mock(\Symfony\Component\Console\Input\InputInterface::class);
            $outputMock = Mockery::mock(\Illuminate\Console\OutputStyle::class);

            $inputMock->shouldReceive('getArgument')->with('spec')->andReturn('/non/existent/file.json');
            $inputMock->shouldReceive('getOption')->with('output')->andReturn(sys_get_temp_dir());
            $inputMock->shouldReceive('getOption')->with('namespace')->andReturn('App\\Http\\Requests');
            $inputMock->shouldReceive('getOption')->with('force')->andReturn(false);
            $inputMock->shouldReceive('getOption')->with('dry-run')->andReturn(false);
            $inputMock->shouldReceive('getOption')->with('verbose')->andReturn(false);

            $outputMock->shouldReceive('writeln')->andReturn(null);
            $outputMock->shouldReceive('write')->andReturn(null);

            $this->command->setInput($inputMock);
            $this->command->setOutput($outputMock);

            $exitCode = $this->command->handle();

            expect($exitCode)->toBe(1);
        });
    });
});