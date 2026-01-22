<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
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

// Create OTLP exporter with error logging wrapper
class ErrorLoggingSpanExporter implements SpanExporterInterface
{
    public function __construct(private SpanExporterInterface $exporter)
    {
    }

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        return $this->exporter->export($batch, $cancellation)
            ->catch(function (\Throwable $e): bool {
                error_log('OTEL Export Error: ' . $e->getMessage());
                error_log('OTEL Export Error Trace: ' . $e->getTraceAsString());
                return false;
            })
            ->map(function (bool $result): bool {
                if (!$result) {
                    error_log('OTEL Export returned false');
                }
                return $result;
            });
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        try {
            return $this->exporter->shutdown($cancellation);
        } catch (\Throwable $e) {
            error_log('OTEL Shutdown Error: ' . $e->getMessage());
            return false;
        }
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        try {
            return $this->exporter->forceFlush($cancellation);
        } catch (\Throwable $e) {
            error_log('OTEL ForceFlush Error: ' . $e->getMessage());
            return false;
        }
    }
}

$baseExporter = new SpanExporter($transport);
$exporter = new ErrorLoggingSpanExporter($baseExporter);

// Create resource with service information
$resource = \OpenTelemetry\SDK\Resource\ResourceInfo::create(
    Attributes::create([
        ResourceAttributes::SERVICE_NAME => 'php-maxim-ai-logging',
        ResourceAttributes::SERVICE_VERSION => '1.0.0',
    ])
);

// Build tracer provider with SimpleSpanProcessor for immediate export
$tracerProvider = (new TracerProviderBuilder())
    ->addSpanProcessor(new SimpleSpanProcessor($exporter))
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
        usleep(250000); // 0.25 seconds
        
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
            $llmSpan->setAttribute('llm.model_name', 'anthropic.claude-3-5-sonnet-20241022-v2:0');
            $llmSpan->setAttribute('llm.provider', 'bedrock');
            $llmSpan->setAttribute('llm.vendor', 'aws');

            // Input Messages (OpenInference indexed format)
            $llmSpan->setAttribute('llm.input_messages.0.message.role', 'user');
            $llmSpan->setAttribute('llm.input_messages.0.message.content', 'What is the capital of France?');

            $llmSpan->setAttribute('llm.output_messages.0.message.role', 'assistant');
            $llmSpan->setAttribute('llm.output_messages.0.message.content', 'Paris!');

            // Token Counts
            $llmSpan->setAttribute('llm.token_count.prompt', 3000);
            $llmSpan->setAttribute('llm.token_count.completion', 300);
            $llmSpan->setAttribute('llm.token_count.total', 3300);

            $llmSpan->setAttribute('llm.cost.total', 0.66);

            // Invocation Parameters
            $llmSpan->setAttribute('llm.invocation_parameters.temperature', 0.7);
            $llmSpan->setAttribute('llm.invocation_parameters.max_tokens', 1000);
            $llmSpan->setAttribute('llm.invocation_parameters.top_p', 0.9);

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
