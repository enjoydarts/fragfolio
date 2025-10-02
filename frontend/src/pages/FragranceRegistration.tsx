import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useToast } from '../hooks/useToast';
import { useAIStore, useNormalizationState } from '../stores/aiStore';
import { useAIProviders } from '../hooks/useAIProviders';
import SmartFragranceInput from '../components/ai/SmartFragranceInput';
import ConfidenceIndicator from '../components/ai/ConfidenceIndicator';

interface FragranceFormData {
  // 統一入力欄
  smartInput: string;
  // 個別入力欄
  brandName: string;
  brandNameEn: string;
  fragranceName: string;
  fragranceNameEn: string;
  volume: string;
  purchasePrice: string;
  purchaseDate: string;
  purchasePlace: string;
  possessionType: 'full_bottle' | 'decant' | 'sample';
  userRating: number;
  comments: string;
}

const FragranceRegistration: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { toast } = useToast();
  const { currentProvider } = useAIProviders();

  const {
    setNormalizationLoading,
    setNormalizationResult,
    setNormalizationError,
    resetNormalization,
  } = useAIStore();

  const {
    loading: normalizationLoading,
    result: normalizationResult,
    error: normalizationError,
  } = useNormalizationState();

  const [formData, setFormData] = useState<FragranceFormData>({
    smartInput: '',
    brandName: '',
    brandNameEn: '',
    fragranceName: '',
    fragranceNameEn: '',
    volume: '',
    purchasePrice: '',
    purchaseDate: '',
    purchasePlace: '',
    possessionType: 'full_bottle',
    userRating: 0,
    comments: '',
  });

  const [errors, setErrors] = useState<
    Partial<Record<keyof FragranceFormData, string>>
  >({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showNormalization, setShowNormalization] = useState(false);

  // 認証チェック
  useEffect(() => {
    if (!user) {
      navigate('/auth');
    }
  }, [user, navigate]);

  // フォーム入力の処理
  const handleInputChange =
    (field: keyof FragranceFormData) => (value: string | number) => {
      setFormData((prev) => ({
        ...prev,
        [field]: value,
      }));

      // エラーをクリア
      if (errors[field]) {
        setErrors((prev) => {
          const newErrors = { ...prev };
          delete newErrors[field];
          return newErrors;
        });
      }
    };

  // バリデーション
  const validateForm = (): boolean => {
    const newErrors: Partial<Record<keyof FragranceFormData, string>> = {};

    if (!formData.brandName.trim()) {
      newErrors.brandName = t('validation.required', {
        field: t('fragrance.brand_name'),
      });
    }

    if (!formData.fragranceName.trim()) {
      newErrors.fragranceName = t('validation.required', {
        field: t('fragrance.fragrance_name'),
      });
    }

    if (formData.volume && isNaN(parseFloat(formData.volume))) {
      newErrors.volume = t('validation.number', {
        field: t('fragrance.volume'),
      });
    }

    if (formData.purchasePrice && isNaN(parseFloat(formData.purchasePrice))) {
      newErrors.purchasePrice = t('validation.number', {
        field: t('fragrance.purchase_price'),
      });
    }

    if (formData.userRating < 0 || formData.userRating > 5) {
      newErrors.userRating = t('validation.rating_range');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  // AI正規化の実行
  const performNormalization = async () => {
    if (!formData.brandName.trim() || !formData.fragranceName.trim()) {
      toast.info(t('ai.normalization.missing_data'));
      return;
    }

    setNormalizationLoading(true);
    setNormalizationError(null);

    try {
      const response = await fetch(
        `${import.meta.env.VITE_API_BASE_URL}/api/ai/normalize`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${localStorage.getItem('token')}`,
          },
          body: JSON.stringify({
            brand_name: formData.brandName,
            fragrance_name: formData.fragranceName,
            language: 'ja',
            provider: currentProvider || 'openai',
          }),
        }
      );

      if (!response.ok) {
        throw new Error('Normalization failed');
      }

      const data = await response.json();

      if (data.success && data.data) {
        setNormalizationResult(data.data);
        setShowNormalization(true);
        toast.success(t('ai.normalization.success'));
      } else {
        throw new Error(data.message || 'Normalization failed');
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error';
      setNormalizationError(errorMessage);
      toast.error(t('ai.normalization.error'));
    } finally {
      setNormalizationLoading(false);
    }
  };

  // 正規化結果の適用
  const applyNormalization = () => {
    if (normalizationResult?.normalized_data) {
      const data = normalizationResult.normalized_data;
      setFormData((prev) => ({
        ...prev,
        // 日本語フィールド
        brandName: data.brand_name || prev.brandName,
        fragranceName: data.text || prev.fragranceName,
        // 英語フィールド
        brandNameEn: data.brand_name_en || prev.brandNameEn,
        fragranceNameEn: data.text_en || prev.fragranceNameEn,
      }));
      setShowNormalization(false);
      toast.success(t('fragrance.ai_quality_check.applied'));
    }
  };

  // フォーム送信
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      toast.error(t('validation.form_errors'));
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await fetch('/api/fragrances', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify({
          brand_name: formData.brandName,
          fragrance_name: formData.fragranceName,
          volume_ml: formData.volume ? parseFloat(formData.volume) : null,
          purchase_price: formData.purchasePrice
            ? parseFloat(formData.purchasePrice)
            : null,
          purchase_date: formData.purchaseDate || null,
          purchase_place: formData.purchasePlace || null,
          possession_type: formData.possessionType,
          user_rating: formData.userRating || null,
          comments: formData.comments || null,
        }),
      });

      if (!response.ok) {
        throw new Error('Registration failed');
      }

      const data = await response.json();

      if (data.success) {
        toast.success(t('fragrance.registration_success'));
        navigate('/collection');
      } else {
        throw new Error(data.message || 'Registration failed');
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error';
      toast.error(t('fragrance.registration_error'), errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  // コンポーネントのクリーンアップ
  useEffect(() => {
    return () => {
      resetNormalization();
    };
  }, [resetNormalization]);

  if (!user) {
    return null; // Loading or redirect
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="bg-white shadow-sm rounded-lg">
          <div className="px-4 py-5 sm:p-6">
            <div className="mb-6">
              <h1 className="text-2xl font-bold text-gray-900">
                {t('fragrance.register_title')}
              </h1>
              <p className="mt-1 text-sm text-gray-600">
                {t('fragrance.register_description')}
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              {/* スマート入力欄（AI正規化付き） */}
              <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <div className="flex items-center mb-4">
                  <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <span className="text-blue-600 text-lg">🧠</span>
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold text-gray-900">
                      {t('fragrance.smart_input.title')}
                    </h3>
                    <p className="text-sm text-gray-500">
                      {t('fragrance.smart_input.description')}
                    </p>
                  </div>
                  <div className="px-3 py-1 bg-blue-50 border border-blue-200 rounded-md">
                    <span className="text-xs font-medium text-blue-700">
                      {t('fragrance.smart_input.ai_badge')}
                    </span>
                  </div>
                </div>

                <SmartFragranceInput
                  value={formData.smartInput}
                  onChange={handleInputChange('smartInput')}
                  onNormalizationResult={(result) => {
                    setFormData((prev) => ({
                      ...prev,
                      brandName: result.brandName || prev.brandName,
                      brandNameEn: result.brandNameEn || prev.brandNameEn,
                      fragranceName: result.fragranceName || prev.fragranceName,
                      fragranceNameEn:
                        result.fragranceNameEn || prev.fragranceNameEn,
                    }));
                  }}
                  label={t('fragrance.smart_input.label')}
                  required
                  error={errors.smartInput}
                  className="w-full"
                />
              </div>

              {/* 詳細情報（自動入力済み・手動編集可能） */}
              <div className="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <div className="flex items-center mb-4">
                  <span className="text-2xl mr-3">📝</span>
                  <h3 className="text-lg font-semibold text-gray-900">
                    {t('fragrance.detailed_info.title')}
                  </h3>
                  <span className="ml-3 text-xs text-gray-600 bg-gray-100 px-3 py-1 rounded-full font-medium">
                    {t('fragrance.detailed_info.editable')}
                  </span>
                </div>
                <div className="space-y-8">
                  {/* ブランド名グループ */}
                  <div className="space-y-4">
                    <h4 className="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2">
                      {t('fragrance.detailed_info.brand_group')}
                    </h4>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          {t('fragrance.detailed_info.brand_ja')}
                          <span className="ml-2 text-red-500">*</span>
                        </label>
                        <input
                          type="text"
                          value={formData.brandName}
                          onChange={(e) =>
                            setFormData((prev) => ({
                              ...prev,
                              brandName: e.target.value,
                            }))
                          }
                          placeholder={t(
                            'fragrance.detailed_info.brand_ja_placeholder'
                          )}
                          className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900"
                        />
                        {errors.brandName && (
                          <p className="mt-1 text-sm text-red-600">
                            {errors.brandName}
                          </p>
                        )}
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          {t('fragrance.detailed_info.brand_en')}
                        </label>
                        <input
                          type="text"
                          value={formData.brandNameEn}
                          onChange={(e) =>
                            setFormData((prev) => ({
                              ...prev,
                              brandNameEn: e.target.value,
                            }))
                          }
                          placeholder={t(
                            'fragrance.detailed_info.brand_en_placeholder'
                          )}
                          className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900"
                        />
                      </div>
                    </div>
                  </div>

                  {/* 香水名グループ */}
                  <div className="space-y-4">
                    <h4 className="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2">
                      {t('fragrance.detailed_info.fragrance_group')}
                    </h4>
                    <div className="space-y-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          {t('fragrance.detailed_info.fragrance_ja')}
                          <span className="ml-2 text-red-500">*</span>
                        </label>
                        <input
                          type="text"
                          value={formData.fragranceName}
                          onChange={(e) =>
                            setFormData((prev) => ({
                              ...prev,
                              fragranceName: e.target.value,
                            }))
                          }
                          placeholder={t(
                            'fragrance.detailed_info.fragrance_ja_placeholder'
                          )}
                          className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white text-gray-900"
                        />
                        {errors.fragranceName && (
                          <p className="mt-1 text-sm text-red-600">
                            {errors.fragranceName}
                          </p>
                        )}
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          {t('fragrance.detailed_info.fragrance_en')}
                        </label>
                        <input
                          type="text"
                          value={formData.fragranceNameEn}
                          onChange={(e) =>
                            setFormData((prev) => ({
                              ...prev,
                              fragranceNameEn: e.target.value,
                            }))
                          }
                          placeholder={t(
                            'fragrance.detailed_info.fragrance_en_placeholder'
                          )}
                          className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white text-gray-900"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <div className="mt-4 text-xs text-gray-600 bg-gray-50 p-3 rounded-lg">
                  💡 上記の統一入力欄に入力すると、AI
                  が自動的にこれらのフィールドを埋めます。手動で編集することも可能です。
                </div>
              </div>

              {/* AI品質チェック & 正規化（手動実行） */}
              <div className="bg-blue-50 p-6 rounded-lg border border-blue-200 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center">
                    <span className="text-2xl mr-3">🤖</span>
                    <div>
                      <h3 className="text-lg font-semibold text-gray-900">
                        {t('fragrance.ai_quality_check.title')}
                      </h3>
                      <p className="text-sm text-gray-600 mt-1">
                        {t('fragrance.ai_quality_check.description')}
                      </p>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={performNormalization}
                    disabled={
                      normalizationLoading ||
                      !formData.brandName.trim() ||
                      !formData.fragranceName.trim()
                    }
                    className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-md transition-all duration-200 whitespace-nowrap"
                  >
                    {normalizationLoading ? (
                      <>
                        <div className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                        {t('fragrance.ai_quality_check.analyzing')}
                      </>
                    ) : (
                      <>
                        <span>🔍</span>
                        {t('fragrance.ai_quality_check.button')}
                      </>
                    )}
                  </button>
                </div>

                {normalizationError && (
                  <div className="mb-4 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                    <div className="flex items-center">
                      <span className="text-red-500 text-xl mr-3">❌</span>
                      <div>
                        <h4 className="text-red-800 font-medium">
                          {t('fragrance.ai_quality_check.error_occurred')}
                        </h4>
                        <p className="text-sm text-red-600 mt-1">
                          {normalizationError}
                        </p>
                      </div>
                    </div>
                  </div>
                )}

                {showNormalization && normalizationResult && (
                  <div className="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
                    <div className="flex items-center mb-4">
                      <span className="text-2xl mr-3">✅</span>
                      <h4 className="font-semibold text-gray-900 text-lg">
                        {t('fragrance.ai_quality_check.result_title')}
                      </h4>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                      {normalizationResult.normalized_data?.brand_name && (
                        <div className="bg-blue-50 p-4 rounded-lg">
                          <div className="text-sm text-blue-700 font-medium mb-1">
                            {t('fragrance.ai_quality_check.normalized_brand')}
                          </div>
                          <div className="text-lg font-semibold text-gray-900">
                            {normalizationResult.normalized_data.brand_name}
                          </div>
                          {normalizationResult.normalized_data.brand_name_en && (
                            <div className="text-sm text-gray-600 mt-1">
                              {t('fragrance.ai_quality_check.english_label')}{' '}
                              {normalizationResult.normalized_data.brand_name_en}
                            </div>
                          )}
                        </div>
                      )}

                      {normalizationResult.normalized_data?.text && (
                        <div className="bg-purple-50 p-4 rounded-lg">
                          <div className="text-sm text-purple-700 font-medium mb-1">
                            {t(
                              'fragrance.ai_quality_check.normalized_fragrance'
                            )}
                          </div>
                          <div className="text-lg font-semibold text-gray-900">
                            {normalizationResult.normalized_data.text}
                          </div>
                          {normalizationResult.normalized_data.text_en && (
                            <div className="text-sm text-gray-600 mt-1">
                              {t('fragrance.ai_quality_check.english_label')}{' '}
                              {normalizationResult.normalized_data.text_en}
                            </div>
                          )}
                        </div>
                      )}
                    </div>

                    {/* 実在確認警告 */}
                    {normalizationResult.normalized_data?.exists === false && (
                      <div className="mb-5 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                        <div className="flex items-center">
                          <span className="text-red-500 text-xl mr-3">⚠️</span>
                          <div>
                            <h4 className="text-red-800 font-medium">
                              {t('fragrance.ai_quality_check.not_exists_title')}
                            </h4>
                            <p className="text-sm text-red-600 mt-1">
                              {t('fragrance.ai_quality_check.not_exists_message')}
                            </p>
                            {normalizationResult.normalized_data.rationale_brief && (
                              <p className="text-sm text-red-700 mt-2 italic">
                                {normalizationResult.normalized_data.rationale_brief}
                              </p>
                            )}
                          </div>
                        </div>
                      </div>
                    )}

                    <div className="mb-5">
                      <div className="flex items-center gap-3 mb-3">
                        <span className="text-lg">📊</span>
                        <span className="font-medium text-gray-900">
                          {t('fragrance.ai_quality_check.confidence_score')}
                        </span>
                      </div>
                      <ConfidenceIndicator
                        confidence={normalizationResult.normalized_data?.confidence || 0.5}
                        size="lg"
                        showLabel={true}
                        showPercentage={true}
                        className="w-full"
                      />
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3">
                      <button
                        type="button"
                        onClick={applyNormalization}
                        className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium shadow-md transition-all duration-200 whitespace-nowrap"
                      >
                        <span>✅</span>
                        {t('fragrance.ai_quality_check.apply_button')}
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowNormalization(false)}
                        className="flex-1 sm:flex-none px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium transition-all duration-200 whitespace-nowrap"
                      >
                        {t('common.cancel')}
                      </button>
                    </div>
                  </div>
                )}

                {!showNormalization && !normalizationError && (
                  <div className="text-sm text-gray-600 bg-white p-3 rounded-lg border border-gray-200">
                    {t('fragrance.ai_quality_check.info_message')}
                  </div>
                )}
              </div>

              {/* その他の入力フィールド */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {t('fragrance.volume')} (ml)
                  </label>
                  <input
                    type="number"
                    value={formData.volume}
                    onChange={(e) =>
                      handleInputChange('volume')(e.target.value)
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="50"
                  />
                  {errors.volume && (
                    <p className="mt-1 text-sm text-red-600">{errors.volume}</p>
                  )}
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {t('fragrance.purchase_price')} (¥)
                  </label>
                  <input
                    type="number"
                    value={formData.purchasePrice}
                    onChange={(e) =>
                      handleInputChange('purchasePrice')(e.target.value)
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                    placeholder="10000"
                  />
                  {errors.purchasePrice && (
                    <p className="mt-1 text-sm text-red-600">
                      {errors.purchasePrice}
                    </p>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {t('fragrance.purchase_date')}
                  </label>
                  <input
                    type="date"
                    value={formData.purchaseDate}
                    onChange={(e) =>
                      handleInputChange('purchaseDate')(e.target.value)
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {t('fragrance.possession_type')}
                  </label>
                  <select
                    value={formData.possessionType}
                    onChange={(e) =>
                      handleInputChange('possessionType')(e.target.value)
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  >
                    <option value="full_bottle">
                      {t('fragrance.full_bottle')}
                    </option>
                    <option value="decant">{t('fragrance.decant')}</option>
                    <option value="sample">{t('fragrance.sample')}</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  {t('fragrance.purchase_place')}
                </label>
                <input
                  type="text"
                  value={formData.purchasePlace}
                  onChange={(e) =>
                    handleInputChange('purchasePlace')(e.target.value)
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder={t('fragrance.purchase_place_placeholder')}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  {t('fragrance.rating')} (1-5)
                </label>
                <div className="flex space-x-1">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button
                      key={star}
                      type="button"
                      onClick={() => handleInputChange('userRating')(star)}
                      className={`w-8 h-8 ${
                        star <= formData.userRating
                          ? 'text-yellow-400'
                          : 'text-gray-300'
                      }`}
                    >
                      ★
                    </button>
                  ))}
                </div>
                {errors.userRating && (
                  <p className="mt-1 text-sm text-red-600">
                    {errors.userRating}
                  </p>
                )}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  {t('fragrance.comments')}
                </label>
                <textarea
                  value={formData.comments}
                  onChange={(e) =>
                    handleInputChange('comments')(e.target.value)
                  }
                  rows={4}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder={t('fragrance.comments_placeholder')}
                />
              </div>

              {/* 送信ボタン */}
              <div className="flex justify-end space-x-3">
                <button
                  type="button"
                  onClick={() => navigate('/collection')}
                  className="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300"
                >
                  {t('common.cancel')}
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isSubmitting
                    ? t('fragrance.registering')
                    : t('fragrance.register')}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};

export default FragranceRegistration;
