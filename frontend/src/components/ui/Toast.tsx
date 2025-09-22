import React, { useState, useEffect } from 'react';
import { CheckCircleIcon, ExclamationCircleIcon, XMarkIcon } from '@heroicons/react/24/outline';

type ToastType = 'success' | 'error' | 'info';

interface ToastProps {
  type: ToastType;
  title: string;
  message?: string;
  duration?: number;
  onClose: () => void;
}

export const Toast: React.FC<ToastProps> = ({
  type,
  title,
  message,
  duration = 5000,
  onClose
}) => {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    // 少し遅延して表示することで、スムーズなアニメーションを実現
    const showTimer = setTimeout(() => {
      setIsVisible(true);
    }, 50);

    // duration が 0 の場合は自動で閉じない（手動クローズのみ）
    if (duration > 0) {
      const hideTimer = setTimeout(() => {
        setIsVisible(false);
        setTimeout(onClose, 500); // アニメーション時間に合わせて調整
      }, duration);

      return () => {
        clearTimeout(showTimer);
        clearTimeout(hideTimer);
      };
    }

    return () => clearTimeout(showTimer);
  }, [duration, onClose]);

  const getIcon = () => {
    switch (type) {
      case 'success':
        return <CheckCircleIcon className="w-6 h-6 text-green-600" />;
      case 'error':
        return <ExclamationCircleIcon className="w-6 h-6 text-red-600" />;
      case 'info':
      default:
        return <ExclamationCircleIcon className="w-6 h-6 text-blue-600" />;
    }
  };

  const getStyles = () => {
    switch (type) {
      case 'success':
        return 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-200 text-green-900 shadow-green-100';
      case 'error':
        return 'bg-gradient-to-r from-red-50 to-rose-50 border-red-200 text-red-900 shadow-red-100';
      case 'info':
      default:
        return 'bg-gradient-to-r from-blue-50 to-sky-50 border-blue-200 text-blue-900 shadow-blue-100';
    }
  };

  const handleClose = () => {
    setIsVisible(false);
    setTimeout(onClose, 500); // アニメーション時間に合わせて調整
  };

  return (
    <div
      className={`z-50 max-w-sm w-full transform transition-all duration-500 ease-out ${
        isVisible
          ? 'translate-x-0 opacity-100 scale-100'
          : 'translate-x-full opacity-0 scale-95'
      }`}
    >
      <div className={`rounded-xl border shadow-xl backdrop-blur-sm p-4 transition-all duration-300 hover:shadow-2xl hover:scale-105 ${getStyles()}`}>
        <div className="flex items-start">
          <div className="flex-shrink-0">
            {getIcon()}
          </div>
          <div className="ml-3 flex-1">
            <h3 className="text-sm font-medium">{title}</h3>
            {message && (
              <p className="mt-1 text-sm opacity-80">{message}</p>
            )}
          </div>
          <div className="ml-4 flex-shrink-0">
            <button
              onClick={handleClose}
              className="inline-flex rounded-md p-1.5 hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-current transition-colors"
            >
              <XMarkIcon className="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};