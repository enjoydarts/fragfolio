import React, { useEffect, useRef, useCallback } from 'react';

interface TurnstileWidgetProps {
  siteKey: string;
  onVerify: (token: string) => void;
  onError?: () => void;
  onExpire?: () => void;
  theme?: 'light' | 'dark' | 'auto';
  size?: 'normal' | 'compact';
}

interface TurnstileOptions {
  sitekey: string;
  callback: (token: string) => void;
  'error-callback'?: () => void;
  'expired-callback'?: () => void;
  theme?: 'light' | 'dark' | 'auto';
  size?: 'normal' | 'compact';
}

declare global {
  interface Window {
    turnstile?: {
      render: (
        element: string | HTMLElement,
        options: TurnstileOptions
      ) => string;
      reset: (widgetId?: string) => void;
      remove: (widgetId?: string) => void;
    };
  }
}

// Global script loading state
let scriptLoading = false;
let scriptLoaded = false;

export const TurnstileWidget: React.FC<TurnstileWidgetProps> = ({
  siteKey,
  onVerify,
  onError,
  onExpire,
  theme = 'auto',
  size = 'normal',
}) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const widgetIdRef = useRef<string | null>(null);
  const mountedRef = useRef<boolean>(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const renderTurnstile = useCallback(() => {
    if (
      !mountedRef.current ||
      !window.turnstile ||
      !containerRef.current ||
      widgetIdRef.current
    ) {
      return;
    }

    try {
      widgetIdRef.current = window.turnstile.render(containerRef.current, {
        sitekey: siteKey,
        callback: (token: string) => {
          if (mountedRef.current) {
            onVerify(token);
          }
        },
        'error-callback': () => {
          if (mountedRef.current) {
            onError?.();
          }
        },
        'expired-callback': () => {
          if (mountedRef.current) {
            onExpire?.();
          }
        },
        theme,
        size,
      });
    } catch (error) {
      console.error('Turnstile render error:', error);
    }
  }, [siteKey, onVerify, onError, onExpire, theme, size]);

  useEffect(() => {
    // Reset widget if it exists and key changes
    if (widgetIdRef.current && window.turnstile) {
      window.turnstile.remove(widgetIdRef.current);
      widgetIdRef.current = null;
    }

    if (window.turnstile) {
      renderTurnstile();
    } else if (!scriptLoaded && !scriptLoading) {
      scriptLoading = true;

      const script = document.createElement('script');
      script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
      script.async = true;
      script.defer = true;

      script.onload = () => {
        scriptLoaded = true;
        scriptLoading = false;
        if (mountedRef.current) {
          renderTurnstile();
        }
      };

      script.onerror = () => {
        scriptLoading = false;
        console.error('Failed to load Turnstile script');
      };

      document.head.appendChild(script);
    }

    return () => {
      if (widgetIdRef.current && window.turnstile) {
        try {
          window.turnstile.remove(widgetIdRef.current);
        } catch (error) {
          console.error('Error removing Turnstile widget:', error);
        }
        widgetIdRef.current = null;
      }
    };
  }, [renderTurnstile]);

  return <div ref={containerRef} />;
};
