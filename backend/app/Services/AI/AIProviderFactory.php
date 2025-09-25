<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\AnthropicProvider;
use InvalidArgumentException;

class AIProviderFactory
{
    /**
     * AIプロバイダーを作成
     *
     * @param string|null $provider プロバイダー名（nullの場合はデフォルト）
     * @return AIProviderInterface
     * @throws InvalidArgumentException
     */
    public function create(?string $provider = null): AIProviderInterface
    {
        $provider = $provider ?: config('services.ai.default_provider', 'openai');

        return match (strtolower($provider)) {
            'openai' => new OpenAIProvider(),
            'anthropic' => new AnthropicProvider(),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}")
        };
    }

    /**
     * 利用可能なプロバイダーリストを取得
     *
     * @return array
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

        return $providers;
    }

    /**
     * 指定されたプロバイダーが利用可能かチェック
     *
     * @param string $provider
     * @return bool
     */
    public function isProviderAvailable(string $provider): bool
    {
        return in_array(strtolower($provider), $this->getAvailableProviders());
    }

    /**
     * デフォルトプロバイダーを取得
     *
     * @return string
     */
    public function getDefaultProvider(): string
    {
        $defaultProvider = config('services.ai.default_provider', 'openai');

        // デフォルトプロバイダーが利用可能でない場合は、利用可能な最初のプロバイダーを返す
        if (!$this->isProviderAvailable($defaultProvider)) {
            $availableProviders = $this->getAvailableProviders();
            if (empty($availableProviders)) {
                throw new \Exception('No AI providers are configured');
            }
            return $availableProviders[0];
        }

        return $defaultProvider;
    }
}