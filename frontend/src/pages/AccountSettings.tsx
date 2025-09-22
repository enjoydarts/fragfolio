import React, { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { TOTPSettings } from '../components/security/TOTPSettings';
import { WebAuthnSettings } from '../components/security/WebAuthnSettings';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import { AuthAPI } from '../api/auth';
import { useToastContext } from '../contexts/ToastContext';
import { useConfirm } from '../hooks/useConfirm';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';

interface AccountSettingsState {
  activeTab: 'profile' | 'security' | 'sessions';
}

interface ProfileFormData {
  name: string;
  email: string;
  language: string;
  timezone: string;
}

interface PasswordFormData {
  currentPassword: string;
  newPassword: string;
  confirmPassword: string;
}

export const AccountSettings: React.FC = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { toast } = useToastContext();
  const { confirm, isOpen, options, handleConfirm, handleCancel } = useConfirm();
  const [searchParams, setSearchParams] = useSearchParams();
  const [state, setState] = useState<AccountSettingsState>({
    activeTab: 'profile'
  });
  const hasProcessedParams = useRef(false); // 一度だけ実行するためのフラグ

  const tabs = [
    { id: 'profile', label: t('settings.tabs.profile', 'プロフィール') },
    { id: 'security', label: t('settings.tabs.security', 'セキュリティ') },
    { id: 'sessions', label: t('settings.tabs.sessions', 'セッション') }
  ] as const;

  const setActiveTab = (tabId: AccountSettingsState['activeTab']) => {
    setState(prev => ({ ...prev, activeTab: tabId }));
  };

  // 初回マウント時のみURLパラメータをチェック
  useEffect(() => {
    // 既に処理済みの場合は何もしない
    if (hasProcessedParams.current) {
      return;
    }

    const message = searchParams.get('message');
    const error = searchParams.get('error');


    if (message || error) {
      // パラメータが存在する場合のみ処理を実行
      hasProcessedParams.current = true; // フラグを立てる

      if (message) {
        toast.success(
          t('settings.email_change_success', 'メールアドレス変更完了'),
          decodeURIComponent(message)
        );
      } else if (error) {
        toast.error(
          t('settings.email_change_error', 'メールアドレス変更エラー'),
          decodeURIComponent(error)
        );
      }
      // URLパラメータをクリア
      setSearchParams({}, { replace: true });
    }
  }, []); // 空の依存配列で初回のみ実行

  if (!user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-gray-500">{t('common.loading', '読み込み中...')}</div>
      </div>
    );
  }


  return (
    <div className="min-h-screen bg-gradient-to-br from-orange-50 via-amber-50 to-red-50">
      {/* フローティング背景要素 */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-80 h-80 bg-gradient-to-br from-orange-200/30 to-amber-200/30 rounded-full blur-3xl"></div>
        <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-gradient-to-tr from-amber-200/30 to-orange-200/30 rounded-full blur-3xl"></div>
      </div>

      <div className="relative z-10 px-4 py-6 sm:px-6 lg:px-8">
        <div className="max-w-2xl mx-auto">
          {/* ページヘッダー */}
          <div className="mb-8">
            <button
              onClick={() => navigate('/')}
              className="flex items-center text-gray-600 hover:text-orange-600 transition-colors duration-200 mb-6"
            >
              <ArrowLeftIcon className="w-5 h-5 mr-2" />
              {t('common.back_to_dashboard', 'ダッシュボードに戻る')}
            </button>

            <div className="text-center mb-8">
              <h1 className="text-3xl font-light text-gray-800 mb-2">
                {t('settings.title', 'アカウント設定')}
              </h1>
              <p className="text-gray-500 text-sm font-light">
                {t('settings.description', 'アカウント情報とセキュリティ設定を管理します')}
              </p>
            </div>
          </div>

          {/* タブナビゲーション - モバイルファースト */}
          <div className="bg-white/95 backdrop-blur-sm border border-white/20 rounded-2xl shadow-xl mb-6 overflow-hidden">
            <div className="border-b border-gray-200/50">
              <nav className="flex" aria-label="Tabs">
                {tabs.map((tab) => (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`flex-1 py-4 px-3 text-center font-medium text-sm transition-all duration-200 relative ${
                      state.activeTab === tab.id
                        ? 'bg-white/80 text-amber-700 shadow-sm border-b-2 border-amber-400'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-white/30'
                    }`}
                  >
                    {tab.label}
                  </button>
                ))}
              </nav>
            </div>

            {/* タブコンテンツ */}
            <div className="p-6 sm:p-8">
              {state.activeTab === 'profile' && (
                <ProfileSettings />
              )}
              {state.activeTab === 'security' && (
                <SecuritySettings />
              )}
              {state.activeTab === 'sessions' && (
                <SessionsSettings />
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Confirm Dialog */}
      <ConfirmDialog
        isOpen={isOpen}
        title={options.title}
        message={options.message}
        confirmText={options.confirmText}
        cancelText={options.cancelText}
        confirmVariant={options.confirmVariant}
        onConfirm={handleConfirm}
        onCancel={handleCancel}
      />
    </div>
  );
};

// プロフィール設定コンポーネント
const ProfileSettings: React.FC = () => {
  const { t } = useTranslation();
  const { user, token, refreshUser } = useAuth();
  const { toast } = useToastContext();
  const [formData, setFormData] = useState<ProfileFormData>({
    name: user?.name || '',
    email: user?.email || '',
    language: user?.profile?.language || 'ja',
    timezone: user?.profile?.timezone || 'Asia/Tokyo'
  });
  const [isLoading, setIsLoading] = useState(false);
  const [showEmailChangeDialog, setShowEmailChangeDialog] = useState(false);
  const [newEmail, setNewEmail] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;

    setIsLoading(true);

    try {
      const response = await AuthAPI.updateProfile(token, {
        name: formData.name,
        language: formData.language,
        timezone: formData.timezone
        // メールアドレスは除外（別途認証プロセスが必要）
      });

      if (response.success) {
        await refreshUser();
        toast.success(
          t('auth.profile_update_success', 'プロフィールを更新しました'),
          t('auth.profile_update_success_desc', '変更内容が正常に保存されました')
        );
      } else {
        toast.error(
          t('auth.profile_update_failed', 'プロフィール更新に失敗しました'),
          response.message
        );
      }
    } catch (error) {
      console.error('Profile update failed:', error);
      toast.error(
        t('auth.profile_update_failed', 'プロフィール更新に失敗しました'),
        t('auth.network_error', 'ネットワークエラーが発生しました')
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (field: keyof ProfileFormData, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const handleEmailChange = async () => {
    if (!token || !newEmail.trim()) return;

    if (newEmail === user?.email) {
      toast.error(
        t('settings.profile.email_same_error', 'エラー'),
        t('settings.profile.email_same_error_desc', '現在のメールアドレスと同じです')
      );
      return;
    }

    try {
      const response = await AuthAPI.requestEmailChange(token, { new_email: newEmail });

      if (response.success) {
        toast.success(
          t('settings.profile.email_change_request_sent', 'メールアドレス変更リクエストを送信しました'),
          t('settings.profile.email_change_request_desc', '新しいメールアドレスに確認メールを送信しました。確認リンクをクリックすると変更が適用されます。')
        );
        setShowEmailChangeDialog(false);
        setNewEmail('');
      } else {
        toast.error(
          t('settings.profile.email_change_failed', 'メールアドレス変更リクエストに失敗しました'),
          response.message || t('auth.network_error', 'ネットワークエラーが発生しました')
        );
      }
    } catch (error) {
      toast.error(
        t('settings.profile.email_change_failed', 'メールアドレス変更リクエストに失敗しました'),
        t('auth.network_error', 'ネットワークエラーが発生しました')
      );
    }
  };

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h3 className="text-xl font-light text-gray-800 mb-2">
          {t('settings.profile.title', 'プロフィール情報')}
        </h3>
        <p className="text-gray-500 text-sm font-light">
          {t('settings.profile.description', '基本的なアカウント情報を設定します')}
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="space-y-4">
          {/* 名前フィールド */}
          <div className="group">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              {t('auth.fields.name', '名前')}
            </label>
            <div className="relative">
              <input
                type="text"
                value={formData.name}
                onChange={(e) => handleInputChange('name', e.target.value)}
                className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                placeholder={t('auth.placeholders.name', '田中 太郎')}
                required
              />
              <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                <svg className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            </div>
          </div>

          {/* メールフィールド */}
          <div className="group">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              {t('auth.fields.email', 'メールアドレス')}
            </label>
            <div className="relative">
              <input
                type="email"
                value={formData.email}
                disabled
                className="w-full px-4 py-4 bg-gray-100 border border-gray-200 rounded-xl text-gray-600 placeholder-gray-400 font-light cursor-not-allowed"
                placeholder={t('auth.placeholders.email', 'your@email.com')}
              />
              <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                </svg>
              </div>
            </div>
            <p className="mt-2 text-xs text-gray-500">
              {t('settings.profile.email_change_note', 'メールアドレスの変更は安全性のため、別途お手続きが必要です')}
            </p>
            <button
              type="button"
              className="mt-2 text-sm text-amber-600 hover:text-amber-700 font-medium transition-colors"
              onClick={() => setShowEmailChangeDialog(true)}
            >
              {t('settings.profile.change_email', 'メールアドレスを変更')}
            </button>
          </div>

          {/* 言語選択 */}
          <div className="group">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              {t('settings.profile.language', '言語')}
            </label>
            <div className="relative">
              <select
                value={formData.language}
                onChange={(e) => handleInputChange('language', e.target.value)}
                className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 font-light appearance-none cursor-pointer"
              >
                <option value="ja">{t('auth.languages.ja')}</option>
                <option value="en">{t('auth.languages.en')}</option>
              </select>
              <div className="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                <svg className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>

          {/* タイムゾーン */}
          <div className="group">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              {t('settings.profile.timezone', 'タイムゾーン')}
            </label>
            <div className="relative">
              <select
                value={formData.timezone}
                onChange={(e) => handleInputChange('timezone', e.target.value)}
                className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 font-light appearance-none cursor-pointer"
              >
                {/* UTC */}
                <optgroup label="UTC">
                  <option value="UTC">UTC (UTC+0)</option>
                </optgroup>

                {/* アジア・太平洋 */}
                <optgroup label="Asia/Pacific">
                  <option value="Asia/Kamchatka">Asia/Kamchatka (UTC+12)</option>
                  <option value="Asia/Magadan">Asia/Magadan (UTC+11)</option>
                  <option value="Asia/Vladivostok">Asia/Vladivostok (UTC+10)</option>
                  <option value="Asia/Yakutsk">Asia/Yakutsk (UTC+9)</option>
                  <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
                  <option value="Asia/Seoul">Asia/Seoul (UTC+9)</option>
                  <option value="Asia/Pyongyang">Asia/Pyongyang (UTC+9)</option>
                  <option value="Asia/Irkutsk">Asia/Irkutsk (UTC+8)</option>
                  <option value="Asia/Shanghai">Asia/Shanghai (UTC+8)</option>
                  <option value="Asia/Hong_Kong">Asia/Hong_Kong (UTC+8)</option>
                  <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                  <option value="Asia/Taipei">Asia/Taipei (UTC+8)</option>
                  <option value="Asia/Manila">Asia/Manila (UTC+8)</option>
                  <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (UTC+8)</option>
                  <option value="Asia/Brunei">Asia/Brunei (UTC+8)</option>
                  <option value="Asia/Makassar">Asia/Makassar (UTC+8)</option>
                  <option value="Asia/Krasnoyarsk">Asia/Krasnoyarsk (UTC+7)</option>
                  <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
                  <option value="Asia/Jakarta">Asia/Jakarta (UTC+7)</option>
                  <option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh (UTC+7)</option>
                  <option value="Asia/Vientiane">Asia/Vientiane (UTC+7)</option>
                  <option value="Asia/Phnom_Penh">Asia/Phnom_Penh (UTC+7)</option>
                  <option value="Asia/Rangoon">Asia/Rangoon (UTC+6:30)</option>
                  <option value="Asia/Omsk">Asia/Omsk (UTC+6)</option>
                  <option value="Asia/Almaty">Asia/Almaty (UTC+6)</option>
                  <option value="Asia/Dhaka">Asia/Dhaka (UTC+6)</option>
                  <option value="Asia/Thimphu">Asia/Thimphu (UTC+6)</option>
                  <option value="Asia/Kathmandu">Asia/Kathmandu (UTC+5:45)</option>
                  <option value="Asia/Yekaterinburg">Asia/Yekaterinburg (UTC+5)</option>
                  <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                  <option value="Asia/Colombo">Asia/Colombo (UTC+5:30)</option>
                  <option value="Asia/Karachi">Asia/Karachi (UTC+5)</option>
                  <option value="Asia/Tashkent">Asia/Tashkent (UTC+5)</option>
                  <option value="Asia/Dushanbe">Asia/Dushanbe (UTC+5)</option>
                  <option value="Asia/Kabul">Asia/Kabul (UTC+4:30)</option>
                  <option value="Asia/Dubai">Asia/Dubai (UTC+4)</option>
                  <option value="Asia/Muscat">Asia/Muscat (UTC+4)</option>
                  <option value="Asia/Baku">Asia/Baku (UTC+4)</option>
                  <option value="Asia/Tbilisi">Asia/Tbilisi (UTC+4)</option>
                  <option value="Asia/Yerevan">Asia/Yerevan (UTC+4)</option>
                  <option value="Asia/Tehran">Asia/Tehran (UTC+3:30)</option>
                  <option value="Asia/Baghdad">Asia/Baghdad (UTC+3)</option>
                  <option value="Asia/Kuwait">Asia/Kuwait (UTC+3)</option>
                  <option value="Asia/Riyadh">Asia/Riyadh (UTC+3)</option>
                  <option value="Asia/Qatar">Asia/Qatar (UTC+3)</option>
                  <option value="Asia/Bahrain">Asia/Bahrain (UTC+3)</option>
                  <option value="Asia/Jerusalem">Asia/Jerusalem (UTC+2)</option>
                  <option value="Asia/Damascus">Asia/Damascus (UTC+2)</option>
                  <option value="Asia/Beirut">Asia/Beirut (UTC+2)</option>
                  <option value="Asia/Amman">Asia/Amman (UTC+2)</option>
                  <option value="Asia/Gaza">Asia/Gaza (UTC+2)</option>
                  <option value="Asia/Nicosia">Asia/Nicosia (UTC+2)</option>
                  <option value="Pacific/Auckland">Pacific/Auckland (UTC+13)</option>
                  <option value="Pacific/Fiji">Pacific/Fiji (UTC+12)</option>
                  <option value="Pacific/Norfolk">Pacific/Norfolk (UTC+11)</option>
                  <option value="Pacific/Noumea">Pacific/Noumea (UTC+11)</option>
                  <option value="Pacific/Guadalcanal">Pacific/Guadalcanal (UTC+11)</option>
                  <option value="Pacific/Port_Moresby">Pacific/Port_Moresby (UTC+10)</option>
                  <option value="Pacific/Guam">Pacific/Guam (UTC+10)</option>
                  <option value="Pacific/Saipan">Pacific/Saipan (UTC+10)</option>
                  <option value="Pacific/Palau">Pacific/Palau (UTC+9)</option>
                  <option value="Pacific/Nauru">Pacific/Nauru (UTC+12)</option>
                  <option value="Pacific/Majuro">Pacific/Majuro (UTC+12)</option>
                  <option value="Pacific/Tarawa">Pacific/Tarawa (UTC+12)</option>
                  <option value="Pacific/Wake">Pacific/Wake (UTC+12)</option>
                  <option value="Pacific/Wallis">Pacific/Wallis (UTC+12)</option>
                  <option value="Pacific/Funafuti">Pacific/Funafuti (UTC+12)</option>
                  <option value="Pacific/Kosrae">Pacific/Kosrae (UTC+11)</option>
                  <option value="Pacific/Ponape">Pacific/Ponape (UTC+11)</option>
                  <option value="Pacific/Truk">Pacific/Truk (UTC+10)</option>
                  <option value="Pacific/Yap">Pacific/Yap (UTC+10)</option>
                  <option value="Pacific/Pago_Pago">Pacific/Pago_Pago (UTC-11)</option>
                  <option value="Pacific/Honolulu">Pacific/Honolulu (UTC-10)</option>
                  <option value="Pacific/Rarotonga">Pacific/Rarotonga (UTC-10)</option>
                  <option value="Pacific/Tahiti">Pacific/Tahiti (UTC-10)</option>
                  <option value="Pacific/Marquesas">Pacific/Marquesas (UTC-9:30)</option>
                  <option value="Pacific/Gambier">Pacific/Gambier (UTC-9)</option>
                  <option value="Pacific/Pitcairn">Pacific/Pitcairn (UTC-8)</option>
                  <option value="Pacific/Easter">Pacific/Easter (UTC-5)</option>
                  <option value="Pacific/Galapagos">Pacific/Galapagos (UTC-6)</option>
                  <option value="Australia/Darwin">Australia/Darwin (UTC+9:30)</option>
                  <option value="Australia/Adelaide">Australia/Adelaide (UTC+10:30)</option>
                  <option value="Australia/Sydney">Australia/Sydney (UTC+11)</option>
                  <option value="Australia/Melbourne">Australia/Melbourne (UTC+11)</option>
                  <option value="Australia/Brisbane">Australia/Brisbane (UTC+10)</option>
                  <option value="Australia/Perth">Australia/Perth (UTC+8)</option>
                  <option value="Australia/Lord_Howe">Australia/Lord_Howe (UTC+11)</option>
                  <option value="Australia/Broken_Hill">Australia/Broken_Hill (UTC+10:30)</option>
                  <option value="Australia/Hobart">Australia/Hobart (UTC+11)</option>
                  <option value="Australia/Eucla">Australia/Eucla (UTC+8:45)</option>
                </optgroup>

                {/* ヨーロッパ */}
                <optgroup label="Europe">
                  <option value="Europe/London">Europe/London (UTC+0)</option>
                  <option value="Europe/Dublin">Europe/Dublin (UTC+0)</option>
                  <option value="Europe/Lisbon">Europe/Lisbon (UTC+0)</option>
                  <option value="Europe/Reykjavik">Europe/Reykjavik (UTC+0)</option>
                  <option value="Europe/Paris">Europe/Paris (UTC+1)</option>
                  <option value="Europe/Berlin">Europe/Berlin (UTC+1)</option>
                  <option value="Europe/Rome">Europe/Rome (UTC+1)</option>
                  <option value="Europe/Madrid">Europe/Madrid (UTC+1)</option>
                  <option value="Europe/Amsterdam">Europe/Amsterdam (UTC+1)</option>
                  <option value="Europe/Brussels">Europe/Brussels (UTC+1)</option>
                  <option value="Europe/Vienna">Europe/Vienna (UTC+1)</option>
                  <option value="Europe/Zurich">Europe/Zurich (UTC+1)</option>
                  <option value="Europe/Prague">Europe/Prague (UTC+1)</option>
                  <option value="Europe/Warsaw">Europe/Warsaw (UTC+1)</option>
                  <option value="Europe/Budapest">Europe/Budapest (UTC+1)</option>
                  <option value="Europe/Stockholm">Europe/Stockholm (UTC+1)</option>
                  <option value="Europe/Oslo">Europe/Oslo (UTC+1)</option>
                  <option value="Europe/Copenhagen">Europe/Copenhagen (UTC+1)</option>
                  <option value="Europe/Zagreb">Europe/Zagreb (UTC+1)</option>
                  <option value="Europe/Ljubljana">Europe/Ljubljana (UTC+1)</option>
                  <option value="Europe/Sarajevo">Europe/Sarajevo (UTC+1)</option>
                  <option value="Europe/Belgrade">Europe/Belgrade (UTC+1)</option>
                  <option value="Europe/Skopje">Europe/Skopje (UTC+1)</option>
                  <option value="Europe/Podgorica">Europe/Podgorica (UTC+1)</option>
                  <option value="Europe/Tirane">Europe/Tirane (UTC+1)</option>
                  <option value="Europe/Malta">Europe/Malta (UTC+1)</option>
                  <option value="Europe/Vatican">Europe/Vatican (UTC+1)</option>
                  <option value="Europe/San_Marino">Europe/San_Marino (UTC+1)</option>
                  <option value="Europe/Andorra">Europe/Andorra (UTC+1)</option>
                  <option value="Europe/Monaco">Europe/Monaco (UTC+1)</option>
                  <option value="Europe/Luxembourg">Europe/Luxembourg (UTC+1)</option>
                  <option value="Europe/Liechtenstein">Europe/Liechtenstein (UTC+1)</option>
                  <option value="Europe/Helsinki">Europe/Helsinki (UTC+2)</option>
                  <option value="Europe/Tallinn">Europe/Tallinn (UTC+2)</option>
                  <option value="Europe/Riga">Europe/Riga (UTC+2)</option>
                  <option value="Europe/Vilnius">Europe/Vilnius (UTC+2)</option>
                  <option value="Europe/Athens">Europe/Athens (UTC+2)</option>
                  <option value="Europe/Sofia">Europe/Sofia (UTC+2)</option>
                  <option value="Europe/Bucharest">Europe/Bucharest (UTC+2)</option>
                  <option value="Europe/Chisinau">Europe/Chisinau (UTC+2)</option>
                  <option value="Europe/Kiev">Europe/Kiev (UTC+2)</option>
                  <option value="Europe/Istanbul">Europe/Istanbul (UTC+3)</option>
                  <option value="Europe/Moscow">Europe/Moscow (UTC+3)</option>
                  <option value="Europe/Minsk">Europe/Minsk (UTC+3)</option>
                  <option value="Europe/Kaliningrad">Europe/Kaliningrad (UTC+2)</option>
                  <option value="Europe/Samara">Europe/Samara (UTC+4)</option>
                  <option value="Europe/Volgograd">Europe/Volgograd (UTC+3)</option>
                  <option value="Europe/Astrakhan">Europe/Astrakhan (UTC+4)</option>
                  <option value="Europe/Saratov">Europe/Saratov (UTC+4)</option>
                  <option value="Europe/Ulyanovsk">Europe/Ulyanovsk (UTC+4)</option>
                </optgroup>

                {/* 北米 */}
                <optgroup label="North America">
                  <option value="America/New_York">America/New_York (UTC-5)</option>
                  <option value="America/Detroit">America/Detroit (UTC-5)</option>
                  <option value="America/Louisville">America/Louisville (UTC-5)</option>
                  <option value="America/Kentucky/Monticello">America/Kentucky/Monticello (UTC-5)</option>
                  <option value="America/Indiana/Indianapolis">America/Indiana/Indianapolis (UTC-5)</option>
                  <option value="America/Indiana/Vincennes">America/Indiana/Vincennes (UTC-5)</option>
                  <option value="America/Indiana/Winamac">America/Indiana/Winamac (UTC-5)</option>
                  <option value="America/Indiana/Marengo">America/Indiana/Marengo (UTC-5)</option>
                  <option value="America/Indiana/Petersburg">America/Indiana/Petersburg (UTC-5)</option>
                  <option value="America/Indiana/Vevay">America/Indiana/Vevay (UTC-5)</option>
                  <option value="America/Chicago">America/Chicago (UTC-6)</option>
                  <option value="America/Indiana/Tell_City">America/Indiana/Tell_City (UTC-6)</option>
                  <option value="America/Indiana/Knox">America/Indiana/Knox (UTC-6)</option>
                  <option value="America/Menominee">America/Menominee (UTC-6)</option>
                  <option value="America/North_Dakota/Center">America/North_Dakota/Center (UTC-6)</option>
                  <option value="America/North_Dakota/New_Salem">America/North_Dakota/New_Salem (UTC-6)</option>
                  <option value="America/North_Dakota/Beulah">America/North_Dakota/Beulah (UTC-6)</option>
                  <option value="America/Denver">America/Denver (UTC-7)</option>
                  <option value="America/Boise">America/Boise (UTC-7)</option>
                  <option value="America/Phoenix">America/Phoenix (UTC-7)</option>
                  <option value="America/Los_Angeles">America/Los_Angeles (UTC-8)</option>
                  <option value="America/Anchorage">America/Anchorage (UTC-9)</option>
                  <option value="America/Juneau">America/Juneau (UTC-9)</option>
                  <option value="America/Sitka">America/Sitka (UTC-9)</option>
                  <option value="America/Metlakatla">America/Metlakatla (UTC-9)</option>
                  <option value="America/Yakutat">America/Yakutat (UTC-9)</option>
                  <option value="America/Nome">America/Nome (UTC-9)</option>
                  <option value="America/Adak">America/Adak (UTC-10)</option>
                  <option value="America/Toronto">America/Toronto (UTC-5)</option>
                  <option value="America/Thunder_Bay">America/Thunder_Bay (UTC-5)</option>
                  <option value="America/Nipigon">America/Nipigon (UTC-5)</option>
                  <option value="America/Montreal">America/Montreal (UTC-5)</option>
                  <option value="America/Winnipeg">America/Winnipeg (UTC-6)</option>
                  <option value="America/Regina">America/Regina (UTC-6)</option>
                  <option value="America/Swift_Current">America/Swift_Current (UTC-6)</option>
                  <option value="America/Edmonton">America/Edmonton (UTC-7)</option>
                  <option value="America/Calgary">America/Calgary (UTC-7)</option>
                  <option value="America/Vancouver">America/Vancouver (UTC-8)</option>
                  <option value="America/Dawson_Creek">America/Dawson_Creek (UTC-7)</option>
                  <option value="America/Fort_Nelson">America/Fort_Nelson (UTC-7)</option>
                  <option value="America/Whitehorse">America/Whitehorse (UTC-7)</option>
                  <option value="America/Dawson">America/Dawson (UTC-7)</option>
                  <option value="America/Inuvik">America/Inuvik (UTC-7)</option>
                  <option value="America/Iqaluit">America/Iqaluit (UTC-5)</option>
                  <option value="America/Pangnirtung">America/Pangnirtung (UTC-5)</option>
                  <option value="America/Rankin_Inlet">America/Rankin_Inlet (UTC-6)</option>
                  <option value="America/Resolute">America/Resolute (UTC-6)</option>
                  <option value="America/Cambridge_Bay">America/Cambridge_Bay (UTC-7)</option>
                  <option value="America/Yellowknife">America/Yellowknife (UTC-7)</option>
                  <option value="America/Mexico_City">America/Mexico_City (UTC-6)</option>
                  <option value="America/Cancun">America/Cancun (UTC-5)</option>
                  <option value="America/Merida">America/Merida (UTC-6)</option>
                  <option value="America/Monterrey">America/Monterrey (UTC-6)</option>
                  <option value="America/Matamoros">America/Matamoros (UTC-6)</option>
                  <option value="America/Ojinaga">America/Ojinaga (UTC-7)</option>
                  <option value="America/Hermosillo">America/Hermosillo (UTC-7)</option>
                  <option value="America/Chihuahua">America/Chihuahua (UTC-7)</option>
                  <option value="America/Mazatlan">America/Mazatlan (UTC-7)</option>
                  <option value="America/Bahia_Banderas">America/Bahia_Banderas (UTC-6)</option>
                  <option value="America/Tijuana">America/Tijuana (UTC-8)</option>
                  <option value="America/Guatemala">America/Guatemala (UTC-6)</option>
                  <option value="America/Belize">America/Belize (UTC-6)</option>
                  <option value="America/El_Salvador">America/El_Salvador (UTC-6)</option>
                  <option value="America/Tegucigalpa">America/Tegucigalpa (UTC-6)</option>
                  <option value="America/Managua">America/Managua (UTC-6)</option>
                  <option value="America/Costa_Rica">America/Costa_Rica (UTC-6)</option>
                  <option value="America/Panama">America/Panama (UTC-5)</option>
                </optgroup>

                {/* 南米 */}
                <optgroup label="South America">
                  <option value="America/Sao_Paulo">America/Sao_Paulo (UTC-3)</option>
                  <option value="America/Bahia">America/Bahia (UTC-3)</option>
                  <option value="America/Fortaleza">America/Fortaleza (UTC-3)</option>
                  <option value="America/Recife">America/Recife (UTC-3)</option>
                  <option value="America/Araguaina">America/Araguaina (UTC-3)</option>
                  <option value="America/Maceio">America/Maceio (UTC-3)</option>
                  <option value="America/Belem">America/Belem (UTC-3)</option>
                  <option value="America/Santarem">America/Santarem (UTC-3)</option>
                  <option value="America/Campo_Grande">America/Campo_Grande (UTC-4)</option>
                  <option value="America/Cuiaba">America/Cuiaba (UTC-4)</option>
                  <option value="America/Manaus">America/Manaus (UTC-4)</option>
                  <option value="America/Porto_Velho">America/Porto_Velho (UTC-4)</option>
                  <option value="America/Boa_Vista">America/Boa_Vista (UTC-4)</option>
                  <option value="America/Rio_Branco">America/Rio_Branco (UTC-5)</option>
                  <option value="America/Eirunepe">America/Eirunepe (UTC-5)</option>
                  <option value="America/Buenos_Aires">America/Buenos_Aires (UTC-3)</option>
                  <option value="America/Argentina/La_Rioja">America/Argentina/La_Rioja (UTC-3)</option>
                  <option value="America/Argentina/Rio_Gallegos">America/Argentina/Rio_Gallegos (UTC-3)</option>
                  <option value="America/Argentina/Salta">America/Argentina/Salta (UTC-3)</option>
                  <option value="America/Argentina/San_Juan">America/Argentina/San_Juan (UTC-3)</option>
                  <option value="America/Argentina/San_Luis">America/Argentina/San_Luis (UTC-3)</option>
                  <option value="America/Argentina/Tucuman">America/Argentina/Tucuman (UTC-3)</option>
                  <option value="America/Argentina/Ushuaia">America/Argentina/Ushuaia (UTC-3)</option>
                  <option value="America/Catamarca">America/Catamarca (UTC-3)</option>
                  <option value="America/Cordoba">America/Cordoba (UTC-3)</option>
                  <option value="America/Jujuy">America/Jujuy (UTC-3)</option>
                  <option value="America/Mendoza">America/Mendoza (UTC-3)</option>
                  <option value="America/Santiago">America/Santiago (UTC-3)</option>
                  <option value="America/Punta_Arenas">America/Punta_Arenas (UTC-3)</option>
                  <option value="America/Bogota">America/Bogota (UTC-5)</option>
                  <option value="America/Lima">America/Lima (UTC-5)</option>
                  <option value="America/La_Paz">America/La_Paz (UTC-4)</option>
                  <option value="America/Caracas">America/Caracas (UTC-4)</option>
                  <option value="America/Guyana">America/Guyana (UTC-4)</option>
                  <option value="America/Paramaribo">America/Paramaribo (UTC-3)</option>
                  <option value="America/Cayenne">America/Cayenne (UTC-3)</option>
                  <option value="America/Montevideo">America/Montevideo (UTC-3)</option>
                  <option value="America/Asuncion">America/Asuncion (UTC-3)</option>
                  <option value="America/Noronha">America/Noronha (UTC-2)</option>
                </optgroup>

                {/* アフリカ */}
                <optgroup label="Africa">
                  <option value="Africa/Cairo">Africa/Cairo (UTC+2)</option>
                  <option value="Africa/Alexandria">Africa/Alexandria (UTC+2)</option>
                  <option value="Africa/Tripoli">Africa/Tripoli (UTC+2)</option>
                  <option value="Africa/Tunis">Africa/Tunis (UTC+1)</option>
                  <option value="Africa/Algiers">Africa/Algiers (UTC+1)</option>
                  <option value="Africa/Casablanca">Africa/Casablanca (UTC+1)</option>
                  <option value="Africa/El_Aaiun">Africa/El_Aaiun (UTC+1)</option>
                  <option value="Africa/Ceuta">Africa/Ceuta (UTC+1)</option>
                  <option value="Africa/Lagos">Africa/Lagos (UTC+1)</option>
                  <option value="Africa/Porto-Novo">Africa/Porto-Novo (UTC+1)</option>
                  <option value="Africa/Cotonou">Africa/Cotonou (UTC+1)</option>
                  <option value="Africa/Niamey">Africa/Niamey (UTC+1)</option>
                  <option value="Africa/Ouagadougou">Africa/Ouagadougou (UTC+0)</option>
                  <option value="Africa/Bamako">Africa/Bamako (UTC+0)</option>
                  <option value="Africa/Timbuktu">Africa/Timbuktu (UTC+0)</option>
                  <option value="Africa/Conakry">Africa/Conakry (UTC+0)</option>
                  <option value="Africa/Bissau">Africa/Bissau (UTC+0)</option>
                  <option value="Africa/Monrovia">Africa/Monrovia (UTC+0)</option>
                  <option value="Africa/Freetown">Africa/Freetown (UTC+0)</option>
                  <option value="Africa/Dakar">Africa/Dakar (UTC+0)</option>
                  <option value="Africa/Banjul">Africa/Banjul (UTC+0)</option>
                  <option value="Africa/Nouakchott">Africa/Nouakchott (UTC+0)</option>
                  <option value="Africa/Praia">Africa/Praia (UTC-1)</option>
                  <option value="Africa/Abidjan">Africa/Abidjan (UTC+0)</option>
                  <option value="Africa/Accra">Africa/Accra (UTC+0)</option>
                  <option value="Africa/Lome">Africa/Lome (UTC+0)</option>
                  <option value="Africa/Sao_Tome">Africa/Sao_Tome (UTC+0)</option>
                  <option value="Africa/Malabo">Africa/Malabo (UTC+1)</option>
                  <option value="Africa/Libreville">Africa/Libreville (UTC+1)</option>
                  <option value="Africa/Brazzaville">Africa/Brazzaville (UTC+1)</option>
                  <option value="Africa/Kinshasa">Africa/Kinshasa (UTC+1)</option>
                  <option value="Africa/Lubumbashi">Africa/Lubumbashi (UTC+2)</option>
                  <option value="Africa/Bangui">Africa/Bangui (UTC+1)</option>
                  <option value="Africa/Ndjamena">Africa/Ndjamena (UTC+1)</option>
                  <option value="Africa/Douala">Africa/Douala (UTC+1)</option>
                  <option value="Africa/Yaoundé">Africa/Yaoundé (UTC+1)</option>
                  <option value="Africa/Khartoum">Africa/Khartoum (UTC+2)</option>
                  <option value="Africa/Juba">Africa/Juba (UTC+2)</option>
                  <option value="Africa/Addis_Ababa">Africa/Addis_Ababa (UTC+3)</option>
                  <option value="Africa/Asmara">Africa/Asmara (UTC+3)</option>
                  <option value="Africa/Djibouti">Africa/Djibouti (UTC+3)</option>
                  <option value="Africa/Mogadishu">Africa/Mogadishu (UTC+3)</option>
                  <option value="Africa/Nairobi">Africa/Nairobi (UTC+3)</option>
                  <option value="Africa/Kampala">Africa/Kampala (UTC+3)</option>
                  <option value="Africa/Kigali">Africa/Kigali (UTC+2)</option>
                  <option value="Africa/Bujumbura">Africa/Bujumbura (UTC+2)</option>
                  <option value="Africa/Dar_es_Salaam">Africa/Dar_es_Salaam (UTC+3)</option>
                  <option value="Africa/Dodoma">Africa/Dodoma (UTC+3)</option>
                  <option value="Africa/Lusaka">Africa/Lusaka (UTC+2)</option>
                  <option value="Africa/Harare">Africa/Harare (UTC+2)</option>
                  <option value="Africa/Gaborone">Africa/Gaborone (UTC+2)</option>
                  <option value="Africa/Maseru">Africa/Maseru (UTC+2)</option>
                  <option value="Africa/Mbabane">Africa/Mbabane (UTC+2)</option>
                  <option value="Africa/Maputo">Africa/Maputo (UTC+2)</option>
                  <option value="Africa/Johannesburg">Africa/Johannesburg (UTC+2)</option>
                  <option value="Africa/Windhoek">Africa/Windhoek (UTC+2)</option>
                  <option value="Africa/Blantyre">Africa/Blantyre (UTC+2)</option>
                  <option value="Africa/Lilongwe">Africa/Lilongwe (UTC+2)</option>
                  <option value="Africa/Antananarivo">Africa/Antananarivo (UTC+3)</option>
                  <option value="Africa/Comoro">Africa/Comoro (UTC+3)</option>
                  <option value="Africa/Mayotte">Africa/Mayotte (UTC+3)</option>
                  <option value="Africa/Mauritius">Africa/Mauritius (UTC+4)</option>
                  <option value="Africa/Reunion">Africa/Reunion (UTC+4)</option>
                  <option value="Africa/Seychelles">Africa/Seychelles (UTC+4)</option>
                </optgroup>

                {/* 大西洋 */}
                <optgroup label="Atlantic">
                  <option value="Atlantic/Azores">Atlantic/Azores (UTC-1)</option>
                  <option value="Atlantic/Madeira">Atlantic/Madeira (UTC+0)</option>
                  <option value="Atlantic/Canary">Atlantic/Canary (UTC+0)</option>
                  <option value="Atlantic/Cape_Verde">Atlantic/Cape_Verde (UTC-1)</option>
                  <option value="Atlantic/Reykjavik">Atlantic/Reykjavik (UTC+0)</option>
                  <option value="Atlantic/Faroe">Atlantic/Faroe (UTC+0)</option>
                  <option value="Atlantic/Jan_Mayen">Atlantic/Jan_Mayen (UTC+1)</option>
                  <option value="Atlantic/Bermuda">Atlantic/Bermuda (UTC-4)</option>
                  <option value="Atlantic/Stanley">Atlantic/Stanley (UTC-3)</option>
                  <option value="Atlantic/South_Georgia">Atlantic/South_Georgia (UTC-2)</option>
                  <option value="Atlantic/St_Helena">Atlantic/St_Helena (UTC+0)</option>
                </optgroup>

                {/* インド洋 */}
                <optgroup label="Indian Ocean">
                  <option value="Indian/Maldives">Indian/Maldives (UTC+5)</option>
                  <option value="Indian/Chagos">Indian/Chagos (UTC+6)</option>
                  <option value="Indian/Christmas">Indian/Christmas (UTC+7)</option>
                  <option value="Indian/Cocos">Indian/Cocos (UTC+6:30)</option>
                  <option value="Indian/Kerguelen">Indian/Kerguelen (UTC+5)</option>
                  <option value="Indian/Mahe">Indian/Mahe (UTC+4)</option>
                  <option value="Indian/Mauritius">Indian/Mauritius (UTC+4)</option>
                  <option value="Indian/Reunion">Indian/Reunion (UTC+4)</option>
                  <option value="Indian/Mayotte">Indian/Mayotte (UTC+3)</option>
                  <option value="Indian/Comoro">Indian/Comoro (UTC+3)</option>
                  <option value="Indian/Antananarivo">Indian/Antananarivo (UTC+3)</option>
                </optgroup>

                {/* 南極 */}
                <optgroup label="Antarctica">
                  <option value="Antarctica/McMurdo">Antarctica/McMurdo (UTC+13)</option>
                  <option value="Antarctica/South_Pole">Antarctica/South_Pole (UTC+13)</option>
                  <option value="Antarctica/Palmer">Antarctica/Palmer (UTC-3)</option>
                  <option value="Antarctica/Rothera">Antarctica/Rothera (UTC-3)</option>
                  <option value="Antarctica/Syowa">Antarctica/Syowa (UTC+3)</option>
                  <option value="Antarctica/Mawson">Antarctica/Mawson (UTC+5)</option>
                  <option value="Antarctica/Davis">Antarctica/Davis (UTC+7)</option>
                  <option value="Antarctica/Casey">Antarctica/Casey (UTC+11)</option>
                  <option value="Antarctica/Vostok">Antarctica/Vostok (UTC+6)</option>
                  <option value="Antarctica/DumontDUrville">Antarctica/DumontDUrville (UTC+10)</option>
                  <option value="Antarctica/Macquarie">Antarctica/Macquarie (UTC+11)</option>
                </optgroup>

                {/* 北極圏 */}
                <optgroup label="Arctic">
                  <option value="Arctic/Longyearbyen">Arctic/Longyearbyen (UTC+1)</option>
                </optgroup>
              </select>
              <div className="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                <svg className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <div className="pt-6">
          <button
            type="submit"
            disabled={isLoading}
            className="w-full py-4 text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 rounded-xl font-medium tracking-wide shadow-md hover:shadow-lg"
          >
            {isLoading ? t('common.loading', '読み込み中...') : t('common.save', '保存')}
          </button>
        </div>
      </form>

      {/* メールアドレス変更ダイアログ */}
      {showEmailChangeDialog && createPortal(
        <div
          className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-[9999]"
          onClick={(e) => {
            if (e.target === e.currentTarget) {
              setShowEmailChangeDialog(false);
              setNewEmail('');
            }
          }}
        >
          <div className="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl transform transition-all">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">
              {t('settings.profile.email_change_title', 'メールアドレス変更')}
            </h3>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  {t('settings.profile.current_email', '現在のメールアドレス')}
                </label>
                <input
                  type="email"
                  value={user?.email || ''}
                  disabled
                  className="w-full px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-gray-600 font-light"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  {t('settings.profile.new_email', '新しいメールアドレス')}
                </label>
                <input
                  type="email"
                  value={newEmail}
                  onChange={(e) => setNewEmail(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                  placeholder={t('settings.profile.new_email_placeholder', 'new@email.com')}
                />
              </div>

              <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p className="text-sm text-amber-800">
                  {t('settings.profile.email_change_warning', '新しいメールアドレスに確認メールが送信されます。確認リンクをクリックすると変更が適用されます。')}
                </p>
              </div>
            </div>

            <div className="flex gap-3 mt-6">
              <button
                type="button"
                onClick={() => {
                  setShowEmailChangeDialog(false);
                  setNewEmail('');
                }}
                className="flex-1 px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
              >
                {t('common.cancel', 'キャンセル')}
              </button>
              <button
                type="button"
                onClick={handleEmailChange}
                disabled={!newEmail.trim()}
                className="flex-1 px-4 py-2 text-white bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
              >
                {t('settings.profile.send_verification', '確認メール送信')}
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  );
};

// セキュリティ設定コンポーネント
const SecuritySettings: React.FC = () => {
  const { t } = useTranslation();
  const { token } = useAuth();
  const { toast } = useToastContext();
  const [passwordForm, setPasswordForm] = useState<PasswordFormData>({
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  });
  const [isPasswordLoading, setIsPasswordLoading] = useState(false);

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;

    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      toast.error(
        t('auth.password_mismatch', 'パスワードが一致しません'),
        t('auth.password_mismatch_desc', '新しいパスワードを再度確認してください')
      );
      return;
    }

    if (passwordForm.newPassword.length < 8) {
      toast.error(
        t('auth.password_min', 'パスワードは8文字以上で入力してください'),
        t('auth.password_min_desc', 'より強固なパスワードを設定してください')
      );
      return;
    }

    setIsPasswordLoading(true);

    try {
      const response = await AuthAPI.changePassword(token, {
        current_password: passwordForm.currentPassword,
        password: passwordForm.newPassword,
        password_confirmation: passwordForm.confirmPassword
      });

      if (response.success) {
        toast.success(
          t('auth.password_change_success', 'パスワードを変更しました'),
          t('auth.password_change_success_desc', 'アカウントのセキュリティが向上しました')
        );
        setPasswordForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
      } else {
        toast.error(
          t('auth.password_change_failed', 'パスワード変更に失敗しました'),
          response.message
        );
      }
    } catch (error) {
      console.error('Password change failed:', error);
      toast.error(
        t('auth.password_change_failed', 'パスワード変更に失敗しました'),
        t('auth.network_error', 'ネットワークエラーが発生しました')
      );
    } finally {
      setIsPasswordLoading(false);
    }
  };

  const handlePasswordChange = (field: keyof PasswordFormData, value: string) => {
    setPasswordForm(prev => ({ ...prev, [field]: value }));
  };

  return (
    <div className="space-y-8">
      <div className="text-center">
        <h3 className="text-xl font-light text-gray-800 mb-2">
          {t('settings.security.title', 'セキュリティ設定')}
        </h3>
        <p className="text-gray-500 text-sm font-light">
          {t('settings.security.description', 'アカウントのセキュリティを強化します')}
        </p>
      </div>

      {/* パスワード変更セクション */}
      <div className="bg-white/50 backdrop-blur-sm border border-white/30 rounded-2xl p-6 shadow-sm">
        <h4 className="text-lg font-light text-gray-800 mb-6 text-center">
          {t('settings.security.password.title', 'パスワード変更')}
        </h4>

        <form onSubmit={handlePasswordSubmit} className="space-y-5">
          <div className="space-y-4">
            {/* 現在のパスワード */}
            <div className="group">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                {t('settings.security.password.current', '現在のパスワード')}
              </label>
              <div className="relative">
                <input
                  type="password"
                  value={passwordForm.currentPassword}
                  onChange={(e) => handlePasswordChange('currentPassword', e.target.value)}
                  className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                  placeholder="現在のパスワードを入力"
                  required
                />
                <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                  <svg className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </div>
              </div>
            </div>

            {/* 新しいパスワード */}
            <div className="group">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                {t('settings.security.password.new', '新しいパスワード')}
              </label>
              <div className="relative">
                <input
                  type="password"
                  value={passwordForm.newPassword}
                  onChange={(e) => handlePasswordChange('newPassword', e.target.value)}
                  className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                  placeholder="8文字以上の新しいパスワード"
                  minLength={8}
                  required
                />
                <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                  <svg className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </div>
              </div>
            </div>

            {/* パスワード確認 */}
            <div className="group">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                {t('settings.security.password.confirm', 'パスワード確認')}
              </label>
              <div className="relative">
                <input
                  type="password"
                  value={passwordForm.confirmPassword}
                  onChange={(e) => handlePasswordChange('confirmPassword', e.target.value)}
                  className="w-full px-4 py-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-400 font-light"
                  placeholder="新しいパスワードを再入力"
                  minLength={8}
                  required
                />
                <div className="absolute inset-y-0 right-0 flex items-center pr-4">
                  <svg className="w-5 h-5 text-gray-400 group-focus-within:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </div>
              </div>
            </div>
          </div>

          <div className="pt-4">
            <button
              type="submit"
              disabled={isPasswordLoading}
              className="w-full py-4 text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-300 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 rounded-xl font-medium tracking-wide shadow-md hover:shadow-lg"
            >
              {isPasswordLoading ? t('common.loading', '読み込み中...') : t('settings.security.password.update', 'パスワードを更新')}
            </button>
          </div>
        </form>
      </div>

      {/* 2FA設定 */}
      <TOTPSettings />

      {/* WebAuthn設定 */}
      <WebAuthnSettings />
    </div>
  );
};

// セッション管理コンポーネント
const SessionsSettings: React.FC = () => {
  const { t } = useTranslation();
  const { token } = useAuth();
  const { toast } = useToastContext();
  const { confirm } = useConfirm();
  const [isLoading, setIsLoading] = useState(false);

  const handleLogoutOtherSessions = async () => {
    const confirmed = await confirm({
      title: t('settings.sessions.logout_confirm_title', 'セッション終了の確認'),
      message: t('settings.sessions.logout_confirm_message', '他のすべてのデバイスからログアウトしますか？この操作により、他のデバイスでの認証が無効になります。'),
      confirmText: t('settings.sessions.logout_confirm', 'ログアウト'),
      cancelText: t('common.cancel', 'キャンセル'),
      confirmVariant: 'danger'
    });

    if (!confirmed || !token) {
      return;
    }

    setIsLoading(true);

    try {
      const response = await AuthAPI.logoutOtherSessions(token);

      if (response.success) {
        toast.success(
          t('settings.sessions.logout_success', '他のセッションからログアウトしました'),
          t('settings.sessions.logout_success_desc', '他のデバイスでの認証が無効になりました')
        );
      } else {
        toast.error(
          t('settings.sessions.logout_failed', 'ログアウトに失敗しました'),
          response.message
        );
      }
    } catch (error) {
      console.error('Logout other sessions failed:', error);
      toast.error(
        t('settings.sessions.logout_failed', 'ログアウトに失敗しました'),
        t('auth.network_error', 'ネットワークエラーが発生しました')
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="space-y-8">
      <div className="text-center">
        <h3 className="text-xl font-light text-gray-800 mb-2">
          {t('settings.sessions.title', 'アクティブセッション')}
        </h3>
        <p className="text-gray-500 text-sm font-light">
          {t('settings.sessions.description', '現在ログインしているデバイスを管理します')}
        </p>
      </div>

      <div className="space-y-4">
        {/* 現在のセッション */}
        <div className="bg-white/50 backdrop-blur-sm border border-white/30 rounded-2xl p-6 shadow-sm">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
            <div className="flex items-start space-x-4">
              <div className="flex-shrink-0">
                <div className="w-12 h-12 bg-gradient-to-br from-green-100 to-emerald-100 rounded-xl flex items-center justify-center">
                  <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <div className="flex-1 min-w-0">
                <h4 className="text-base font-medium text-gray-900 mb-1">
                  {t('settings.sessions.current', '現在のセッション')}
                </h4>
                <p className="text-sm text-gray-600 mb-1">
                  Chrome - Tokyo, Japan
                </p>
                <p className="text-xs text-gray-500">
                  {new Date().toLocaleString()}
                </p>
              </div>
            </div>
            <div className="flex-shrink-0">
              <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200">
                <div className="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                {t('settings.sessions.active', 'アクティブ')}
              </span>
            </div>
          </div>
        </div>

        {/* 他のセッションをログアウト */}
        <div className="pt-4">
          <button
            onClick={handleLogoutOtherSessions}
            disabled={isLoading}
            className="w-full py-4 text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-4 focus:ring-red-200 border border-red-200 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 rounded-xl font-medium tracking-wide shadow-sm hover:shadow-md"
          >
            {isLoading ? t('common.loading', '読み込み中...') : t('settings.sessions.logout_all', '他のセッションをすべてログアウト')}
          </button>
        </div>
      </div>
    </div>
  );
};