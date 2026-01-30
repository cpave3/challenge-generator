/**
 * AI Model Configuration
 * 
 * This file centralizes AI model configuration for easy swapping between providers.
 * To switch providers, simply update the activeProvider and model constants below.
 */

export type AIProvider = 'openai' | 'anthropic';

export interface ModelConfig {
  provider: AIProvider;
  model: string;
  maxTokens: number;
  temperature: number;
}

// ============================================================
// CONFIGURATION - Modify these values to switch AI providers
// ============================================================

export const ACTIVE_PROVIDER: AIProvider = 'openai';

export const MODELS: Record<AIProvider, ModelConfig> = {
  openai: {
    provider: 'openai',
    model: 'gpt-4o',
    maxTokens: 8000,
    temperature: 0.7,
  },
  anthropic: {
    provider: 'anthropic',
    model: 'claude-3-sonnet-20240229',
    maxTokens: 8000,
    temperature: 0.7,
  },
};

// Get the active model configuration
export function getActiveModel(): ModelConfig {
  return MODELS[ACTIVE_PROVIDER];
}

// Helper to check which provider is active
export function isProvider(provider: AIProvider): boolean {
  return ACTIVE_PROVIDER === provider;
}
