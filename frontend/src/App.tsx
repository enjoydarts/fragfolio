import { useTranslation } from 'react-i18next';

function App() {
  const { t, i18n } = useTranslation();

  const toggleLanguage = () => {
    i18n.changeLanguage(i18n.language === 'ja' ? 'en' : 'ja');
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow">π
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <h1 className="text-2xl font-bold text-gray-900">
                {t('app.title')}
              </h1>
            </div>
            <div className="flex items-center space-x-4">
              <button
                onClick={toggleLanguage}
                className="text-gray-600 hover:text-gray-900 text-sm font-medium"
              >
                {i18n.language === 'ja' ? 'English' : '日本語'}
              </button>
              <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                {t('nav.login')}
              </button>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          <div className="text-center">
            <h2 className="text-3xl font-extrabold text-gray-900 sm:text-4xl">
              {t('app.subtitle')}
            </h2>
            <p className="mt-4 max-w-2xl mx-auto text-xl text-gray-500">
              {t('app.description')}
            </p>
          </div>

          <div className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div className="bg-white overflow-hidden shadow rounded-lg">
              <div className="p-6">
                <h3 className="text-lg font-medium text-gray-900">
                  {t('features.collection.title')}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  {t('features.collection.description')}
                </p>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow rounded-lg">
              <div className="p-6">
                <h3 className="text-lg font-medium text-gray-900">
                  {t('features.ai_search.title')}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  {t('features.ai_search.description')}
                </p>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow rounded-lg">
              <div className="p-6">
                <h3 className="text-lg font-medium text-gray-900">
                  {t('features.wearing_log.title')}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  {t('features.wearing_log.description')}
                </p>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}

export default App;
