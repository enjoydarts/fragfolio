import React from 'react';
import { useTranslation } from 'react-i18next';

interface ConfidenceIndicatorProps {
  confidence: number;
  size?: 'sm' | 'md' | 'lg';
  showLabel?: boolean;
  showPercentage?: boolean;
  className?: string;
}

const ConfidenceIndicator: React.FC<ConfidenceIndicatorProps> = ({
  confidence,
  size = 'md',
  showLabel = true,
  showPercentage = true,
  className = '',
}) => {
  const { t } = useTranslation();

  // 信頼度を0-100の範囲に正規化
  const normalizedConfidence = Math.max(0, Math.min(100, confidence * 100));

  // サイズに応じたスタイル
  const sizeClasses = {
    sm: 'h-1.5 text-xs',
    md: 'h-2 text-sm',
    lg: 'h-2.5 text-base',
  };

  // 信頼度に応じた色とラベル
  const getConfidenceColor = (conf: number) => {
    if (conf >= 80) return 'bg-green-500';
    if (conf >= 60) return 'bg-yellow-500';
    if (conf >= 40) return 'bg-orange-500';
    return 'bg-red-500';
  };

  const getConfidenceLabel = (conf: number) => {
    if (conf >= 80) return t('ai.confidence.high');
    if (conf >= 60) return t('ai.confidence.medium');
    if (conf >= 40) return t('ai.confidence.low');
    return t('ai.confidence.very_low');
  };

  const confidenceColor = getConfidenceColor(normalizedConfidence);
  const confidenceLabel = getConfidenceLabel(normalizedConfidence);

  return (
    <div className={`flex flex-col space-y-1 ${className}`}>
      {showLabel && (
        <div className="flex items-center justify-between">
          <span
            className={`font-medium text-gray-700 ${sizeClasses[size].split(' ')[1]}`}
          >
            {t('ai.confidence.label')}
          </span>
          {showPercentage && (
            <span
              className={`font-mono text-gray-600 ${sizeClasses[size].split(' ')[1]}`}
            >
              {normalizedConfidence.toFixed(0)}%
            </span>
          )}
        </div>
      )}

      <div className="relative">
        {/* 背景バー */}
        <div
          className={`w-full bg-gray-200 rounded-full ${sizeClasses[size].split(' ')[0]}`}
        >
          {/* プログレスバー */}
          <div
            className={`${confidenceColor} ${sizeClasses[size].split(' ')[0]} rounded-full transition-all duration-300 ease-in-out`}
            style={{ width: `${normalizedConfidence}%` }}
          />
        </div>

        {/* ツールチップ用の情報 */}
        <div
          className="absolute inset-0 cursor-help"
          title={`${confidenceLabel}: ${normalizedConfidence.toFixed(1)}%`}
        />
      </div>

      {/* 詳細ラベル（オプション） */}
      {showLabel && size !== 'sm' && (
        <span className="text-xs text-gray-500">{confidenceLabel}</span>
      )}
    </div>
  );
};

export default ConfidenceIndicator;
