<?php

namespace App\Services\AI\Contracts;

interface AIProviderInterface
{
    /**
     * リアルタイム補完機能
     *
     * @param  string  $query  検索クエリ
     * @param  array  $options  オプション設定
     * @return array 補完候補配列
     */
    public function complete(string $query, array $options = []): array;

    /**
     * 包括的正規化機能
     *
     * @param  string  $brandName  ブランド名
     * @param  string  $fragranceName  香水名
     * @param  array  $options  オプション設定
     * @return array 正規化結果
     */
    public function normalize(string $brandName, string $fragranceName, array $options = []): array;

    /**
     * 香りノート推定機能
     *
     * @param  string  $brandName  ブランド名
     * @param  string  $fragranceName  香水名
     * @param  array  $options  オプション設定
     * @return array ノート推定結果
     */
    public function suggestNotes(string $brandName, string $fragranceName, array $options = []): array;

    /**
     * 季節・シーン適性推定機能
     *
     * @param  string  $fragranceName  香水名
     * @param  array  $options  オプション設定
     * @return array 適性推定結果
     */
    public function suggestAttributes(string $fragranceName, array $options = []): array;

    /**
     * コスト計算機能
     *
     * @param  array  $usage  使用量データ
     * @return float コスト（USD）
     */
    public function calculateCost(array $usage): float;

    /**
     * プロバイダー名を取得
     *
     * @return string プロバイダー名
     */
    public function getProviderName(): string;
}
