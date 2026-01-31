/**
 * AI Service using Vercel AI SDK
 * 
 * This service provides a simple interface for generating challenges using AI.
 */

import { generateText, Output } from 'ai';
import { z } from 'zod';
import { model } from './models.js';

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

export async function generateChallenge(
  systemPrompt: string,
  userPrompt: string
): Promise<ChallengeGenerationResponse> {
  try {
    const { output } = await generateText({
      model,
      system: systemPrompt,
      prompt: userPrompt,
      temperature: 0.7,
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
