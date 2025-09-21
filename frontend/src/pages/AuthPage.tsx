import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { LoginForm } from '../components/auth/LoginForm';
import { RegisterForm } from '../components/auth/RegisterForm';

export const AuthPage: React.FC = () => {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const navigate = useNavigate();

  const handleLoginSuccess = () => {
    window.location.href = '/';
  };

  const handleRegisterSuccess = () => {
    navigate('/email-verification');
  };

  return (
    <>
      {mode === 'login' ? (
        <LoginForm
          onSuccess={handleLoginSuccess}
          onRegisterClick={() => setMode('register')}
        />
      ) : (
        <RegisterForm
          onSuccess={handleRegisterSuccess}
          onLoginClick={() => setMode('login')}
        />
      )}
    </>
  );
};
