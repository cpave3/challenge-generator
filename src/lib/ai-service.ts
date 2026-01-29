/**
 * AI Service using Vercel AI SDK
 * 
 * This service abstracts AI provider interactions using the Vercel AI SDK,
 * making it easy to switch between OpenAI, Anthropic, and other providers.
 */

import { generateText } from 'ai';
import { openai } from '@ai-sdk/openai';
import { getActiveModel, type ModelConfig } from './models.js';

export interface ChallengeGenerationResponse {
  challengeName: string;
  slug: string;
  difficulty: number;
  skills: string[];
  estimatedTime: string;
  readme: string;
  starterFiles: Array<{ path: string; content: string }>;
  solutionFiles: Array<{ path: string; content: string }>;
  testFiles: Array<{ path: string; content: string }>;
}

export class AIService {
  private config: ModelConfig;

  constructor(config?: ModelConfig) {
    this.config = config || getActiveModel();
  }

  async generateChallenge(
    systemPrompt: string,
    userPrompt: string
  ): Promise<ChallengeGenerationResponse> {
    try {
      const model = this.createModel();
      
      const { text } = await generateText({
        model,
        system: systemPrompt,
        prompt: userPrompt,
        maxTokens: this.config.maxTokens,
        temperature: this.config.temperature,
      });

      // Parse the JSON response
      const parsed = this.parseResponse(text);
      return parsed;
    } catch (error) {
      console.error('Error generating challenge:', error);
      throw new Error(
        `Failed to generate challenge: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  private createModel() {
    switch (this.config.provider) {
      case 'openai':
        // openai is a function that takes a model ID and returns a model instance
        return (openai as any)(this.config.model);
      default:
        throw new Error(`Unsupported provider: ${this.config.provider}`);
    }
  }

  private parseResponse(text: string): ChallengeGenerationResponse {
    try {
      // Try to extract JSON from the response
      // The AI might wrap it in markdown code blocks
      const jsonMatch = text.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
      const jsonString = jsonMatch ? jsonMatch[1] : text;
      
      const parsed = JSON.parse(jsonString.trim());
      
      // Validate required fields
      const requiredFields = [
        'challengeName',
        'slug',
        'difficulty',
        'skills',
        'estimatedTime',
        'readme',
        'starterFiles',
        'solutionFiles',
        'testFiles',
      ];
      
      for (const field of requiredFields) {
        if (!(field in parsed)) {
          throw new Error(`Missing required field: ${field}`);
        }
      }
      
      return parsed as ChallengeGenerationResponse;
    } catch (error) {
      console.error('Failed to parse AI response:', text);
      throw new Error(
        `Failed to parse AI response: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }
}
