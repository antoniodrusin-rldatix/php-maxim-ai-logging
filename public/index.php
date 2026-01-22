<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;

// Maxim OTLP endpoint configuration
$maximApiKey = getenv('MAXIM_API_KEY') ?: 'your_api_key_here';
$maximRepoId = getenv('MAXIM_REPO_ID') ?: 'your_repository_id_here';
$maximEndpoint = 'https://api.getmaxim.ai/v1/otel';

// Create transport with Maxim headers
$transport = (new OtlpHttpTransportFactory())->create(
    $maximEndpoint,
    'application/json',
    [
        'x-maxim-api-key' => $maximApiKey,
        'x-maxim-repo-id' => $maximRepoId,
    ]
);

// Create OTLP exporter
$exporter = new SpanExporter($transport);

// Create resource with service information
$resource = ResourceInfoFactory::emptyResource()
    ->merge(\OpenTelemetry\SDK\Resource\ResourceInfo::create(
        Attributes::create([
            ResourceAttributes::SERVICE_NAME => 'php-maxim-ai-logging',
            ResourceAttributes::SERVICE_VERSION => '1.0.0',
        ])
    ));

// Build tracer provider
$tracerProvider = (new TracerProviderBuilder())
    ->addSpanProcessor(new BatchSpanProcessor($exporter, Clock::getDefault()))
    ->setResource($resource)
    ->build();

// Get tracer
$tracer = $tracerProvider->getTracer('php-maxim-ai-logging', '1.0.0');

$app = AppFactory::create();

// GET endpoint that simulates an LLM call and logs to Maxim
$app->get('/query', function ($request, $response, $args) use ($tracer) {
    // Start root span
    $rootSpan = $tracer->spanBuilder('query')
        ->setSpanKind(SpanKind::KIND_SERVER)
        ->startSpan();
    
    $scope = $rootSpan->activate();
    
    try {
        // Simulate LLM processing time
        usleep(250000); // 0.5 seconds
        
        // Calculate costs (Claude 3.5 Sonnet on Bedrock pricing)
        // Input: $3.00 per 1M tokens, Output: $15.00 per 1M tokens
        $inputCost = (3000 / 1000000) * 3.00;  // $0.009
        $outputCost = (300 / 1000000) * 15.00;  // $0.0045
        $totalCost = $inputCost + $outputCost;  // $0.0135
        
        // Create LLM span with OpenInference semantic conventions
        // The parent context is automatically inherited from the activated root span
        $llmSpan = $tracer->spanBuilder('llm.call')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();
        
        $llmScope = $llmSpan->activate();
        
        try {
            // OpenInference required attribute
            $llmSpan->setAttribute('openinference.span.kind', 'LLM');
            
            // LLM Model Information
//            $llmSpan->setAttribute('llm.model_name', 'anthropic.claude-3-5-sonnet-20241022-v2:0');
//            $llmSpan->setAttribute('llm.provider', 'bedrock');
//            $llmSpan->setAttribute('llm.vendor', 'aws');
//
//            // Input Messages (OpenInference indexed format)
//            $llmSpan->setAttribute('llm.input_messages.0.message.role', 'user');
//            $llmSpan->setAttribute('llm.input_messages.0.message.content', 'What is the capital of France?');
//
//            // Token Counts
//            $llmSpan->setAttribute('llm.token_count.prompt', 3000);
//            $llmSpan->setAttribute('llm.token_count.completion', 300);
//            $llmSpan->setAttribute('llm.token_count.total', 3300);
//
//            // Invocation Parameters
//            $llmSpan->setAttribute('llm.invocation_parameters.temperature', 0.7);
//            $llmSpan->setAttribute('llm.invocation_parameters.max_tokens', 1000);
//            $llmSpan->setAttribute('llm.invocation_parameters.top_p', 0.9);
            
            // Generative AI Semantic Conventions
            $llmSpan->setAttribute('gen_ai.system', 'aws.bedrock');
            $llmSpan->setAttribute('gen_ai.provider.name', 'aws.bedrock');
            $llmSpan->setAttribute('gen_ai.operation.name', 'chat');
            $llmSpan->setAttribute('gen_ai.request.type', 'chat');
            $llmSpan->setAttribute('gen_ai.request.model', 'anthropic.claude-3-5-sonnet-20241022-v2:0');
            $llmSpan->setAttribute('gen_ai.request.max_tokens', 1000);
            $llmSpan->setAttribute('gen_ai.request.temperature', 0.7);
            $llmSpan->setAttribute('gen_ai.request.top_p', 0.9);
            $llmSpan->setAttribute('gen_ai.response.type', 'chat');
            $llmSpan->setAttribute('gen_ai.response.model', 'anthropic.claude-3-5-sonnet-20241022-v2:0');
            $llmSpan->setAttribute('gen_ai.response.id', 'bedrock-' . uniqid());
            $llmSpan->setAttribute('gen_ai.response.finish_reasons', ['stop']);
            $llmSpan->setAttribute('gen_ai.usage.input_tokens', 3000);
            $llmSpan->setAttribute('gen_ai.usage.output_tokens', 300);
            $llmSpan->setAttribute('gen_ai.usage.total_tokens', 3300);
            
//            // Output Messages
//            $llmSpan->setAttribute('llm.output_messages.0.message.role', 'assistant');
//            $llmSpan->setAttribute('llm.output_messages.0.message.content', 'The capital of France is Paris.');
//
//            // Cost Information
//            $llmSpan->setAttribute('llm.usage.prompt_tokens', 3000);
//            $llmSpan->setAttribute('llm.usage.completion_tokens', 300);
//            $llmSpan->setAttribute('llm.usage.total_tokens', 3300);
//            $llmSpan->setAttribute('llm.usage.cost', $totalCost);
//            $llmSpan->setAttribute('llm.usage.cost_details.prompt', $inputCost);
//            $llmSpan->setAttribute('llm.usage.cost_details.completion', $outputCost);
//            $llmSpan->setAttribute('llm.response_id', 'bedrock-' . uniqid());
            
            $llmSpan->setStatus(StatusCode::STATUS_OK);
        } finally {
            $llmSpan->end();
            $llmScope->detach();
        }
        
        $rootSpan->setStatus(StatusCode::STATUS_OK);
        
        $traceId = $rootSpan->getContext()->getTraceId();
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Query processed and logged to Maxim',
            'trace_id' => $traceId
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $rootSpan->recordException($e);
        $rootSpan->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        
        $traceId = $rootSpan->getContext()->getTraceId();
        
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Failed to process query: ' . $e->getMessage(),
            'trace_id' => $traceId
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    } finally {
        $rootSpan->end();
        $scope->detach();
    }
});

$app->run();
