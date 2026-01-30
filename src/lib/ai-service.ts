/**
 * AI Service using Vercel AI SDK
 * 
 * This service abstracts AI provider interactions using the Vercel AI SDK,
 * making it easy to switch between OpenAI, Anthropic, and other providers.
 */

import { generateText, Output } from 'ai';
import { openai } from '@ai-sdk/openai';
import { z } from 'zod';
import { getActiveModel, type ModelConfig } from './models.js';

// Define the Zod schema for challenge generation
const ChallengeSchema = z.object({
  challengeName: z.string().describe('The name/title of the coding challenge'),
  slug: z.string().describe('URL-friendly slug for the challenge'),
  difficulty: z.number().describe('Difficulty level (1-4)'),
  skills: z.array(z.string()).describe('List of skills/practices this challenge covers'),
  estimatedTime: z.string().describe('Estimated time to complete (e.g., "30-45 minutes")'),
  readme: z.string().describe('Full README content in markdown format'),
  starterFiles: z.array(z.object({
    path: z.string().describe('Relative path to the file'),
    content: z.string().describe('File content')
  })).describe('Starter code files for the challenge'),
  solutionFiles: z.array(z.object({
    path: z.string().describe('Relative path to the file'),
    content: z.string().describe('File content')
  })).describe('Complete solution files'),
  testFiles: z.array(z.object({
    path: z.string().describe('Relative path to the file'),
    content: z.string().describe('File content')
  })).describe('Test files to verify the solution')
});

// Export the type derived from the schema
export type ChallengeGenerationResponse = z.infer<typeof ChallengeSchema>;

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
      
      const { output } = await generateText({
        model,
        system: systemPrompt,
        prompt: userPrompt,
        temperature: this.config.temperature,
        output: Output.object({
          schema: ChallengeSchema,
          name: 'Challenge',
          description: 'A coding challenge with starter code, solution, and tests'
        })
      });

      return output;
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
}
