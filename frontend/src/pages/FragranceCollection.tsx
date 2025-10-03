import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useToast } from '../hooks/useToast';

interface Fragrance {
  id: number;
  name_ja: string;
  name_en: string;
  brand: {
    id: number;
    name_ja: string;
    name_en: string;
  };
}

interface UserFragrance {
  id: number;
  fragrance_id: number;
  purchase_date: string | null;
  volume_ml: number | null;
  purchase_price: number | null;
  purchase_place: string | null;
  current_volume_ml: number | null;
  possession_type: 'full_bottle' | 'decant' | 'sample';
  duration_hours: number | null;
  projection: 'weak' | 'moderate' | 'strong' | null;
  user_rating: number | null;
  comments: string | null;
  fragrance: Fragrance;
  tags: Array<{ id: number; tag_name: string }>;
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

const FragranceCollection: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { toast } = useToast();

  const [fragrances, setFragrances] = useState<UserFragrance[]>([]);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState<PaginationMeta | null>(null);
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    if (!user) {
      navigate('/auth');
      return;
    }

    fetchFragrances();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user, navigate, currentPage]);

  const fetchFragrances = async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `${import.meta.env.VITE_API_BASE_URL}/api/fragrances?page=${currentPage}`,
        {
          headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch fragrances');
      }

      const data = await response.json();

      if (data.success && data.data) {
        setFragrances(data.data.data || []);
        setPagination({
          current_page: data.data.current_page,
          last_page: data.data.last_page,
          per_page: data.data.per_page,
          total: data.data.total,
        });
      }
    } catch {
      toast.error(t('fragrance.fetch_error'));
    } finally {
      setLoading(false);
    }
  };

  const getPossessionTypeLabel = (type: string) => {
    switch (type) {
      case 'full_bottle':
        return t('fragrance.full_bottle');
      case 'decant':
        return t('fragrance.decant');
      case 'sample':
        return t('fragrance.sample');
      default:
        return type;
    }
  };

  const getProjectionLabel = (projection: string | null) => {
    if (!projection) return '-';
    switch (projection) {
      case 'weak':
        return t('fragrance.projection_weak');
      case 'moderate':
        return t('fragrance.projection_moderate');
      case 'strong':
        return t('fragrance.projection_strong');
      default:
        return projection;
    }
  };

  if (!user) {
    return null;
  }

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* ヘッダー */}
        <div className="mb-8">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                {t('fragrance.my_collection')}
              </h1>
              <p className="mt-2 text-sm text-gray-600">
                {pagination
                  ? t('fragrance.collection_count', { count: pagination.total })
                  : ''}
              </p>
            </div>
            <button
              onClick={() => navigate('/register')}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
            >
              <span>➕</span>
              {t('fragrance.add_new')}
            </button>
          </div>
        </div>

        {/* ローディング */}
        {loading && (
          <div className="flex justify-center items-center py-12">
            <div className="animate-spin w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full"></div>
          </div>
        )}

        {/* 空の状態 */}
        {!loading && fragrances.length === 0 && (
          <div className="bg-white rounded-lg shadow-sm p-12 text-center">
            <div className="text-6xl mb-4">🌸</div>
            <h2 className="text-xl font-semibold text-gray-900 mb-2">
              {t('fragrance.no_fragrances')}
            </h2>
            <p className="text-gray-600 mb-6">
              {t('fragrance.no_fragrances_description')}
            </p>
            <button
              onClick={() => navigate('/register')}
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              {t('fragrance.add_first_fragrance')}
            </button>
          </div>
        )}

        {/* 香水一覧 */}
        {!loading && fragrances.length > 0 && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {fragrances.map((userFragrance) => (
              <div
                key={userFragrance.id}
                className="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden"
              >
                <div className="p-6">
                  {/* ブランド名 */}
                  <div className="text-sm text-blue-600 font-medium mb-1">
                    {userFragrance.fragrance.brand.name_ja}
                  </div>

                  {/* 香水名 */}
                  <h3 className="text-xl font-bold text-gray-900 mb-2">
                    {userFragrance.fragrance.name_ja}
                  </h3>

                  {/* 評価 */}
                  {userFragrance.user_rating && (
                    <div className="flex items-center mb-3">
                      {[...Array(5)].map((_, i) => (
                        <span
                          key={i}
                          className={`text-lg ${
                            i < userFragrance.user_rating!
                              ? 'text-yellow-400'
                              : 'text-gray-300'
                          }`}
                        >
                          ★
                        </span>
                      ))}
                    </div>
                  )}

                  {/* 所有タイプ */}
                  <div className="mb-4">
                    <span className="inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
                      {getPossessionTypeLabel(userFragrance.possession_type)}
                    </span>
                  </div>

                  {/* 詳細情報 */}
                  <div className="space-y-2 text-sm text-gray-600">
                    {userFragrance.volume_ml && (
                      <div className="flex items-center gap-2">
                        <span className="text-gray-400">💧</span>
                        <span>
                          {userFragrance.volume_ml} ml
                          {userFragrance.current_volume_ml && (
                            <span className="text-gray-400">
                              {' '}
                              (残り: {userFragrance.current_volume_ml} ml)
                            </span>
                          )}
                        </span>
                      </div>
                    )}

                    {userFragrance.projection && (
                      <div className="flex items-center gap-2">
                        <span className="text-gray-400">📡</span>
                        <span>
                          {t('fragrance.projection')}:{' '}
                          {getProjectionLabel(userFragrance.projection)}
                        </span>
                      </div>
                    )}

                    {userFragrance.duration_hours && (
                      <div className="flex items-center gap-2">
                        <span className="text-gray-400">⏱️</span>
                        <span>
                          {t('fragrance.duration')}:{' '}
                          {userFragrance.duration_hours} {t('fragrance.hours')}
                        </span>
                      </div>
                    )}

                    {userFragrance.purchase_date && (
                      <div className="flex items-center gap-2">
                        <span className="text-gray-400">📅</span>
                        <span>
                          {new Date(
                            userFragrance.purchase_date
                          ).toLocaleDateString()}
                        </span>
                      </div>
                    )}
                  </div>

                  {/* タグ */}
                  {userFragrance.tags && userFragrance.tags.length > 0 && (
                    <div className="mt-4 flex flex-wrap gap-2">
                      {userFragrance.tags.map((tag) => (
                        <span
                          key={tag.id}
                          className="px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs"
                        >
                          #{tag.tag_name}
                        </span>
                      ))}
                    </div>
                  )}

                  {/* コメント */}
                  {userFragrance.comments && (
                    <div className="mt-4 pt-4 border-t border-gray-200">
                      <p className="text-sm text-gray-600 line-clamp-2">
                        {userFragrance.comments}
                      </p>
                    </div>
                  )}
                </div>

                {/* アクションボタン */}
                <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                  <button
                    onClick={() =>
                      navigate(`/fragrance/${userFragrance.id}/edit`)
                    }
                    className="w-full text-sm text-blue-600 hover:text-blue-700 font-medium"
                  >
                    {t('common.edit')}
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* ページネーション */}
        {pagination && pagination.last_page > 1 && (
          <div className="mt-8 flex justify-center gap-2">
            <button
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              disabled={currentPage === 1}
              className="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {t('common.previous')}
            </button>
            <span className="px-4 py-2 bg-white border border-gray-300 rounded-lg">
              {currentPage} / {pagination.last_page}
            </span>
            <button
              onClick={() =>
                setCurrentPage((p) => Math.min(pagination.last_page, p + 1))
              }
              disabled={currentPage === pagination.last_page}
              className="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {t('common.next')}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default FragranceCollection;
