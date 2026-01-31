import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    globals: false,
    restoreMocks: true,
    clearMocks: true,
    fakeTimers: {
      toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'],
    },
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      enabled: false,
      thresholds: {
        lines: 80,
        functions: 80,
        branches: 80,
        statements: 80,
      },
    },
    typecheck: {
      enabled: true,
      checker: 'tsc',
    },
  },
});
