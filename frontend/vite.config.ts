/// <reference types="vitest" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
    include: ['tests/**/*.{test,spec}.{js,ts,tsx}', 'src/**/*.{test,spec}.{js,ts,tsx}'],
    typecheck: {
      tsconfig: './tsconfig.test.json',
    },
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov', 'html'],
      exclude: [
        'node_modules/',
        'tests/',
        'src/test/',
        '**/*.config.*',
        '**/*.d.ts',
      ],
    },
  },
});
