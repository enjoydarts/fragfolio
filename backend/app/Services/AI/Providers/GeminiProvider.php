<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\CostTrackingService;
use App\Services\AI\PromptBuilder;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    private string $projectId;

    private string $location;

    private string $model;

    private array $costPerToken;

    private CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
        $this->projectId = config('services.gemini.project_id');
        $this->location = config('services.gemini.location', 'us-central1');
        $this->model = config('services.ai.gemini_model', 'gemini-2.5-flash');

        // コスト設定を外部ファイルから取得し、1M tokens → 1 token に変換
        $costs = config('ai_costs.gemini', []);
        $this->costPerToken = [];

        foreach ($costs as $model => $rates) {
            $this->costPerToken[$model] = [
                'input' => ($rates['input'] ?? 0) / 1000000,   // $per 1M tokens → $per token
                'output' => ($rates['output'] ?? 0) / 1000000,  // $per 1M tokens → $per token
            ];
        }

        if (! $this->projectId) {
            throw new \Exception('Gemini project ID is not configured');
        }
    }

    public function complete(string $query, array $options = []): array
    {
        $type = $options['type'] ?? 'fragrance';
        $limit = $options['limit'] ?? 10;
        $language = $options['language'] ?? 'ja';

        return $this->completion($query, $type, $limit, $language);
    }

    public function completion(string $query, string $type, int $limit = 10, string $language = 'ja'): array
    {
        $prompt = $this->buildCompletionPrompt($query, $type, $limit, $language);
        $tools = $this->getCompletionTool($type, $limit);

        $response = $this->makeRequestWithTools($prompt, $tools);
        $result = $this->parseToolResponse($response, 'suggest_fragrances');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'completion', $costEstimate);

        return [
            'suggestions' => $result['suggestions'] ?? [],
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
            'cost_estimate' => $costEstimate,
        ];
    }

    public function normalize(string $brandName, string $fragranceName, array $options = []): array
    {
        $language = $options['language'] ?? 'ja';
        $prompt = $this->buildNormalizationPrompt($brandName, $fragranceName, $language);
        $tools = $this->getNormalizationTool();

        $response = $this->makeRequestWithTools($prompt, $tools);
        $result = $this->parseToolResponse($response, 'normalize_fragrance');
        $costEstimate = $this->estimateCost($response);

        // コスト記録
        $this->recordCost($response, 'normalization', $costEstimate);

        // 新しいスキーマに対応
        return [
            'normalized_brand' => $result['brand_name'] ?? $brandName,
            'normalized_brand_ja' => $result['brand_name'] ?? $brandName,
            'normalized_brand_en' => $result['brand_name_en'] ?? $brandName,
            'normalized_fragrance_name' => $result['text'] ?? $fragranceName,
            'normalized_fragrance_ja' => $result['text'] ?? $fragranceName,
            'normalized_fragrance_en' => $result['text_en'] ?? $fragranceName,
            'confidence_score' => $result['confidence'] ?? 0.5,
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
            'cost_estimate' => $costEstimate,
        ];
    }

    private function buildCompletionPrompt(string $query, string $type, int $limit, string $language): string
    {
        $fewShotExamples = $this->getFewShotExamples($query, $type);

        return PromptBuilder::buildCompletionPrompt($query, $type, $limit, $language, $fewShotExamples);
    }

    private function buildNormalizationPrompt(string $brandName, string $fragranceName, string $language): string
    {
        return PromptBuilder::buildNormalizationPrompt($brandName, $fragranceName, $language);
    }

    private function makeRequestWithTools(string $prompt, array $tools): array
    {
        try {
            Log::info('AI Request Started (Vertex AI Gemini)', [
                'provider' => 'gemini',
                'model' => $this->model,
                'project_id' => $this->projectId,
                'location' => $this->location,
                'prompt_length' => strlen($prompt),
                'tools_count' => count($tools),
            ]);

            // サービスアカウント認証でアクセストークンを取得
            $accessToken = $this->getAccessToken();

            $requestData = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'tools' => [
                    [
                        'function_declarations' => $tools,
                    ],
                ],
                'tool_config' => [
                    'function_calling_config' => [
                        'mode' => 'ANY',
                        'allowed_function_names' => [
                            $tools[0]['name'],
                        ],
                    ],
                ],
                'generation_config' => [
                    'maxOutputTokens' => 8192,
                ],
            ];

            $url = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/{$this->model}:generateContent";

            $response = $this->makeRequestWithRetry($url, $requestData, $accessToken);

            if (! $response->successful()) {
                throw new \Exception('Vertex AI Gemini API request failed: '.$response->body());
            }

            $responseData = $response->json();

            Log::info('AI Request Completed (Vertex AI Gemini)', [
                'provider' => 'gemini',
                'model' => $this->model,
                'input_tokens' => $responseData['usageMetadata']['promptTokenCount'] ?? 0,
                'output_tokens' => $responseData['usageMetadata']['candidatesTokenCount'] ?? 0,
                'cost_estimate' => $this->estimateCost($responseData),
            ]);

            return $responseData;
        } catch (\Exception $e) {
            Log::error('AI Request Failed (Vertex AI Gemini)', [
                'provider' => 'gemini',
                'model' => $this->model,
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);
            throw $e;
        }
    }

    private function parseToolResponse(array $response, string $toolName): array
    {
        $candidates = $response['candidates'] ?? [];

        // デバッグログを追加
        Log::info('parseToolResponse Debug (Gemini)', [
            'tool_name' => $toolName,
            'candidates_count' => count($candidates),
            'response_structure' => array_map(function ($candidate) {
                $content = $candidate['content'] ?? [];
                $parts = $content['parts'] ?? [];

                return [
                    'parts_count' => count($parts),
                    'parts_structure' => array_map(function ($part) {
                        return [
                            'has_function_call' => isset($part['functionCall']),
                            'function_name' => $part['functionCall']['name'] ?? null,
                            'has_args' => isset($part['functionCall']['args']),
                        ];
                    }, $parts),
                ];
            }, $candidates),
        ]);

        foreach ($candidates as $candidate) {
            $content = $candidate['content'] ?? [];
            $parts = $content['parts'] ?? [];

            foreach ($parts as $part) {
                if (isset($part['functionCall']) && $part['functionCall']['name'] === $toolName) {
                    Log::info('Found function call (Gemini)', [
                        'args' => $part['functionCall']['args'],
                        'suggestions_count' => count($part['functionCall']['args']['suggestions'] ?? []),
                    ]);

                    return $part['functionCall']['args'];
                }
            }
        }

        Log::error('No valid function call found (Gemini)', ['response' => $response]);
        throw new \Exception('No valid function call found');
    }

    private function getCompletionTool(string $type, int $limit): array
    {
        $schema = PromptBuilder::suggestionJsonSchema();

        return [
            [
                'name' => $schema['name'],
                'description' => $schema['description'],
                'parameters' => $schema['parameters'],
            ],
        ];
    }

    private function getNormalizationTool(): array
    {
        $schema = PromptBuilder::normalizationJsonSchema();

        return [
            [
                'name' => $schema['name'],
                'description' => $schema['description'],
                'parameters' => $schema['parameters'],
            ],
        ];
    }

    private function getFewShotExamples(string $query, string $type): array
    {
        try {
            $aiFeedbackService = app(\App\Services\AI\AIFeedbackService::class);

            return $aiFeedbackService->getFewShotExamples($query, 'completion', 3);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function estimateCost(array $response): float
    {
        $usage = $response['usageMetadata'] ?? [];

        return $this->calculateCost([
            'model' => $this->model,
            'input_tokens' => $usage['promptTokenCount'] ?? 0,
            'output_tokens' => $usage['candidatesTokenCount'] ?? 0,
        ]);
    }

    public function calculateCost(array $usage): float
    {
        $model = $usage['model'] ?? $this->model;
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $defaultModel = config('ai_costs.defaults.gemini', 'gemini-2.5-flash');
        $rates = $this->costPerToken[$model] ?? $this->costPerToken[$defaultModel] ?? ['input' => 0, 'output' => 0];

        return ($inputTokens * $rates['input']) + ($outputTokens * $rates['output']);
    }

    public function normalizeFromInput(string $input, array $options = []): array
    {
        // 統一入力を解析してブランド名と香水名に分離
        $parts = explode(' ', $input, 2);
        $brandName = $parts[0] ?? '';
        $fragranceName = $parts[1] ?? $input;

        return $this->normalize($brandName, $fragranceName, $options);
    }

    public function suggestNotes(string $brandName, string $fragranceName, array $options = []): array
    {
        // ノート推定機能（今後実装）
        return [
            'top_notes' => [],
            'middle_notes' => [],
            'base_notes' => [],
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
        ];
    }

    public function suggestAttributes(string $fragranceName, array $options = []): array
    {
        // 季節・シーン適性推定機能（今後実装）
        return [
            'seasons' => [],
            'scenes' => [],
            'provider' => 'gemini',
            'ai_provider' => 'gemini',
            'ai_model' => $this->model,
            'model' => $this->model,
        ];
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    private function getAccessToken(): string
    {
        $serviceAccountPath = config('services.gemini.service_account_path');

        if ($serviceAccountPath) {
            // 相対パスの場合は絶対パスに変換
            if (! str_starts_with($serviceAccountPath, '/')) {
                $serviceAccountPath = base_path($serviceAccountPath);
            }

            if (file_exists($serviceAccountPath)) {
                // サービスアカウントキーファイルを使用
                putenv('GOOGLE_APPLICATION_CREDENTIALS='.$serviceAccountPath);
                Log::info('Using service account credentials', ['path' => $serviceAccountPath]);
            } else {
                Log::error('Service account key file not found', ['path' => $serviceAccountPath]);
                throw new \Exception('Service account key file not found: '.$serviceAccountPath);
            }
        }

        try {
            $credentials = ApplicationDefaultCredentials::getCredentials([
                'https://www.googleapis.com/auth/cloud-platform',
            ]);

            $token = $credentials->fetchAuthToken();

            if (! isset($token['access_token'])) {
                throw new \Exception('Failed to get access token from credentials');
            }

            Log::info('Successfully obtained access token');

            return $token['access_token'];
        } catch (\Exception $e) {
            Log::error('Failed to get access token', [
                'error' => $e->getMessage(),
                'service_account_path' => $serviceAccountPath ?? 'not set',
            ]);
            throw new \Exception('Failed to authenticate with Google Cloud: '.$e->getMessage());
        }
    }

    private function recordCost(array $response, string $operation, float $cost): void
    {
        try {
            $usage = $response['usageMetadata'] ?? [];

            $this->costTrackingService->recordCost(
                provider: 'gemini',
                model: $this->model,
                operation: $operation,
                inputTokens: $usage['promptTokenCount'] ?? 0,
                outputTokens: $usage['candidatesTokenCount'] ?? 0,
                cost: $cost
            );
        } catch (\Exception $e) {
            Log::error('Failed to record cost tracking', [
                'provider' => 'gemini',
                'model' => $this->model,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function makeRequestWithRetry(string $url, array $requestData, string $accessToken, int $maxRetries = 5): \Illuminate\Http\Client\Response
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(30)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ])->post($url, $requestData);

                // 429エラー（レート制限）の場合はリトライ
                if ($response->status() === 429) {
                    $attempt++;
                    // Geminiのレート制限は厳しいため、長めのバックオフ: 2秒 → 4秒 → 8秒 → 16秒 → 32秒
                    $waitTime = min(2 ** $attempt, 60); // 指数バックオフ（最大60秒）

                    Log::warning('Gemini rate limit hit, retrying with exponential backoff...', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'wait_time' => $waitTime,
                        'response' => $response->body(),
                    ]);

                    if ($attempt < $maxRetries) {
                        sleep($waitTime);

                        continue;
                    }

                    // 最大リトライ回数に達したら429エラーを返す（フォールバックのため）
                    Log::error('Gemini rate limit exceeded after all retries', [
                        'max_retries' => $maxRetries,
                        'total_wait_time' => array_sum(array_map(fn ($i) => min(2 ** $i, 60), range(1, $maxRetries))),
                    ]);
                }

                return $response;

            } catch (\Exception $e) {
                $attempt++;

                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                $waitTime = min(2 ** $attempt, 60);
                Log::warning('Gemini request failed, retrying...', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'wait_time' => $waitTime,
                    'error' => $e->getMessage(),
                ]);

                sleep($waitTime);
            }
        }

        throw new \Exception('Max retries exceeded');
    }
}
