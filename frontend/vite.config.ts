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
    include: [
      'tests/**/*.{test,spec}.{js,ts,tsx}',
      'src/**/*.{test,spec}.{js,ts,tsx}',
    ],
    testTimeout: 30000,
    hookTimeout: 30000,
    teardownTimeout: 30000,
    typecheck: {
      tsconfig: './tsconfig.test.json',
    },
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov', 'html'],
      reportsDirectory: './coverage',
      exclude: [
        'node_modules/',
        'tests/',
        'src/test/',
        '**/*.config.*',
        '**/*.d.ts',
      ],
      all: true,
      thresholds: {
        lines: 80,
        functions: 40,
        branches: 70,
        statements: 80,
      },
    },
  },
});
