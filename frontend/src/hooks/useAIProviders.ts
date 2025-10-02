import { useEffect } from 'react';
import { useAIStore } from '../stores/aiStore';

export const useAIProviders = () => {
  const { setAvailableProviders, setCurrentProvider, currentProvider } =
    useAIStore();

  useEffect(() => {
    const fetchProviders = async () => {
      try {
        const response = await fetch(
          `${import.meta.env.VITE_API_BASE_URL}/api/ai/providers`
        );

        if (response.ok) {
          const data = await response.json();

          if (data.success && data.data) {
            const availableProviders = data.data.providers
              .filter((provider: { available: boolean }) => provider.available)
              .map((provider: { name: string }) => provider.name);

            setAvailableProviders(availableProviders);

            // バックエンドのデフォルトプロバイダーを常に設定
            if (data.data.default) {
              setCurrentProvider(data.data.default);
            }
          }
        }
      } catch (error) {
        console.warn('Failed to fetch AI providers:', error);
        // フォールバック設定
        setAvailableProviders(['openai', 'anthropic', 'gemini']);
        if (!currentProvider) {
          setCurrentProvider('gemini');
        }
      }
    };

    fetchProviders();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return { currentProvider };
};
