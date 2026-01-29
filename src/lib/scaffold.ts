/**
 * Challenge Scaffolding Utilities
 * 
 * Handles creating the directory structure and writing files for challenges.
 */

import fs from 'fs/promises';
import path from 'path';
import type { ChallengeGenerationResponse } from './ai-service.js';

export interface ScaffoldOptions {
  language: string;
  challengeNumber: number;
  challengeData: ChallengeGenerationResponse;
  basePath: string;
}

export async function scaffoldChallenge(options: ScaffoldOptions): Promise<string> {
  const { language, challengeNumber, challengeData, basePath } = options;
  
  // Create challenge directory name
  const challengeDirName = `${String(challengeNumber).padStart(3, '0')}-${challengeData.slug}`;
  const challengePath = path.join(basePath, 'challenges', language, challengeDirName);
  
  // Ensure parent directories exist
  await fs.mkdir(challengePath, { recursive: true });
  
  // Create subdirectories
  const starterPath = path.join(challengePath, 'starter');
  const solutionPath = path.join(challengePath, 'solution');
  const testsPath = path.join(challengePath, 'tests');
  
  await fs.mkdir(starterPath, { recursive: true });
  await fs.mkdir(solutionPath, { recursive: true });
  await fs.mkdir(testsPath, { recursive: true });
  
  // Write README.md
  await fs.writeFile(
    path.join(challengePath, 'README.md'),
    challengeData.readme,
    'utf-8'
  );
  
  // Write starter files
  for (const file of challengeData.starterFiles) {
    const filePath = path.join(starterPath, file.path);
    await ensureDirectoryExists(filePath);
    await fs.writeFile(filePath, file.content, 'utf-8');
  }
  
  // Write solution files
  for (const file of challengeData.solutionFiles) {
    const filePath = path.join(solutionPath, file.path);
    await ensureDirectoryExists(filePath);
    await fs.writeFile(filePath, file.content, 'utf-8');
  }
  
  // Write test files
  for (const file of challengeData.testFiles) {
    const filePath = path.join(testsPath, file.path);
    await ensureDirectoryExists(filePath);
    await fs.writeFile(filePath, file.content, 'utf-8');
  }
  
  return challengePath;
}

async function ensureDirectoryExists(filePath: string): Promise<void> {
  const dir = path.dirname(filePath);
  await fs.mkdir(dir, { recursive: true });
}

export async function getNextChallengeNumber(language: string, basePath: string): Promise<number> {
  const challengesDir = path.join(basePath, 'challenges', language);
  
  try {
    const entries = await fs.readdir(challengesDir);
    const challengeDirs = entries.filter(entry => /^\d{3}-/.test(entry));
    
    if (challengeDirs.length === 0) {
      return 1;
    }
    
    // Extract numbers and find the highest
    const numbers = challengeDirs.map(dir => {
      const match = dir.match(/^(\d{3})-/);
      return match ? parseInt(match[1], 10) : 0;
    });
    
    return Math.max(...numbers) + 1;
  } catch (error) {
    // Directory doesn't exist yet
    return 1;
  }
}
