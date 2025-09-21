import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { LoginForm } from '../components/auth/LoginForm';
import { RegisterForm } from '../components/auth/RegisterForm';
import logoSvg from '../assets/logo.svg';

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
    <div className="min-h-screen bg-gradient-to-br from-orange-50 to-orange-100 flex flex-col justify-center items-center px-4">
      <div className="mb-8">
        <img src={logoSvg} alt="fragfolio" className="h-16 mx-auto" />
      </div>
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
    </div>
  );
};
