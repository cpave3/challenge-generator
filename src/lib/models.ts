import { createOpenAICompatible } from "@ai-sdk/openai-compatible";
import type { LanguageModel } from 'ai';

/**
 * AI Model Configuration
 *
 * Simply define and export the model you want to use.
 * Example: export const model = openai('gpt-4o');
 */

const provider = createOpenAICompatible({
  name: "synthetic",
  apiKey: process.env.SYNTHETIC_API_KEY,
  baseURL: "https://api.synthetic.new/openai/v1",
  includeUsage: true,
});

export const model: LanguageModel = provider("hf:moonshotai/Kimi-K2.5");
