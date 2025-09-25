import { useState, useCallback } from 'react';

export type ToastType = 'success' | 'error' | 'info';

interface ToastItem {
  id: string;
  type: ToastType;
  title: string;
  message?: string;
  duration?: number;
}

export const useToast = () => {
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const addToast = useCallback((toast: Omit<ToastItem, 'id'>) => {
    const id = Math.random().toString(36).substr(2, 9);
    setToasts((prev) => [...prev, { ...toast, id }]);
  }, []);

  const removeToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((toast) => toast.id !== id));
  }, []);

  const toast = {
    success: (title: string, message?: string, duration: number = 4000) => {
      addToast({ type: 'success', title, message, duration });
    },
    error: (title: string, message?: string, duration: number = 0) => {
      addToast({ type: 'error', title, message, duration });
    },
    info: (title: string, message?: string, duration: number = 5000) => {
      addToast({ type: 'info', title, message, duration });
    },
  };

  return {
    toasts,
    toast,
    removeToast,
  };
};
