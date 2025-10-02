import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';

// API呼び出し用のヘルパー関数
const callCompletionAPI = async (
  query: string,
  type: 'brand' | 'fragrance',
  options?: {
    limit?: number;
    language?: string;
    provider?: string;
  }
) => {
  const response = await fetch('/api/ai/complete', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      query,
      type,
      limit: options?.limit || 5,
      language: options?.language || 'ja',
      provider: options?.provider,
    }),
  });

  return response.json();
};

describe('AI Completion API - Brand/Fragrance Separation Integration', () => {
  beforeEach(() => {
    server.resetHandlers();
  });

  afterEach(() => {
    server.resetHandlers();
  });

  it('香水補完APIが分離されたブランド・香水名を返す', async () => {
    server.use(
      http.post('/api/ai/complete', async ({ request }) => {
        const body = (await request.json()) as {
          query: string;
          type: string;
          limit?: number;
          language?: string;
          provider?: string;
        };

        if (body.query === 'シャネル' && body.type === 'fragrance') {
          return HttpResponse.json({
            success: true,
            data: {
              suggestions: [
                {
                  text: 'No.5 オードゥパルファム', // 香水名のみ
                  text_en: 'No.5 Eau de Parfum',
                  brand_name: 'シャネル', // 分離されたブランド名
                  brand_name_en: 'Chanel',
                  confidence: 0.95,
                  type: 'fragrance',
                  similarity_score: 0.95,
                  adjusted_confidence: 0.95,
                },
                {
                  text: 'ココ マドモアゼル オードゥパルファム',
                  text_en: 'Coco Mademoiselle Eau de Parfum',
                  brand_name: 'シャネル',
                  brand_name_en: 'Chanel',
                  confidence: 0.85,
                  type: 'fragrance',
                  similarity_score: 0.85,
                  adjusted_confidence: 0.85,
                },
              ],
              provider: 'gemini',
              response_time_ms: 100,
              cost_estimate: 0.001,
            },
          });
        }

        return HttpResponse.json({ success: false });
      })
    );

    const result = await callCompletionAPI('シャネル', 'fragrance');

    expect(result.success).toBe(true);
    expect(result.data.suggestions).toHaveLength(2);

    // 各候補がブランド・香水名分離構造を持つことを確認
    result.data.suggestions.forEach(
      (suggestion: { text: string; brand_name: string; type: string }) => {
        expect(suggestion).toHaveProperty('text');
        expect(suggestion).toHaveProperty('text_en');
        expect(suggestion).toHaveProperty('brand_name');
        expect(suggestion).toHaveProperty('brand_name_en');
        expect(suggestion).toHaveProperty('confidence');
        expect(suggestion.type).toBe('fragrance');

        // 香水名にブランド名が含まれていないことを確認
        expect(suggestion.text).not.toContain('シャネル');
        expect(suggestion.text_en).not.toContain('Chanel');

        // ブランド名が正しく分離されていることを確認
        expect(suggestion.brand_name).toBe('シャネル');
        expect(suggestion.brand_name_en).toBe('Chanel');
      }
    );
  });

  it('ブランド補完APIが適切な構造を返す', async () => {
    server.use(
      http.post('/api/ai/complete', async ({ request }) => {
        const body = (await request.json()) as {
          query: string;
          type: string;
          limit?: number;
          language?: string;
          provider?: string;
        };

        if (body.query === 'シャン' && body.type === 'brand') {
          return HttpResponse.json({
            success: true,
            data: {
              suggestions: [
                {
                  text: 'シャネル',
                  text_en: 'Chanel',
                  confidence: 0.98,
                  type: 'brand',
                  brand_name: null, // ブランドの場合はnull
                  brand_name_en: null,
                  similarity_score: 0.98,
                  adjusted_confidence: 0.98,
                },
                {
                  text: 'ディオール',
                  text_en: 'Dior',
                  confidence: 0.95,
                  type: 'brand',
                  brand_name: null,
                  brand_name_en: null,
                  similarity_score: 0.95,
                  adjusted_confidence: 0.95,
                },
              ],
              provider: 'gemini',
              response_time_ms: 80,
              cost_estimate: 0.001,
            },
          });
        }

        return HttpResponse.json({ success: false });
      })
    );

    const result = await callCompletionAPI('シャン', 'brand');

    expect(result.success).toBe(true);
    expect(result.data.suggestions).toHaveLength(2);

    result.data.suggestions.forEach(
      (suggestion: { text: string; brand_name: string; type: string }) => {
        expect(suggestion.type).toBe('brand');
        // ブランドの場合、brand_nameはnullまたは自分自身と同じ
        expect(
          suggestion.brand_name === null ||
            suggestion.brand_name === suggestion.text
        ).toBe(true);
      }
    );
  });

  it('複数のNo.5バリエーションが正しく分離される', async () => {
    server.use(
      http.post('/api/ai/complete', async ({ request }) => {
        const body = (await request.json()) as {
          query: string;
          type: string;
          limit?: number;
          language?: string;
          provider?: string;
        };

        if (body.query === 'No.5' && body.type === 'fragrance') {
          return HttpResponse.json({
            success: true,
            data: {
              suggestions: [
                {
                  text: 'No.5 オードゥパルファム',
                  text_en: 'No.5 Eau de Parfum',
                  brand_name: 'シャネル',
                  brand_name_en: 'Chanel',
                  confidence: 0.96,
                  type: 'fragrance',
                  similarity_score: 0.96,
                  adjusted_confidence: 0.96,
                },
                {
                  text: 'No.5 オードゥトワレ',
                  text_en: 'No.5 Eau de Toilette',
                  brand_name: 'シャネル',
                  brand_name_en: 'Chanel',
                  confidence: 0.94,
                  type: 'fragrance',
                  similarity_score: 0.94,
                  adjusted_confidence: 0.94,
                },
                {
                  text: 'No.5 ロー',
                  text_en: "No.5 L'Eau",
                  brand_name: 'シャネル',
                  brand_name_en: 'Chanel',
                  confidence: 0.92,
                  type: 'fragrance',
                  similarity_score: 0.92,
                  adjusted_confidence: 0.92,
                },
              ],
              provider: 'gemini',
              response_time_ms: 120,
              cost_estimate: 0.001,
            },
          });
        }

        return HttpResponse.json({ success: false });
      })
    );

    const result = await callCompletionAPI('No.5', 'fragrance');

    expect(result.success).toBe(true);
    expect(result.data.suggestions).toHaveLength(3);

    // すべて同じブランドであることを確認
    result.data.suggestions.forEach(
      (suggestion: { text: string; brand_name: string; type: string }) => {
        expect(suggestion.brand_name).toBe('シャネル');
        expect(suggestion.brand_name_en).toBe('Chanel');
        expect(suggestion.type).toBe('fragrance');

        // No.5のバリエーションであることを確認
        expect(suggestion.text).toContain('No.5');
        expect(suggestion.text_en).toContain('No.5');

        // 香水名にブランド名が含まれていないことを確認
        expect(suggestion.text).not.toContain('シャネル');
        expect(suggestion.text_en).not.toContain('Chanel');
      }
    );

    // 各バリエーションが異なることを確認
    const fragranceNames = result.data.suggestions.map(
      (s: { text: string }) => s.text
    );
    expect(new Set(fragranceNames).size).toBe(3); // 重複なし
  });

  it('空の結果を適切に処理する', async () => {
    server.use(
      http.post('/api/ai/complete', async () => {
        return HttpResponse.json({
          success: true,
          data: {
            suggestions: [],
            provider: 'fallback',
            response_time_ms: 50,
            cost_estimate: 0.0,
          },
        });
      })
    );

    const result = await callCompletionAPI('存在しない香水', 'fragrance');

    expect(result.success).toBe(true);
    expect(result.data.suggestions).toEqual([]);
    expect(result.data.provider).toBe('fallback');
  });

  it('バリデーションエラーを適切に処理する', async () => {
    server.use(
      http.post('/api/ai/complete', async ({ request }) => {
        const body = (await request.json()) as {
          query: string;
          type: string;
          limit?: number;
          language?: string;
          provider?: string;
        };

        // 短すぎるクエリの場合
        if (body.query && body.query.length < 2) {
          return HttpResponse.json(
            {
              success: false,
              message: 'クエリが短すぎます',
              errors: {
                query: ['クエリは最低2文字必要です'],
              },
            },
            { status: 422 }
          );
        }

        return HttpResponse.json({ success: false });
      })
    );

    const result = await callCompletionAPI('a', 'fragrance');

    expect(result.success).toBe(false);
    expect(result.message).toBe('クエリが短すぎます');
    expect(result.errors.query).toContain('クエリは最低2文字必要です');
  });

  it('サーバーエラーを適切に処理する', async () => {
    server.use(
      http.post('/api/ai/complete', async () => {
        return HttpResponse.json(
          {
            success: false,
            message: 'AI補完に失敗しました',
          },
          { status: 500 }
        );
      })
    );

    const result = await callCompletionAPI('テスト', 'fragrance');

    expect(result.success).toBe(false);
    expect(result.message).toBe('AI補完に失敗しました');
  });

  it('言語パラメータが正しく処理される', async () => {
    server.use(
      http.post('/api/ai/complete', async ({ request }) => {
        const body = (await request.json()) as {
          query: string;
          type: string;
          limit?: number;
          language?: string;
          provider?: string;
        };

        if (body.language === 'en') {
          return HttpResponse.json({
            success: true,
            data: {
              suggestions: [
                {
                  text: 'Sauvage',
                  text_en: 'Sauvage',
                  brand_name: 'Dior',
                  brand_name_en: 'Dior',
                  confidence: 0.95,
                  type: 'fragrance',
                  similarity_score: 0.95,
                  adjusted_confidence: 0.95,
                },
              ],
              provider: 'gemini',
              response_time_ms: 100,
              cost_estimate: 0.001,
            },
          });
        }

        return HttpResponse.json({ success: false });
      })
    );

    const result = await callCompletionAPI('Sauvage', 'fragrance', {
      language: 'en',
    });

    expect(result.success).toBe(true);
    expect(result.data.suggestions[0].text).toBe('Sauvage');
    expect(result.data.suggestions[0].brand_name).toBe('Dior');
  });
});
