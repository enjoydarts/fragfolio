<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use InvalidArgumentException;

class AIProviderFactory
{
    private CostTrackingService $costTrackingService;

    public function __construct(CostTrackingService $costTrackingService)
    {
        $this->costTrackingService = $costTrackingService;
    }

    /**
     * AIプロバイダーを作成
     *
     * @param  string|null  $provider  プロバイダー名（nullの場合はデフォルト）
     *
     * @throws InvalidArgumentException
     */
    public function create(?string $provider = null): AIProviderInterface
    {
        $provider = $provider ?: config('services.ai.default_provider', 'openai');

        return match (strtolower($provider)) {
            'openai' => new OpenAIProvider($this->costTrackingService),
            'anthropic' => new AnthropicProvider($this->costTrackingService),
            'gemini' => new GeminiProvider($this->costTrackingService),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}")
        };
    }

    /**
     * 利用可能なプロバイダーリストを取得
     */
    public function getAvailableProviders(): array
    {
        $providers = [];

        // OpenAI API キーが設定されているかチェック
        if (config('services.openai.api_key')) {
            $providers[] = 'openai';
        }

        // Anthropic API キーが設定されているかチェック
        if (config('services.anthropic.api_key')) {
            $providers[] = 'anthropic';
        }

        // Gemini プロジェクトIDが設定されているかチェック
        if (config('services.gemini.project_id')) {
            $providers[] = 'gemini';
        }

        return $providers;
    }

    /**
     * 指定されたプロバイダーが利用可能かチェック
     */
    public function isProviderAvailable(string $provider): bool
    {
        return in_array(strtolower($provider), $this->getAvailableProviders());
    }

    /**
     * デフォルトプロバイダーを取得
     */
    public function getDefaultProvider(): string
    {
        $defaultProvider = config('services.ai.default_provider', 'openai');

        // デフォルトプロバイダーが利用可能でない場合は、利用可能な最初のプロバイダーを返す
        if (! $this->isProviderAvailable($defaultProvider)) {
            $availableProviders = $this->getAvailableProviders();
            if (empty($availableProviders)) {
                throw new \Exception('No AI providers are configured');
            }

            return $availableProviders[0];
        }

        return $defaultProvider;
    }
}
