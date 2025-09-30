import React from 'react';
import {
  BrowserRouter as Router,
  Routes,
  Route,
  Navigate,
} from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { ToastProvider } from './contexts/ToastContext';
import { useAuth } from './hooks/useAuth';
import { Header } from './components/layout/Header';
import { LoadingSpinner } from './components/layout/LoadingSpinner';
import { ProtectedRoute } from './components/auth/ProtectedRoute';
import { HomePage } from './pages/HomePage';
import { AuthPage } from './pages/AuthPage';
import { AccountSettings } from './pages/AccountSettings';
import { EmailVerification } from './pages/EmailVerification';
import { EmailVerificationSuccess } from './pages/EmailVerificationSuccess';
import { EmailVerificationError } from './pages/EmailVerificationError';
import { ForgotPassword } from './pages/ForgotPassword';
import { ResetPassword } from './pages/ResetPassword';
import { PasswordResetError } from './pages/PasswordResetError';
import FragranceRegistration from './pages/FragranceRegistration';
import { Test } from './Test';

const AppContent: React.FC = () => {
  const { loading } = useAuth();

  if (loading) {
    return <LoadingSpinner />;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Header />
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/auth" element={<AuthPage />} />
        <Route
          path="/settings"
          element={
            <ProtectedRoute>
              <AccountSettings />
            </ProtectedRoute>
          }
        />
        <Route
          path="/fragrance/register"
          element={
            <ProtectedRoute>
              <FragranceRegistration />
            </ProtectedRoute>
          }
        />
        <Route
          path="/collection"
          element={
            <ProtectedRoute>
              <div className="p-8">
                <h1 className="text-2xl font-bold">Collection (Coming Soon)</h1>
                <p className="mt-4 text-gray-600">Your fragrance collection will be displayed here.</p>
              </div>
            </ProtectedRoute>
          }
        />
        <Route path="/email-verification" element={<EmailVerification />} />
        <Route
          path="/email-verification-success"
          element={<EmailVerificationSuccess />}
        />
        <Route
          path="/email-verification-error"
          element={<EmailVerificationError />}
        />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="/password-reset-error" element={<PasswordResetError />} />
        <Route path="/test" element={<Test />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </div>
  );
};

function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <Router>
          <AppContent />
        </Router>
      </ToastProvider>
    </AuthProvider>
  );
}

export default App;
