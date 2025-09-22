import React, { createContext, useContext } from 'react';
import { useToast } from '../hooks/useToast';
import { ToastContainer } from '../components/ui/ToastContainer';

const ToastContext = createContext<ReturnType<typeof useToast> | undefined>(undefined);

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const toastState = useToast();

  return (
    <ToastContext.Provider value={toastState}>
      {children}
      <ToastContainer />
    </ToastContext.Provider>
  );
};

export const useToastContext = () => {
  const context = useContext(ToastContext);
  if (context === undefined) {
    throw new Error('useToastContext must be used within a ToastProvider');
  }
  return context;
};