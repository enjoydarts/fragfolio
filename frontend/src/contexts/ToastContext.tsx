import React, { createContext } from 'react';
import { useToast } from '../hooks/useToast';
import { ToastContainer } from '../components/ui/ToastContainer';

const ToastContext = createContext<ReturnType<typeof useToast> | undefined>(
  undefined
);

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
