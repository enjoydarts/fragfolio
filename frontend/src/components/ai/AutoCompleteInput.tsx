import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useAIStore } from '../../stores/aiStore';
import type { CompletionSuggestion } from '../../stores/aiStore';
import ConfidenceIndicator from './ConfidenceIndicator';

interface AutoCompleteInputProps {
  value: string;
  onChange: (value: string) => void;
  onSelect?: (suggestion: CompletionSuggestion) => void;
  onSelectEnglish?: (englishName: string) => void; // 英語名を別フィールドに入力するコールバック
  onSelectJapanese?: (japaneseName: string) => void; // 日本語名を別フィールドに入力するコールバック
  onSelectBrand?: (brandName: string, brandNameEn?: string) => void; // ブランド名を入力するコールバック（香水選択時）
  contextBrand?: string; // ブランド名が入力済みの場合、そのブランドでフィルタ
  type: 'brand' | 'fragrance';
  expectedLanguage?: 'ja' | 'en' | 'mixed'; // 期待される言語（デフォルト: mixed）
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  label?: string;
  required?: boolean;
  error?: string;
  debounceMs?: number;
  minChars?: number;
}

const AutoCompleteInput: React.FC<AutoCompleteInputProps> = ({
  value,
  onChange,
  onSelect,
  onSelectEnglish,
  onSelectJapanese,
  onSelectBrand,
  contextBrand,
  type,
  expectedLanguage = 'mixed',
  placeholder,
  disabled = false,
  className = '',
  label,
  required = false,
  error,
  debounceMs = 150,
  minChars = 1,
}) => {
  const { t } = useTranslation();
  const inputRef = useRef<HTMLInputElement>(null);
  const [isFocused, setIsFocused] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const cache = useRef<Map<string, CompletionSuggestion[]>>(new Map());

  const {
    brandSuggestions,
    fragranceSuggestions,
    completionLoading,
    showSuggestions,
    activeSuggestionType,
    setShowSuggestions,
    setActiveSuggestionType,
    clearAllSuggestions,
  } = useAIStore();

  // 現在のタイプに応じた提案を取得
  const suggestions = type === 'brand' ? brandSuggestions : fragranceSuggestions;
  const isActive = activeSuggestionType === type;

  // デバウンス処理
  const debounceTimeout = useRef<NodeJS.Timeout>();

  // 言語検出関数
  const detectLanguage = useCallback((text: string): 'ja' | 'en' => {
    // 日本語文字（ひらがな、カタカナ、漢字）の正規表現
    const japaneseRegex = /[\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FAF]/;
    // 英語アルファベットの正規表現
    const englishRegex = /[A-Za-z]/;

    const hasJapanese = japaneseRegex.test(text);
    const hasEnglish = englishRegex.test(text);

    // 日本語文字が含まれていれば日本語とみなす
    if (hasJapanese) return 'ja';
    // 英語のみなら英語
    if (hasEnglish) return 'en';
    // どちらでもなければデフォルト言語
    return 'ja';
  }, []);

  const fetchCompletions = useCallback(async (query: string) => {
    if (query.length < minChars) {
      clearAllSuggestions();
      return;
    }

    // 入力言語を検出
    const detectedLanguage = detectLanguage(query);

    // 期待言語と異なる場合の警告（mixedでない場合）
    const languageWarning = expectedLanguage !== 'mixed' && expectedLanguage !== detectedLanguage;

    // キャッシュから結果を取得（言語も含む）
    const cacheKey = `${type}-${query.toLowerCase()}-${detectedLanguage}`;
    const cachedResult = cache.current.get(cacheKey);
    if (cachedResult) {
      if (type === 'brand') {
        useAIStore.getState().setBrandSuggestions(cachedResult);
      } else {
        useAIStore.getState().setFragranceSuggestions(cachedResult);
      }
      useAIStore.getState().setActiveSuggestionType(type);
      useAIStore.getState().setShowSuggestions(true);
      return;
    }

    try {
      const response = await fetch(`${import.meta.env.VITE_API_BASE_URL}/api/ai/complete`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          query,
          type,
          limit: 8,
          language: detectedLanguage, // 検出された言語を送信
          contextBrand, // ブランド名でフィルタ
        }),
      });

      if (!response.ok) {
        throw new Error('AI completion request failed');
      }

      const data = await response.json();

      if (data.success && data.data.suggestions) {
        // ストアを直接更新するのではなく、APIから取得したデータを処理
        const suggestions: CompletionSuggestion[] = data.data.suggestions.map((s: {
          text: string;
          text_en?: string;
          textEn?: string;
          brand_name?: string;
          brandName?: string;
          brand_name_en?: string;
          brandNameEn?: string;
          confidence?: number;
          adjusted_confidence?: number;
          type?: string;
          source?: string;
          metadata?: {
            english_name?: string;
            brand_name?: string;
            brand_name_en?: string;
            provider?: string;
          };
        }) => ({
          text: s.text,
          textEn: s.text_en || s.textEn || (s.metadata?.english_name), // 英語名も含める
          brandName: s.brand_name || s.brandName || (s.metadata?.brand_name), // ブランド名
          brandNameEn: s.brand_name_en || s.brandNameEn || (s.metadata?.brand_name_en), // ブランド名（英語）
          confidence: s.confidence || s.adjusted_confidence || 0.5,
          type: s.type || type,
          source: s.source || s.metadata?.provider,
        }));

        // 言語警告を追加
        if (languageWarning) {
          suggestions.forEach(s => {
            s.languageWarning = true;
          });
        }

        // キャッシュに保存
        cache.current.set(cacheKey, suggestions);

        // AIストアの該当メソッドを呼び出し
        if (type === 'brand') {
          useAIStore.getState().setBrandSuggestions(suggestions);
        } else {
          useAIStore.getState().setFragranceSuggestions(suggestions);
        }

        useAIStore.getState().setActiveSuggestionType(type);
        useAIStore.getState().setShowSuggestions(true);
      }
    } catch (err) {
      console.error('AI completion error:', err);
      clearAllSuggestions();
    } finally {
      useAIStore.getState().setCompletionLoading(false);
    }
  }, [type, minChars, clearAllSuggestions, contextBrand, detectLanguage, expectedLanguage]);

  // 入力値変更時の処理
  useEffect(() => {
    if (debounceTimeout.current) {
      clearTimeout(debounceTimeout.current);
    }

    if (value && value.length >= minChars) {
      useAIStore.getState().setCompletionLoading(true);
      debounceTimeout.current = setTimeout(() => {
        fetchCompletions(value);
      }, debounceMs);
    } else {
      clearAllSuggestions();
    }

    return () => {
      if (debounceTimeout.current) {
        clearTimeout(debounceTimeout.current);
      }
    };
  }, [value, fetchCompletions, debounceMs, minChars, clearAllSuggestions]);

  // キーボードナビゲーション
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!showSuggestions || !isActive || suggestions.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(prev => (prev + 1) % suggestions.length);
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(prev => (prev - 1 + suggestions.length) % suggestions.length);
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0 && selectedIndex < suggestions.length) {
          handleSuggestionSelect(suggestions[selectedIndex]);
        }
        break;
      case 'Escape':
        e.preventDefault();
        clearAllSuggestions();
        setSelectedIndex(-1);
        break;
    }
  };

  // 提案選択時の処理
  const handleSuggestionSelect = (suggestion: CompletionSuggestion) => {
    onChange(suggestion.text);
    if (onSelect) {
      onSelect(suggestion);
    }

    // 期待言語に応じて適切なフィールドに自動入力
    if (expectedLanguage === 'ja') {
      // 日本語フィールドなので、英語名を英語フィールドに入力
      if (suggestion.textEn && onSelectEnglish) {
        onSelectEnglish(suggestion.textEn);
      }
    } else if (expectedLanguage === 'en') {
      // 英語フィールドなので、日本語名を日本語フィールドに入力
      if (suggestion.text && onSelectJapanese) {
        onSelectJapanese(suggestion.text);
      }
    } else if (expectedLanguage === 'mixed') {
      // 混在フィールドの場合、両方に入力
      if (suggestion.textEn && onSelectEnglish) {
        onSelectEnglish(suggestion.textEn);
      }
      if (suggestion.text && onSelectJapanese && suggestion.text !== value) {
        onSelectJapanese(suggestion.text);
      }
    }

    // 香水選択時にブランド名も自動入力
    if (suggestion.type === 'fragrance' && suggestion.brandName && onSelectBrand) {
      onSelectBrand(suggestion.brandName, suggestion.brandNameEn);
    }

    clearAllSuggestions();
    setSelectedIndex(-1);
    inputRef.current?.focus();
  };

  // フォーカス処理
  const handleFocus = () => {
    setIsFocused(true);
    if (value && value.length >= minChars && suggestions.length > 0) {
      setShowSuggestions(true);
      setActiveSuggestionType(type);
    }
  };

  const handleBlur = () => {
    setIsFocused(false);
    // 少し遅延させて、クリックイベントを処理できるようにする
    setTimeout(() => {
      setShowSuggestions(false);
      setSelectedIndex(-1);
    }, 150);
  };

  return (
    <div className={`relative ${className}`}>
      {label && (
        <label className="block text-sm font-medium text-gray-700 mb-1">
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
          onBlur={handleBlur}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          className={`
            w-full px-4 py-3 border-2 border-gray-200 rounded-lg shadow-sm
            placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
            bg-white text-gray-900 font-medium
            ${error ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : ''}
            ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}
            ${completionLoading ? 'pr-12' : ''}
          `}
        />

        {/* ローディングインジケータ */}
        {completionLoading && (
          <div className="absolute inset-y-0 right-0 flex items-center pr-3">
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
          </div>
        )}

        {/* 提案ドロップダウン */}
        {showSuggestions && isActive && suggestions.length > 0 && isFocused && (
          <div className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
            {suggestions.map((suggestion, index) => (
              <div
                key={`${suggestion.text}-${index}`}
                className={`
                  px-3 py-2 cursor-pointer border-b border-gray-100 last:border-b-0
                  ${index === selectedIndex ? 'bg-blue-50 border-blue-200' : 'hover:bg-gray-50'}
                `}
                onMouseDown={(e) => e.preventDefault()} // Blurイベントを防ぐ
                onClick={() => handleSuggestionSelect(suggestion)}
                onMouseEnter={() => setSelectedIndex(index)}
              >
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="text-gray-900 font-medium">
                        {suggestion.text}
                      </span>
                      {/* 言語警告表示 */}
                      {suggestion.languageWarning && (
                        <span className="text-xs text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                          {expectedLanguage === 'ja' ? t('ai.input.language_warning.en_expected') : t('ai.input.language_warning.ja_expected')}
                        </span>
                      )}
                    </div>
                    {/* 香水の場合はブランド名も表示 */}
                    {suggestion.type === 'fragrance' && suggestion.brandName && (
                      <span className="ml-2 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                        {suggestion.brandName}
                      </span>
                    )}
                  </div>
                  <div className="ml-2 flex-shrink-0">
                    <ConfidenceIndicator
                      confidence={suggestion.confidence}
                      size="sm"
                      showLabel={false}
                      showPercentage={false}
                      className="w-16"
                    />
                  </div>
                </div>
                {suggestion.source && (
                  <div className="text-xs text-gray-500 mt-1">
                    {t('ai.completion.source')}: {suggestion.source}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* エラーメッセージ */}
      {error && (
        <p className="mt-1 text-sm text-red-600">
          {error}
        </p>
      )}

      {/* ヘルプテキスト */}
      {!error && (
        <p className="mt-1 text-xs text-gray-500">
          {t('ai.completion.help', { minChars })}
        </p>
      )}
    </div>
  );
};

export default AutoCompleteInput;