import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import {
  useAIStore,
  type CompletionSuggestion,
} from '../../src/stores/aiStore';

describe('AI Store - Brand/Fragrance Separation', () => {
  beforeEach(() => {
    // ストアをリセット
    const { result } = renderHook(() => useAIStore());
    act(() => {
      result.current.setBrandSuggestions([]);
      result.current.setFragranceSuggestions([]);
      result.current.setNormalizationResult(null);
      result.current.setNormalizationError(null);
      result.current.setCompletionLoading(false);
      result.current.setNormalizationLoading(false);
    });
  });

  it('初期状態が正しく設定される', () => {
    const { result } = renderHook(() => useAIStore());

    expect(result.current.completionLoading).toBe(false);
    expect(result.current.brandSuggestions).toEqual([]);
    expect(result.current.fragranceSuggestions).toEqual([]);
    expect(result.current.normalizationLoading).toBe(false);
    expect(result.current.normalizationResult).toBeNull();
    expect(result.current.normalizationError).toBeNull();
  });

  it('分離されたブランド・香水名の構造を正しく保存する', () => {
    const { result } = renderHook(() => useAIStore());

    const mockFragranceSuggestions: CompletionSuggestion[] = [
      {
        text: 'No.5 オードゥパルファム', // 香水名のみ
        textEn: 'No.5 Eau de Parfum',
        brandName: 'シャネル', // 分離されたブランド名
        brandNameEn: 'Chanel',
        confidence: 0.95,
        type: 'fragrance',
      },
      {
        text: 'ココ マドモアゼル',
        textEn: 'Coco Mademoiselle',
        brandName: 'シャネル',
        brandNameEn: 'Chanel',
        confidence: 0.92,
        type: 'fragrance',
      },
    ];

    act(() => {
      result.current.setFragranceSuggestions(mockFragranceSuggestions);
    });

    expect(result.current.fragranceSuggestions).toHaveLength(2);
    expect(result.current.fragranceSuggestions[0]).toEqual({
      text: 'No.5 オードゥパルファム',
      textEn: 'No.5 Eau de Parfum',
      brandName: 'シャネル',
      brandNameEn: 'Chanel',
      confidence: 0.95,
      type: 'fragrance',
    });

    // 香水名にブランド名が含まれていないことを確認
    expect(result.current.fragranceSuggestions[0].text).not.toContain(
      'シャネル'
    );
    expect(result.current.fragranceSuggestions[0].textEn).not.toContain(
      'Chanel'
    );
  });

  it('ブランド補完の構造を正しく保存する', () => {
    const { result } = renderHook(() => useAIStore());

    const mockBrandSuggestions: CompletionSuggestion[] = [
      {
        text: 'シャネル',
        textEn: 'Chanel',
        confidence: 0.98,
        type: 'brand',
        // ブランドの場合はbrandNameは通常null
        brandName: undefined,
        brandNameEn: undefined,
      },
      {
        text: 'ディオール',
        textEn: 'Dior',
        confidence: 0.95,
        type: 'brand',
        brandName: undefined,
        brandNameEn: undefined,
      },
    ];

    act(() => {
      result.current.setBrandSuggestions(mockBrandSuggestions);
    });

    expect(result.current.brandSuggestions).toHaveLength(2);
    expect(result.current.brandSuggestions[0]).toEqual({
      text: 'シャネル',
      textEn: 'Chanel',
      confidence: 0.98,
      type: 'brand',
      brandName: undefined,
      brandNameEn: undefined,
    });
  });

  it('同一ブランドの複数香水バリエーションを正しく保存する', () => {
    const { result } = renderHook(() => useAIStore());

    const mockNo5Variations: CompletionSuggestion[] = [
      {
        text: 'No.5 オードゥパルファム',
        textEn: 'No.5 Eau de Parfum',
        brandName: 'シャネル',
        brandNameEn: 'Chanel',
        confidence: 0.96,
        type: 'fragrance',
      },
      {
        text: 'No.5 オードゥトワレ',
        textEn: 'No.5 Eau de Toilette',
        brandName: 'シャネル',
        brandNameEn: 'Chanel',
        confidence: 0.94,
        type: 'fragrance',
      },
      {
        text: 'No.5 ロー',
        textEn: "No.5 L'Eau",
        brandName: 'シャネル',
        brandNameEn: 'Chanel',
        confidence: 0.92,
        type: 'fragrance',
      },
    ];

    act(() => {
      result.current.setFragranceSuggestions(mockNo5Variations);
    });

    expect(result.current.fragranceSuggestions).toHaveLength(3);

    // すべて同じブランドであることを確認
    result.current.fragranceSuggestions.forEach((suggestion) => {
      expect(suggestion.brandName).toBe('シャネル');
      expect(suggestion.brandNameEn).toBe('Chanel');
      expect(suggestion.type).toBe('fragrance');
    });

    // 香水名はそれぞれ異なることを確認
    const fragranceNames = result.current.fragranceSuggestions.map(
      (s) => s.text
    );
    expect(fragranceNames).toEqual([
      'No.5 オードゥパルファム',
      'No.5 オードゥトワレ',
      'No.5 ロー',
    ]);

    // 全ての香水名にブランド名が含まれていないことを確認
    result.current.fragranceSuggestions.forEach((suggestion) => {
      expect(suggestion.text).not.toContain('シャネル');
      expect(suggestion.textEn).not.toContain('Chanel');
    });
  });

  it('正規化結果を正しく保存する', () => {
    const { result } = renderHook(() => useAIStore());

    const mockNormalizationResult = {
      normalized_brand: 'シャネル',
      normalized_fragrance_name: 'No.5 オードゥパルファム',
      final_confidence_score: 0.95,
    };

    act(() => {
      result.current.setNormalizationResult(mockNormalizationResult);
    });

    expect(result.current.normalizationResult).toEqual(mockNormalizationResult);
  });

  it('ローディング状態を正しく管理する', () => {
    const { result } = renderHook(() => useAIStore());

    // 補完ローディング
    act(() => {
      result.current.setCompletionLoading(true);
    });
    expect(result.current.completionLoading).toBe(true);

    act(() => {
      result.current.setCompletionLoading(false);
    });
    expect(result.current.completionLoading).toBe(false);

    // 正規化ローディング
    act(() => {
      result.current.setNormalizationLoading(true);
    });
    expect(result.current.normalizationLoading).toBe(true);

    act(() => {
      result.current.setNormalizationLoading(false);
    });
    expect(result.current.normalizationLoading).toBe(false);
  });

  it('エラー状態を正しく管理する', () => {
    const { result } = renderHook(() => useAIStore());

    const mockError = 'AI補完に失敗しました';

    act(() => {
      result.current.setNormalizationError(mockError);
    });

    expect(result.current.normalizationError).toBe(mockError);

    act(() => {
      result.current.setNormalizationError(null);
    });

    expect(result.current.normalizationError).toBeNull();
  });

  it('候補リストの型チェックが正しく機能する', () => {
    const { result } = renderHook(() => useAIStore());

    // 正しい型の候補
    const validSuggestion: CompletionSuggestion = {
      text: 'テスト香水',
      textEn: 'Test Fragrance',
      brandName: 'テストブランド',
      brandNameEn: 'Test Brand',
      confidence: 0.85,
      type: 'fragrance',
    };

    act(() => {
      result.current.setFragranceSuggestions([validSuggestion]);
    });

    expect(result.current.fragranceSuggestions[0]).toEqual(validSuggestion);
    expect(result.current.fragranceSuggestions[0].type).toBe('fragrance');
  });
});
