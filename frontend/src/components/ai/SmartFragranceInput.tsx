import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useAIStore, useNormalizationState } from '../../stores/aiStore';
import { useAIProviders } from '../../hooks/useAIProviders';
import type { CompletionSuggestion } from '../../stores/aiStore';
import ConfidenceIndicator from './ConfidenceIndicator';

interface ApiSuggestion {
  text: string;
  text_en?: string;
  textEn?: string;
  brand_name?: string;
  brandName?: string;
  brand_name_en?: string;
  brandNameEn?: string;
  confidence?: number;
  type?: string;
  source?: string;
}

interface CacheItem {
  data: {
    suggestions: ApiSuggestion[];
  };
  timestamp: number;
}

interface SmartFragranceInputProps {
  value: string;
  onChange: (value: string) => void;
  onNormalizationResult?: (result: {
    brandName?: string;
    brandNameEn?: string;
    fragranceName?: string;
    fragranceNameEn?: string;
  }) => void;
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  label?: string;
  required?: boolean;
  error?: string;
  debounceMs?: number;
  minChars?: number;
}

const SmartFragranceInput: React.FC<SmartFragranceInputProps> = ({
  value,
  onChange,
  onNormalizationResult,
  placeholder,
  disabled = false,
  className = '',
  label,
  required = false,
  error,
  debounceMs = 500,
  minChars = 3,
}) => {
  const { t } = useTranslation();
  const defaultPlaceholder = placeholder || t('ai.input.placeholder');
  const inputRef = useRef<HTMLInputElement>(null);
  const [isNormalizing, setIsNormalizing] = useState(false);
  const [lastNormalized, setLastNormalized] = useState('');
  const [isFocused, setIsFocused] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [isLoadingCompletions, setIsLoadingCompletions] = useState(false);
  const [lastCompletionResponse, setLastCompletionResponse] = useState<{
    suggestions: ApiSuggestion[];
    provider?: string;
    response_time_ms?: number;
    cost_estimate?: number;
  } | null>(null);
  const debounceTimeout = useRef<number | undefined>(undefined);
  const completionTimeout = useRef<number | undefined>(undefined);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const [userInteractingWithDropdown, setUserInteractingWithDropdown] =
    useState(false);

  // LRUキャッシュの実装
  const cacheRef = useRef<Map<string, CacheItem>>(new Map());
  const cacheMaxSize = 50; // 最大キャッシュサイズ
  const cacheMaxAge = 5 * 60 * 1000; // 5分間有効

  const {
    setNormalizationLoading,
    setNormalizationResult,
    setNormalizationError,
    fragranceSuggestions,
    completionLoading,
    setFragranceSuggestions,
    clearAllSuggestions,
  } = useAIStore();

  const { loading: normalizationLoading } = useNormalizationState();
  const { currentProvider } = useAIProviders();

  // 外部クリックでドロップダウンを閉じる
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        containerRef.current &&
        !containerRef.current.contains(event.target as Node)
      ) {
        console.log('🔘 外部クリック - ドロップダウンクローズ');
        setShowSuggestions(false);
        setSelectedIndex(-1);
        setIsFocused(false);
        setUserInteractingWithDropdown(false);
        setIsLoadingCompletions(false);
      }
    };

    if (showSuggestions) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showSuggestions]);

  // AI正規化を実行
  const performNormalization = useCallback(
    async (query: string) => {
      if (
        query.length < minChars ||
        query === lastNormalized ||
        !currentProvider
      ) {
        return;
      }

      setIsNormalizing(true);
      setNormalizationLoading(true);
      setLastNormalized(query);

      try {
        console.log('🔄 Starting normalization request:', {
          query,
          currentProvider,
        });
        const response = await fetch(
          `${import.meta.env.VITE_API_BASE_URL}/api/ai/normalize-from-input`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              input: query,
              provider: currentProvider,
              language: 'mixed', // 日英混在対応
            }),
          }
        );

        if (!response.ok) {
          const errorData = await response.text();
          console.error(
            '❌ AI normalization API error:',
            response.status,
            errorData
          );
          throw new Error(
            `AI normalization request failed: ${response.status}`
          );
        }

        const data = await response.json();
        console.log('✅ Normalization response:', data);

        if (data.success && data.data && data.data.normalized_data) {
          setNormalizationResult(data.data.normalized_data);

          // 正規化結果を親コンポーネントに通知
          if (onNormalizationResult) {
            onNormalizationResult({
              brandName:
                data.data.normalized_data.normalized_brand_ja ||
                data.data.normalized_data.normalized_brand,
              brandNameEn:
                data.data.normalized_data.normalized_brand_en ||
                data.data.normalized_data.normalized_brand,
              fragranceName:
                data.data.normalized_data.normalized_fragrance_ja ||
                data.data.normalized_data.normalized_fragrance_name,
              fragranceNameEn:
                data.data.normalized_data.normalized_fragrance_en ||
                data.data.normalized_data.normalized_fragrance_ja,
            });
          }
        }
      } catch (err) {
        console.error('AI normalization error:', err);
        setNormalizationError(t('ai.normalization.failed'));
      } finally {
        setIsNormalizing(false);
        setNormalizationLoading(false);
      }
    },
    [
      minChars,
      lastNormalized,
      currentProvider,
      setIsNormalizing,
      setNormalizationLoading,
      setLastNormalized,
      setNormalizationResult,
      setNormalizationError,
      onNormalizationResult,
      t,
    ]
  );

  // キャッシュヘルパー関数
  const getCachedResult = useCallback(
    (query: string) => {
      const cacheKey = `${currentProvider}-${query.toLowerCase()}`;
      const cached = cacheRef.current.get(cacheKey);

      if (cached && Date.now() - cached.timestamp < cacheMaxAge) {
        return cached.data;
      }

      // 期限切れのキャッシュを削除
      if (cached) {
        cacheRef.current.delete(cacheKey);
      }

      return null;
    },
    [currentProvider, cacheMaxAge]
  );

  const setCachedResult = useCallback(
    (query: string, data: { suggestions: ApiSuggestion[] }) => {
      const cacheKey = `${currentProvider}-${query.toLowerCase()}`;

      // キャッシュサイズ制限
      if (cacheRef.current.size >= cacheMaxSize) {
        // 最も古いエントリを削除
        const firstKey = cacheRef.current.keys().next().value;
        if (firstKey) {
          cacheRef.current.delete(firstKey);
        }
      }

      cacheRef.current.set(cacheKey, {
        data,
        timestamp: Date.now(),
      });
    },
    [currentProvider, cacheMaxSize]
  );

  // 補完機能（キャッシュ対応）
  const fetchCompletions = useCallback(
    async (query: string) => {
      if (query.length < 2 || !currentProvider) {
        clearAllSuggestions();
        setIsLoadingCompletions(false);
        return;
      }

      // キャッシュチェック
      const cachedResult = getCachedResult(query);
      if (cachedResult) {
        console.log('✅ キャッシュヒット:', query);
        const suggestions: CompletionSuggestion[] =
          cachedResult.suggestions.map((s: ApiSuggestion) => ({
            text: s.text,
            textEn: s.text_en || s.textEn,
            brandName: s.brand_name || s.brandName,
            brandNameEn: s.brand_name_en || s.brandNameEn,
            confidence: s.confidence || 0.5,
            type: (s.type || 'fragrance') as 'brand' | 'fragrance',
            source: s.source,
          }));

        setFragranceSuggestions(suggestions);
        if (isFocused) {
          setShowSuggestions(true);
          setUserInteractingWithDropdown(true);
        }
        return;
      }

      setIsLoadingCompletions(true);
      console.log('🔍 AI補完開始:', query, 'provider:', currentProvider);

      try {
        const response = await fetch(
          `${import.meta.env.VITE_API_BASE_URL}/api/ai/complete`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              query,
              type: 'fragrance',
              limit: 12,
              provider: currentProvider,
              language: 'ja',
            }),
          }
        );

        if (!response.ok) {
          const errorData = await response.text();
          console.error('❌ AI補完API エラー:', response.status, errorData);
          throw new Error(`AI completion request failed: ${response.status}`);
        }

        const data = await response.json();
        console.log(
          '✅ AI補完完了:',
          data.data?.suggestions?.length || 0,
          '件'
        );

        if (
          data.success &&
          data.data.suggestions &&
          data.data.suggestions.length > 0
        ) {
          // APIレスポンスを保存してフィードバック記録で使用
          setLastCompletionResponse(data.data);

          const suggestions: CompletionSuggestion[] = data.data.suggestions.map(
            (s: ApiSuggestion) => ({
              text: s.text,
              textEn: s.text_en || s.textEn,
              brandName: s.brand_name || s.brandName,
              brandNameEn: s.brand_name_en || s.brandNameEn,
              confidence: s.confidence || 0.5,
              type: s.type || 'fragrance',
              source: s.source,
            })
          );

          // キャッシュに保存
          setCachedResult(query, data.data);
          console.log('💾 キャッシュ保存:', query);

          setFragranceSuggestions(suggestions);
          // フォーカスしている場合は必ずドロップダウンを表示
          if (isFocused) {
            setShowSuggestions(true);
            setUserInteractingWithDropdown(true);
          }
        } else {
          console.log('⚠️ 提案なし');
          setLastCompletionResponse(null);
          clearAllSuggestions();
        }
      } catch (err) {
        console.error('❌ AI補完エラー:', err);
        clearAllSuggestions();
      } finally {
        setIsLoadingCompletions(false);
      }
    },
    [
      currentProvider,
      clearAllSuggestions,
      getCachedResult,
      setCachedResult,
      setFragranceSuggestions,
      setLastCompletionResponse,
      isFocused,
    ]
  );

  // デバウンス付き正規化実行
  useEffect(() => {
    if (debounceTimeout.current) {
      clearTimeout(debounceTimeout.current);
    }
    if (completionTimeout.current) {
      clearTimeout(completionTimeout.current);
    }

    if (value && value.length >= minChars && value !== lastNormalized) {
      // 正規化は十分に長い遅延にしてドロップダウンの操作を妨げない
      debounceTimeout.current = setTimeout(() => {
        // ドロップダウンが表示されている間、またはユーザーがドロップダウンと対話中は正規化を実行しない
        if (!showSuggestions && !userInteractingWithDropdown) {
          performNormalization(value);
        }
      }, 2000); // 2秒に延長
    }

    if (value && value.length >= 2) {
      // 補完は短い遅延
      completionTimeout.current = setTimeout(() => {
        fetchCompletions(value);
      }, 300); // 300msに変更してAPIへの負荷軽減
    } else {
      clearAllSuggestions();
      setIsLoadingCompletions(false);
    }

    return () => {
      if (debounceTimeout.current) {
        clearTimeout(debounceTimeout.current);
      }
      if (completionTimeout.current) {
        clearTimeout(completionTimeout.current);
      }
    };
  }, [
    value,
    debounceMs,
    minChars,
    currentProvider,
    clearAllSuggestions,
    fetchCompletions,
    lastNormalized,
    performNormalization,
    showSuggestions,
    userInteractingWithDropdown,
  ]);

  // キーボードナビゲーション
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!showSuggestions || fragranceSuggestions.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex((prev) => (prev + 1) % fragranceSuggestions.length);
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(
          (prev) =>
            (prev - 1 + fragranceSuggestions.length) %
            fragranceSuggestions.length
        );
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0 && selectedIndex < fragranceSuggestions.length) {
          handleSuggestionSelect(fragranceSuggestions[selectedIndex]);
        }
        break;
      case 'Escape':
        e.preventDefault();
        clearAllSuggestions();
        setShowSuggestions(false);
        setSelectedIndex(-1);
        setUserInteractingWithDropdown(false);
        setIsLoadingCompletions(false);
        break;
    }
  };

  // AI提案のフィードバックを記録
  const recordFeedback = async (
    action: 'selected' | 'rejected' | 'modified',
    suggestion?: CompletionSuggestion,
    finalValue?: string
  ) => {
    try {
      // APIレスポンスからプロバイダー情報を取得
      const aiProvider = lastCompletionResponse?.provider || 'unknown';
      const aiModel = 'unknown'; // モデル情報は現在のレスポンスに含まれていない

      const feedbackData = {
        operation_type: 'completion',
        query: value,
        request_params: { type: 'fragrance', limit: 12, language: 'ja' },
        ai_provider: aiProvider,
        ai_model: aiModel,
        ai_suggestions: fragranceSuggestions,
        user_action: action,
        selected_suggestion: suggestion,
        final_input: finalValue,
        relevance_score: suggestion?.confidence,
        was_helpful: action === 'selected',
      };

      console.log(
        '📝 AI提案フィードバック記録:',
        action,
        suggestion?.text,
        `provider: ${aiProvider}, model: ${aiModel}`
      );

      // バックエンドAPIに送信
      await fetch(
        `${import.meta.env.VITE_API_BASE_URL}/api/ai/feedback/selection`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(feedbackData),
        }
      );
    } catch (error) {
      console.warn('フィードバック記録失敗:', error);
    }
  };

  // 提案選択処理
  const handleSuggestionSelect = (suggestion: CompletionSuggestion) => {
    console.log('✅ 提案選択:', suggestion.text);
    onChange(suggestion.text);

    // フィードバック記録
    recordFeedback('selected', suggestion, suggestion.text);

    // 正規化結果として通知
    if (onNormalizationResult) {
      onNormalizationResult({
        brandName: suggestion.brandName || '',
        brandNameEn: suggestion.brandNameEn || '',
        fragranceName: suggestion.text || '',
        fragranceNameEn: suggestion.textEn || '',
      });
    }

    // UI状態をリセット
    clearAllSuggestions();
    setShowSuggestions(false);
    setSelectedIndex(-1);
    setUserInteractingWithDropdown(false);
    setIsLoadingCompletions(false);
    inputRef.current?.focus();
  };

  // フォーカス処理
  const handleFocus = () => {
    setIsFocused(true);
    console.log(
      '🎯 フォーカス:',
      value,
      'suggestions:',
      fragranceSuggestions.length
    );
    // 候補があれば表示、なければ少し待ってから補完を実行
    if (fragranceSuggestions.length > 0) {
      setShowSuggestions(true);
      setUserInteractingWithDropdown(true);
    } else if (value && value.length >= 2) {
      // フォーカス時に値があれば即座に補完を実行
      fetchCompletions(value);
    }
  };

  // ブラー処理は外部クリックで処理するため削除

  return (
    <div ref={containerRef} className={`relative ${className}`}>
      {label && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}

      <div className="relative">
        <input
          ref={inputRef}
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onFocus={handleFocus}
          onKeyDown={handleKeyDown}
          placeholder={defaultPlaceholder}
          disabled={disabled}
          className={`
            w-full px-4 py-4 border-2 border-gray-200 rounded-lg shadow-sm
            placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
            bg-white text-gray-900 font-medium text-lg
            ${error ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : ''}
            ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}
            ${isNormalizing || normalizationLoading || completionLoading ? 'pr-12' : ''}
          `}
        />

        {/* ローディングインジケータ */}
        {(isNormalizing || normalizationLoading || isLoadingCompletions) && (
          <div className="absolute inset-y-0 right-0 flex items-center pr-3">
            <div className="flex items-center gap-2">
              <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500"></div>
              <span className="text-xs text-blue-600 font-medium">
                {isLoadingCompletions && t('ai.status.suggesting')}
                {isNormalizing && t('ai.status.normalizing')}
                {normalizationLoading && t('ai.status.analyzing')}
              </span>
            </div>
          </div>
        )}

        {/* 補完ドロップダウン */}
        {(showSuggestions && fragranceSuggestions.length > 0) ||
        isLoadingCompletions ? (
          <div
            ref={dropdownRef}
            className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
          >
            {/* ローディング表示 */}
            {isLoadingCompletions && fragranceSuggestions.length === 0 && (
              <div className="px-3 py-4 text-center">
                <div className="flex items-center justify-center gap-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
                  <span className="text-sm text-gray-600">
                    {t('ai.completion.loading')}
                  </span>
                </div>
              </div>
            )}
            {/* 提案リスト */}
            {fragranceSuggestions.length > 0 && (
              <>
                {fragranceSuggestions.map((suggestion, index) => (
                  <div
                    key={`${suggestion.text}-${index}`}
                    className={`
                      px-3 py-2 cursor-pointer border-b border-gray-100 last:border-b-0
                      ${index === selectedIndex ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50'}
                    `}
                    onClick={() => handleSuggestionSelect(suggestion)}
                    onMouseEnter={() => setSelectedIndex(index)}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <span className="text-gray-900 font-medium">
                            {suggestion.text}
                          </span>
                          {suggestion.textEn &&
                            suggestion.textEn !== suggestion.text && (
                              <span className="text-sm text-gray-500">
                                ({suggestion.textEn})
                              </span>
                            )}
                        </div>
                        {suggestion.brandName && (
                          <div className="flex items-center gap-2 mt-1">
                            <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                              {suggestion.brandName}
                            </span>
                            {suggestion.brandNameEn &&
                              suggestion.brandNameEn !==
                                suggestion.brandName && (
                                <span className="text-xs text-gray-500">
                                  ({suggestion.brandNameEn})
                                </span>
                              )}
                          </div>
                        )}
                      </div>
                      <div className="ml-2 flex-shrink-0">
                        <ConfidenceIndicator
                          confidence={suggestion.confidence}
                          size="sm"
                          showLabel={false}
                          showPercentage={false}
                          className="w-12"
                        />
                      </div>
                    </div>
                    {suggestion.source && (
                      <div className="text-xs text-gray-400 mt-1">
                        {suggestion.source}
                      </div>
                    )}
                  </div>
                ))}
              </>
            )}
          </div>
        ) : null}
      </div>

      {/* エラーメッセージ */}
      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}

      {/* ヘルプテキスト */}
      {!error && (
        <p className="mt-2 text-xs text-gray-500">{t('ai.input.help_text')}</p>
      )}
    </div>
  );
};

export default SmartFragranceInput;
