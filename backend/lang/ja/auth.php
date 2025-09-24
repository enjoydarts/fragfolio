<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 認証言語ファイル
    |--------------------------------------------------------------------------
    |
    | 認証に関連するメッセージの日本語翻訳
    |
    */

    'user_not_found' => '指定されたメールアドレスのユーザーが見つかりません',
    'reset_link_sent' => 'パスワードリセットメールを送信しました',
    'reset_link_failed' => 'パスワードリセットメールの送信に失敗しました',
    'password_reset_success' => 'パスワードをリセットしました',
    'password_reset_failed' => 'パスワードリセットに失敗しました',
    'login_failed' => 'ログインに失敗しました',
    'registration_failed' => 'ユーザー登録に失敗しました',
    'logout_success' => 'ログアウトしました',
    'unauthorized' => '認証が必要です',
    'forbidden' => 'アクセスが拒否されました',
    'token_invalid' => '認証トークンが無効です',
    'token_expired' => '認証トークンの有効期限が切れています',
    'email_not_verified' => 'メールアドレスが認証されていません',
    'turnstile_verification_failed' => 'Turnstile検証に失敗しました',
    'validation_error' => 'バリデーションエラー',
    'registration_success' => 'ユーザー登録が完了しました',
    'login_success' => 'ログインしました',

    // 2FA関連
    'two_factor_setup_started' => '2要素認証の設定を開始しました。QRコードをスキャンして認証を完了してください。',
    'two_factor_enabled' => '2要素認証が正常に有効化されました',
    'two_factor_disabled' => '2要素認証が無効化されました',
    'two_factor_code_invalid' => '認証コードが正しくありません',
    'two_factor_failed' => '2要素認証に失敗しました',
    'two_factor_token_expired' => '2要素認証トークンが無効です。再度ログインしてください。',
    'two_factor_not_enabled' => '2要素認証が有効化されていません',
    'two_factor_already_enabled' => '2要素認証は既に有効化されています',
    'two_factor_already_disabled' => '2要素認証は無効化されています',
    'two_factor_already_confirmed' => '2要素認証は既に確認済みです',
    'two_factor_not_confirmed' => '2要素認証が確認されていません',
    'recovery_codes_not_generated' => 'リカバリコードが生成されていません',
    'recovery_codes_regenerated' => 'リカバリコードを再生成しました',
    'ai_normalization_failed' => 'AI正規化に失敗しました',
    'two_factor_required' => '2要素認証が必要です。認証コードを入力してください。',
    'two_factor_login_required' => '2要素認証コードを入力してください',
    'session_invalid' => '認証セッションが無効です。再度ログインしてください。',

    // WebAuthn関連
    'webauthn_credential_not_found' => 'WebAuthn認証器が見つかりません',
    'webauthn_credential_disabled_not_found' => '無効化されたWebAuthn認証器が見つかりません',
    'invalid_credentials' => 'メールアドレスまたはパスワードが正しくありません',
    'profile_update_success' => 'プロフィールを更新しました',
    'profile_update_failed' => 'プロフィール更新に失敗しました',
    'invalid_reset_link' => '無効なリセットリンクです',
    'token_refresh_success' => 'トークンを更新しました',
    'email_already_verified' => 'メールアドレスは既に認証済みです',
    'email_verified' => 'メールアドレスが認証されました',
    'verification_email_resent' => '認証メールを再送信しました',
    'invalid_verification_link' => '無効な認証リンクです',
    'webauthn_credential_not_found' => '指定された認証器が見つかりません',
    'webauthn_disabled_credential_not_found' => '無効化されたWebAuthn認証器が見つかりません',
    'webauthn_credential_disabled' => 'WebAuthn認証器を無効化しました',
    'webauthn_credential_enabled' => 'WebAuthn認証器を有効化しました',
    'webauthn_credential_disable_failed' => 'WebAuthn認証器の無効化に失敗しました',
    'webauthn_credential_enable_failed' => 'WebAuthn認証器の有効化に失敗しました',
    'webauthn_alias_updated' => 'WebAuthn認証器のエイリアスを更新しました',
    'webauthn_alias_update_failed' => 'エイリアスの更新に失敗しました',
    'webauthn_credential_deleted' => '認証器を削除しました',
    'webauthn_credentials_fetch_failed' => 'WebAuthn認証器の取得に失敗しました',
    'webauthn_credential_delete_failed' => 'WebAuthn認証器の削除に失敗しました',

    // Email change messages
    'email_exists' => 'このメールアドレスは既に使用されています',
    'email_change_request_sent' => 'メールアドレス変更の確認メールを送信しました',
    'email_change_request_failed' => 'メールアドレス変更リクエストに失敗しました',
    'email_change_completed' => 'メールアドレスの変更が完了しました',
    'email_change_current_verified' => '現在のメールアドレスの認証が完了しました',
    'email_change_new_verified' => '新しいメールアドレスの認証が完了しました',

    // Email verification subjects and content
    'verify_current_email_subject' => '【重要】メールアドレス変更の確認（現在のメールアドレス）',
    'verify_new_email_subject' => '【重要】メールアドレス変更の確認（新しいメールアドレス）',
    'verify_current_email_title' => 'メールアドレス変更の確認',
    'verify_new_email_title' => '新しいメールアドレスの確認',
    'dear_user' => ':name 様',
    'hello' => 'こんにちは',
    'verify_current_email_message' => 'アカウントのメールアドレスを :new_email に変更するリクエストを受け付けました。現在のメールアドレスの確認のため、下記のボタンをクリックしてください。',
    'verify_new_email_message' => 'このメールアドレスを :current_email からの変更先として設定するリクエストを受け付けました。新しいメールアドレスの確認のため、下記のボタンをクリックしてください。',
    'important' => '重要',
    'verify_current_email_warning' => 'このリクエストに心当たりがない場合は、このメールを無視してください。',
    'verify_new_email_warning' => 'このリクエストに心当たりがない場合は、このメールを無視してください。',
    'verify_current_email_button' => '現在のメールアドレスを確認する',
    'verify_new_email_button' => '新しいメールアドレスを確認する',
    'verify_email_manual_instruction' => 'ボタンが機能しない場合は、以下のURLを直接ブラウザのアドレスバーにコピーしてアクセスしてください',
    'verify_email_expire_notice' => 'この確認リンクは24時間で期限切れとなります。',
    'email_footer' => 'このメールは fragfolio から送信されました',
    'no_reply_notice' => 'このメールは送信専用です。返信しないでください。',
    'email_same_as_current' => '現在のメールアドレスと同じです',
    'email_already_taken' => 'このメールアドレスは既に使用されています',
];
