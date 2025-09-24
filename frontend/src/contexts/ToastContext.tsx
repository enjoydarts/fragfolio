import React from 'react';
import { useToast } from '../hooks/useToast';
import { ToastContainer } from '../components/ui/ToastContainer';
import { ToastContext } from './toast';

export const ToastProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const toastState = useToast();

  return (
    <ToastContext.Provider value={toastState}>
      {children}
      <ToastContainer />
    </ToastContext.Provider>
  );
};
