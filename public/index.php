<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

// Maxim OTLP endpoint configuration
$maximApiKey = getenv('MAXIM_API_KEY') ?: 'your_api_key_here';
$maximRepoId = getenv('MAXIM_REPO_ID') ?: 'your_repository_id_here';
$maximEndpoint = 'https://api.getmaxim.ai/v1/otel';

/**
 * Generate a random 16-byte hex string for trace/span IDs
 * OTLP requires 32 hex characters (lowercase) for trace IDs
 */
function generateTraceId(): string
{
    return strtolower(bin2hex(random_bytes(16)));
}

/**
 * Generate a random 8-byte hex string for span IDs
 * OTLP requires 16 hex characters (lowercase) for span IDs
 */
function generateSpanId(): string
{
    return strtolower(bin2hex(random_bytes(8)));
}

/**
 * Convert microseconds to nanoseconds
 */
function microtimeToNanoseconds(): int
{
    return (int)(microtime(true) * 1_000_000_000);
}

/**
 * Send OTLP trace data to Maxim
 */
function sendToMaxim(array $otlpData, string $endpoint, string $apiKey, string $repoId): bool
{
    $ch = curl_init($endpoint);
    
    $jsonData = json_encode($otlpData);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-maxim-api-key: ' . $apiKey,
            'x-maxim-repo-id: ' . $repoId,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Maxim OTLP error: " . $error);
        return false;
    }
    
    if ($httpCode !== 200) {
        error_log("Maxim OTLP HTTP error: " . $httpCode . " - " . $response);
        return false;
    }
    
    return true;
}

$app = AppFactory::create();

// GET endpoint that simulates an LLM call and logs to Maxim
$app->get('/query', function ($request, $response, $args) use ($maximEndpoint, $maximApiKey, $maximRepoId) {
    $traceId = generateTraceId();
    $rootSpanId = generateSpanId();
    $llmSpanId = generateSpanId();
    
    $startTime = microtimeToNanoseconds();
    
    // Simulate LLM processing time
    usleep(500000); // 0.5 seconds
    
    $endTime = microtimeToNanoseconds();
    
    // Calculate costs (Claude 3.5 Sonnet on Bedrock pricing)
    // Input: $3.00 per 1M tokens, Output: $15.00 per 1M tokens
    $inputCost = (3000 / 1000000) * 3.00;  // $0.009
    $outputCost = (300 / 1000000) * 15.00;  // $0.0045
    $totalCost = $inputCost + $outputCost;  // $0.0135
    
    // Build OTLP JSON payload following OpenTelemetry specification
    // Using OpenInference semantic conventions for LLM spans
    $otlpData = [
        'resourceSpans' => [
            [
                'resource' => [
                    'attributes' => [
                        [
                            'key' => 'service.name',
                            'value' => ['stringValue' => 'php-maxim-ai-logging']
                        ],
                        [
                            'key' => 'service.version',
                            'value' => ['stringValue' => '1.0.0']
                        ],
                    ]
                ],
                'scopeSpans' => [
                    [
                        'scope' => [
                            'name' => 'php-maxim-ai-logging',
                            'version' => '1.0.0'
                        ],
                        'spans' => [
                            // Root span for the query
                            [
                                'traceId' => $traceId,
                                'spanId' => $rootSpanId,
                                'name' => 'query',
                                'kind' => 'SPAN_KIND_SERVER',
                                'startTimeUnixNano' => (string)$startTime,
                                'endTimeUnixNano' => (string)$endTime,
                                'status' => [
                                    'code' => 'STATUS_CODE_OK'
                                ],
                                'attributes' => []
                            ],
                            // LLM span with OpenInference semantic conventions
                            [
                                'traceId' => $traceId,
                                'spanId' => $llmSpanId,
                                'parentSpanId' => $rootSpanId,
                                'name' => 'llm.call',
                                'kind' => 'SPAN_KIND_CLIENT',
                                'startTimeUnixNano' => (string)$startTime,
                                'endTimeUnixNano' => (string)$endTime,
                                'status' => [
                                    'code' => 'STATUS_CODE_OK'
                                ],
                                'attributes' => [
                                    // OpenInference required attribute
                                    [
                                        'key' => 'openinference.span.kind',
                                        'value' => ['stringValue' => 'LLM']
                                    ],
                                    // LLM Model Information
                                    [
                                        'key' => 'llm.model_name',
                                        'value' => ['stringValue' => 'anthropic.claude-3-5-sonnet-20241022-v2:0']
                                    ],
                                    [
                                        'key' => 'llm.provider',
                                        'value' => ['stringValue' => 'bedrock']
                                    ],
                                    [
                                        'key' => 'llm.vendor',
                                        'value' => ['stringValue' => 'aws']
                                    ],
                                    // Input Messages (OpenInference indexed format)
                                    [
                                        'key' => 'llm.input_messages.0.message.role',
                                        'value' => ['stringValue' => 'user']
                                    ],
                                    [
                                        'key' => 'llm.input_messages.0.message.content',
                                        'value' => ['stringValue' => 'What is the capital of France?']
                                    ],
                                    // Token Counts
                                    [
                                        'key' => 'llm.token_count.prompt',
                                        'value' => ['intValue' => '3000']
                                    ],
                                    [
                                        'key' => 'llm.token_count.completion',
                                        'value' => ['intValue' => '300']
                                    ],
                                    [
                                        'key' => 'llm.token_count.total',
                                        'value' => ['intValue' => '3300']
                                    ],
                                    // Invocation Parameters
                                    [
                                        'key' => 'llm.invocation_parameters.temperature',
                                        'value' => ['doubleValue' => 0.7]
                                    ],
                                    [
                                        'key' => 'llm.invocation_parameters.max_tokens',
                                        'value' => ['intValue' => '1000']
                                    ],
                                    [
                                        'key' => 'llm.invocation_parameters.top_p',
                                        'value' => ['doubleValue' => 0.9]
                                    ],
                                    // Generative AI Semantic Conventions
                                    [
                                        'key' => 'gen_ai.system',
                                        'value' => ['stringValue' => 'aws.bedrock']
                                    ],
                                    [
                                        'key' => 'gen_ai.provider.name',
                                        'value' => ['stringValue' => 'aws.bedrock']
                                    ],
                                    [
                                        'key' => 'gen_ai.operation.name',
                                        'value' => ['stringValue' => 'chat']
                                    ],
                                    [
                                        'key' => 'gen_ai.request.type',
                                        'value' => ['stringValue' => 'chat']
                                    ],
                                    [
                                        'key' => 'gen_ai.request.model',
                                        'value' => ['stringValue' => 'anthropic.claude-3-5-sonnet-20241022-v2:0']
                                    ],
                                    [
                                        'key' => 'gen_ai.request.max_tokens',
                                        'value' => ['intValue' => '1000']
                                    ],
                                    [
                                        'key' => 'gen_ai.request.temperature',
                                        'value' => ['doubleValue' => 0.7]
                                    ],
                                    [
                                        'key' => 'gen_ai.request.top_p',
                                        'value' => ['doubleValue' => 0.9]
                                    ],
                                    [
                                        'key' => 'gen_ai.response.type',
                                        'value' => ['stringValue' => 'chat']
                                    ],
                                    [
                                        'key' => 'gen_ai.response.model',
                                        'value' => ['stringValue' => 'anthropic.claude-3-5-sonnet-20241022-v2:0']
                                    ],
                                    [
                                        'key' => 'gen_ai.response.id',
                                        'value' => ['stringValue' => 'bedrock-' . uniqid()]
                                    ],
                                    [
                                        'key' => 'gen_ai.response.finish_reasons',
                                        'value' => ['arrayValue' => [
                                            'values' => [
                                                ['stringValue' => 'stop']
                                            ]
                                        ]]
                                    ],
                                    [
                                        'key' => 'gen_ai.usage.input_tokens',
                                        'value' => ['intValue' => '3000']
                                    ],
                                    [
                                        'key' => 'gen_ai.usage.output_tokens',
                                        'value' => ['intValue' => '300']
                                    ],
                                    [
                                        'key' => 'gen_ai.usage.total_tokens',
                                        'value' => ['intValue' => '3300']
                                    ],
                                    // Output Messages
                                    [
                                        'key' => 'llm.output_messages.0.message.role',
                                        'value' => ['stringValue' => 'assistant']
                                    ],
                                    [
                                        'key' => 'llm.output_messages.0.message.content',
                                        'value' => ['stringValue' => 'The capital of France is Paris.']
                                    ],
                                    // Cost Information
                                    [
                                        'key' => 'llm.usage.prompt_tokens',
                                        'value' => ['intValue' => '3000']
                                    ],
                                    [
                                        'key' => 'llm.usage.completion_tokens',
                                        'value' => ['intValue' => '300']
                                    ],
                                    [
                                        'key' => 'llm.usage.total_tokens',
                                        'value' => ['intValue' => '3300']
                                    ],
                                    [
                                        'key' => 'llm.usage.cost',
                                        'value' => ['doubleValue' => $totalCost]
                                    ],
                                    [
                                        'key' => 'llm.usage.cost_details.prompt',
                                        'value' => ['doubleValue' => $inputCost]
                                    ],
                                    [
                                        'key' => 'llm.usage.cost_details.completion',
                                        'value' => ['doubleValue' => $outputCost]
                                    ],
                                    [
                                        'key' => 'llm.response_id',
                                        'value' => ['stringValue' => 'bedrock-' . uniqid()]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    // Send to Maxim
    $success = sendToMaxim($otlpData, $maximEndpoint, $maximApiKey, $maximRepoId);
    
    if ($success) {
        return $response->withStatus(200)->withJson([
            'status' => 'success',
            'message' => 'Query processed and logged to Maxim',
            'trace_id' => $traceId
        ]);
    } else {
        return $response->withStatus(500)->withJson([
            'status' => 'error',
            'message' => 'Failed to send trace to Maxim (check logs)',
            'trace_id' => $traceId
        ]);
    }
});

$app->run();
