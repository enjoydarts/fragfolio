<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user.
    |
    */

    'user_not_found' => 'User not found with the specified email address',
    'reset_link_sent' => 'Password reset email has been sent',
    'reset_link_failed' => 'Failed to send password reset email',
    'password_reset_success' => 'Password has been reset successfully',
    'password_reset_failed' => 'Password reset failed',
    'login_failed' => 'Login failed',
    'registration_failed' => 'Registration failed',
    'logout_success' => 'Logged out successfully',
    'unauthorized' => 'Authentication required',
    'forbidden' => 'Access denied',
    'token_invalid' => 'Authentication token is invalid',
    'token_expired' => 'Authentication token has expired',
    'email_not_verified' => 'Email address is not verified',
    'turnstile_verification_failed' => 'Turnstile verification failed',
    'validation_error' => 'Validation error',
    'registration_success' => 'Registration completed successfully',
    'login_success' => 'Logged in successfully',
    'invalid_credentials' => 'Invalid email address or password',
    'profile_update_success' => 'Profile updated successfully',
    'profile_update_failed' => 'Failed to update profile',
    'invalid_reset_link' => 'Invalid reset link',
    'token_refresh_success' => 'Token refreshed successfully',
    'email_already_verified' => 'Email address is already verified',
    'email_verified' => 'Email address has been verified',
    'verification_email_resent' => 'Verification email has been resent',
    'invalid_verification_link' => 'Invalid verification link',
    'webauthn_credential_not_found' => 'Specified authenticator not found',
    'webauthn_disabled_credential_not_found' => 'Disabled WebAuthn authenticator not found',
    'webauthn_credential_disabled' => 'WebAuthn authenticator has been disabled',
    'webauthn_credential_enabled' => 'WebAuthn authenticator has been enabled',
    'webauthn_credential_disable_failed' => 'Failed to disable WebAuthn authenticator',
    'webauthn_credential_enable_failed' => 'Failed to enable WebAuthn authenticator',
    'webauthn_alias_updated' => 'WebAuthn authenticator alias has been updated',
    'webauthn_alias_update_failed' => 'Failed to update alias',
    'webauthn_credential_deleted' => 'Authenticator has been deleted',
    'webauthn_credentials_fetch_failed' => 'Failed to retrieve WebAuthn authenticators',
    'webauthn_credential_delete_failed' => 'Failed to delete WebAuthn authenticator',

    // Email change messages
    'email_exists' => 'This email address is already in use',
    'email_change_request_sent' => 'Email change verification emails have been sent',
    'email_change_request_failed' => 'Failed to request email change',
    'email_change_completed' => 'Email address change has been completed',
    'email_change_current_verified' => 'Current email address verification completed',
    'email_change_new_verified' => 'New email address verification completed',
    'current_password_incorrect' => 'Current password is incorrect',
    'password_change_success' => 'Password changed successfully',

    // Email verification subjects and content
    'verify_current_email_subject' => '[Important] Email Address Change Verification (Current Email)',
    'verify_new_email_subject' => '[Important] Email Address Change Verification (New Email)',
    'verify_current_email_title' => 'Email Address Change Verification',
    'verify_new_email_title' => 'New Email Address Verification',
    'dear_user' => 'Dear :name',
    'hello' => 'Hello',
    'verify_current_email_message' => 'We have received a request to change your account email address to :new_email. Please click the button below to verify your current email address.',
    'verify_new_email_message' => 'We have received a request to set this email address as the new address for the account currently using :current_email. Please click the button below to verify this new email address.',
    'important' => 'Important',
    'verify_current_email_warning' => 'If you did not make this request, please ignore this email.',
    'verify_new_email_warning' => 'If you did not make this request, please ignore this email.',
    'verify_current_email_button' => 'Verify Current Email Address',
    'verify_new_email_button' => 'Verify New Email Address',
    'verify_email_manual_instruction' => 'If the button doesn\'t work, please copy and paste the following URL directly into your browser\'s address bar',
    'verify_email_expire_notice' => 'This verification link will expire in 24 hours.',
    'email_footer' => 'This email was sent from fragfolio',
    'no_reply_notice' => 'This is a no-reply email. Please do not reply to this message.',

    // 2FA related
    'two_factor_setup_started' => '2FA setup has been started. Please scan the QR code to complete authentication.',
    'two_factor_enabled' => 'Two-factor authentication has been enabled successfully',
    'two_factor_disabled' => 'Two-factor authentication has been disabled',
    'two_factor_code_invalid' => 'The authentication code is incorrect',
    'two_factor_not_enabled' => 'Two-factor authentication is not enabled',
    'two_factor_already_enabled' => 'Two-factor authentication is already enabled',
    'two_factor_already_disabled' => 'Two-factor authentication is disabled',
    'two_factor_already_confirmed' => 'Two-factor authentication is already confirmed',
    'two_factor_not_confirmed' => 'Two-factor authentication is not confirmed',
    'recovery_codes_not_generated' => 'Recovery codes have not been generated',
    'recovery_codes_regenerated' => 'Recovery codes have been regenerated',
    'ai_normalization_failed' => 'AI normalization failed',
    'two_factor_required' => 'Two-factor authentication is required. Please enter your authentication code.',
    'two_factor_login_required' => 'Please enter your two-factor authentication code',
    'session_invalid' => 'Authentication session is invalid. Please log in again.',

    // Authorization related
    'unauthorized_admin' => 'Administrator privileges required',
    'unauthorized_action' => 'You do not have permission to perform this action',
    'insufficient_permissions' => 'Insufficient permissions',
    'access_denied' => 'Access denied',
];
