import React from 'react';

export const Test: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <div className="max-w-4xl mx-auto space-y-8">
        <div className="card">
          <div className="p-8">
            <h1 className="text-3xl font-bold text-gray-900 mb-6">
              デザインシステム テスト
            </h1>

            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-3">
                  ボタン
                </h2>
                <div className="flex space-x-4">
                  <button className="btn-primary">プライマリボタン</button>
                  <button className="btn-secondary">セカンダリボタン</button>
                </div>
              </div>

              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-3">
                  インプット
                </h2>
                <div className="space-y-3 max-w-sm">
                  <input
                    type="text"
                    placeholder="テキスト入力"
                    className="input-field w-full"
                  />
                  <input
                    type="email"
                    placeholder="メールアドレス"
                    className="input-field w-full"
                  />
                </div>
              </div>

              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-3">
                  カード
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="card">
                    <div className="p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-2">
                        通常カード
                      </h3>
                      <p className="text-gray-600">
                        このカードにホバーすると、エフェクトが適用されます。
                      </p>
                    </div>
                  </div>

                  <div className="card feature-card">
                    <div className="p-6">
                      <h3 className="text-lg font-semibold text-gray-900 mb-2">
                        フィーチャーカード
                      </h3>
                      <p className="text-gray-600">
                        このカードには上部にアクセントラインが表示されます。
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="mt-8 p-6 bg-orange-50 border border-orange-200 rounded-xl">
                <div className="flex items-center">
                  <div className="w-8 h-8 bg-orange-600 rounded-lg flex items-center justify-center mr-3">
                    <svg
                      className="w-4 h-4 text-white"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M5 13l4 4L19 7"
                      />
                    </svg>
                  </div>
                  <p className="text-orange-800 font-medium">
                    Tailwind CSS v4が正常に動作しています！
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
