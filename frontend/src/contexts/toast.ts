import { createContext } from 'react';
import { useToast } from '../hooks/useToast';

export const ToastContext = createContext<ReturnType<typeof useToast> | undefined>(
  undefined
);