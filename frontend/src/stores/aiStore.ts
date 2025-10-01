import { create } from 'zustand';

// AI補完の提案アイテムの型定義
export interface CompletionSuggestion {
  text: string;
  textEn?: string; // 英語名
  brandName?: string; // 香水の場合のブランド名
  brandNameEn?: string; // 香水の場合のブランド名（英語）
  confidence: number;
  type: 'brand' | 'fragrance';
  source?: string;
  languageWarning?: boolean; // 言語不一致警告
}

// AI正規化の結果型定義
export interface NormalizationResult {
  normalized_brand?: string;
  normalized_brand_ja?: string;
  normalized_brand_en?: string;
  normalized_fragrance?: string;
  normalized_fragrance_name?: string;
  normalized_fragrance_ja?: string;
  normalized_fragrance_en?: string;
  final_confidence_score?: number;
  confidence_score?: number;
  fallback_reason?: string;
}

// AI機能の状態管理インターface
interface AIState {
  // 補完機能の状態
  completionLoading: boolean;
  brandSuggestions: CompletionSuggestion[];
  fragranceSuggestions: CompletionSuggestion[];

  // 正規化機能の状態
  normalizationLoading: boolean;
  normalizationResult: NormalizationResult | null;
  normalizationError: string | null;

  // プロバイダー情報
  availableProviders: string[];
  currentProvider: string;

  // UI状態
  showSuggestions: boolean;
  activeSuggestionType: 'brand' | 'fragrance' | null;

  // アクション
  setBrandSuggestions: (suggestions: CompletionSuggestion[]) => void;
  setFragranceSuggestions: (suggestions: CompletionSuggestion[]) => void;
  setCompletionLoading: (loading: boolean) => void;

  setNormalizationResult: (result: NormalizationResult | null) => void;
  setNormalizationLoading: (loading: boolean) => void;
  setNormalizationError: (error: string | null) => void;

  setAvailableProviders: (providers: string[]) => void;
  setCurrentProvider: (provider: string) => void;

  setShowSuggestions: (show: boolean) => void;
  setActiveSuggestionType: (type: 'brand' | 'fragrance' | null) => void;

  // 複合アクション
  clearAllSuggestions: () => void;
  resetNormalization: () => void;
  resetAll: () => void;
}

export const useAIStore = create<AIState>((set) => ({
  // 初期状態
  completionLoading: false,
  brandSuggestions: [],
  fragranceSuggestions: [],

  normalizationLoading: false,
  normalizationResult: null,
  normalizationError: null,

  availableProviders: [],
  currentProvider: '',

  showSuggestions: false,
  activeSuggestionType: null,

  // 基本アクション
  setBrandSuggestions: (suggestions) => set({ brandSuggestions: suggestions }),

  setFragranceSuggestions: (suggestions) =>
    set({ fragranceSuggestions: suggestions }),

  setCompletionLoading: (loading) => set({ completionLoading: loading }),

  setNormalizationResult: (result) => set({ normalizationResult: result }),

  setNormalizationLoading: (loading) => set({ normalizationLoading: loading }),

  setNormalizationError: (error) => set({ normalizationError: error }),

  setAvailableProviders: (providers) => set({ availableProviders: providers }),

  setCurrentProvider: (provider) => set({ currentProvider: provider }),

  setShowSuggestions: (show) => set({ showSuggestions: show }),

  setActiveSuggestionType: (type) => set({ activeSuggestionType: type }),

  // 複合アクション
  clearAllSuggestions: () =>
    set({
      brandSuggestions: [],
      fragranceSuggestions: [],
      showSuggestions: false,
      activeSuggestionType: null,
    }),

  resetNormalization: () =>
    set({
      normalizationResult: null,
      normalizationError: null,
      normalizationLoading: false,
    }),

  resetAll: () =>
    set({
      completionLoading: false,
      brandSuggestions: [],
      fragranceSuggestions: [],
      normalizationLoading: false,
      normalizationResult: null,
      normalizationError: null,
      showSuggestions: false,
      activeSuggestionType: null,
    }),
}));

// セレクターヘルパー（パフォーマンス最適化）
export const useCompletionState = () => {
  const loading = useAIStore((state) => state.completionLoading);
  const brandSuggestions = useAIStore((state) => state.brandSuggestions);
  const fragranceSuggestions = useAIStore(
    (state) => state.fragranceSuggestions
  );
  const showSuggestions = useAIStore((state) => state.showSuggestions);
  const activeSuggestionType = useAIStore(
    (state) => state.activeSuggestionType
  );

  return {
    loading,
    brandSuggestions,
    fragranceSuggestions,
    showSuggestions,
    activeSuggestionType,
  };
};

export const useNormalizationState = () => {
  const loading = useAIStore((state) => state.normalizationLoading);
  const result = useAIStore((state) => state.normalizationResult);
  const error = useAIStore((state) => state.normalizationError);

  return {
    loading,
    result,
    error,
  };
};

export const useProviderState = () => {
  const availableProviders = useAIStore((state) => state.availableProviders);
  const currentProvider = useAIStore((state) => state.currentProvider);

  return {
    availableProviders,
    currentProvider,
  };
};
