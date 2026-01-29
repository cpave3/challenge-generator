/**
 * Validate Command
 * 
 * Validates that a challenge has all required components.
 */

import { Command } from 'commander';
import fs from 'fs/promises';
import path from 'path';

interface ValidationResult {
  valid: boolean;
  errors: string[];
  warnings: string[];
}

export function createValidateCommand(): Command {
  const command = new Command('validate');
  
  command
    .description('Validate a challenge has all required components')
    .argument('<challenge-path>', 'Path to the challenge directory')
    .action(async (challengePath) => {
      try {
        const resolvedPath = path.resolve(challengePath);
        
        console.log(`🔍 Validating: ${resolvedPath}\n`);
        
        const result = await validateChallenge(resolvedPath);
        
        if (result.errors.length === 0 && result.warnings.length === 0) {
          console.log('✅ Challenge is valid!');
          return;
        }
        
        if (result.errors.length > 0) {
          console.log('❌ Errors:');
          for (const error of result.errors) {
            console.log(`   • ${error}`);
          }
          console.log('');
        }
        
        if (result.warnings.length > 0) {
          console.log('⚠️  Warnings:');
          for (const warning of result.warnings) {
            console.log(`   • ${warning}`);
          }
          console.log('');
        }
        
        if (result.errors.length > 0) {
          process.exit(1);
        }
        
      } catch (error) {
        console.error('❌ Error:', error instanceof Error ? error.message : String(error));
        process.exit(1);
      }
    });
  
  return command;
}

async function validateChallenge(challengePath: string): Promise<ValidationResult> {
  const result: ValidationResult = {
    valid: true,
    errors: [],
    warnings: [],
  };
  
  // Check if directory exists
  try {
    const stats = await fs.stat(challengePath);
    if (!stats.isDirectory()) {
      result.errors.push('Path is not a directory');
      result.valid = false;
      return result;
    }
  } catch {
    result.errors.push('Challenge directory does not exist');
    result.valid = false;
    return result;
  }
  
  // Check for README.md
  const readmePath = path.join(challengePath, 'README.md');
  try {
    await fs.access(readmePath);
    const readme = await fs.readFile(readmePath, 'utf-8');
    
    // Validate README content
    if (!readme.match(/^#\s+/m)) {
      result.warnings.push('README.md is missing a title');
    }
    
    if (!readme.includes('## Requirements')) {
      result.warnings.push('README.md is missing Requirements section');
    }
    
    if (!readme.includes('## Definition of Done')) {
      result.warnings.push('README.md is missing Definition of Done section');
    }
  } catch {
    result.errors.push('Missing README.md');
  }
  
  // Check for starter directory
  const starterPath = path.join(challengePath, 'starter');
  try {
    const starterStats = await fs.stat(starterPath);
    if (!starterStats.isDirectory()) {
      result.errors.push('starter/ exists but is not a directory');
    } else {
      const starterFiles = await fs.readdir(starterPath);
      if (starterFiles.length === 0) {
        result.warnings.push('starter/ directory is empty');
      }
    }
  } catch {
    result.errors.push('Missing starter/ directory');
  }
  
  // Check for solution directory
  const solutionPath = path.join(challengePath, 'solution');
  try {
    const solutionStats = await fs.stat(solutionPath);
    if (!solutionStats.isDirectory()) {
      result.errors.push('solution/ exists but is not a directory');
    } else {
      const solutionFiles = await fs.readdir(solutionPath);
      if (solutionFiles.length === 0) {
        result.warnings.push('solution/ directory is empty');
      }
    }
  } catch {
    result.errors.push('Missing solution/ directory');
  }
  
  // Check for tests directory
  const testsPath = path.join(challengePath, 'tests');
  try {
    const testsStats = await fs.stat(testsPath);
    if (!testsStats.isDirectory()) {
      result.errors.push('tests/ exists but is not a directory');
    } else {
      const testFiles = await fs.readdir(testsPath);
      if (testFiles.length === 0) {
        result.warnings.push('tests/ directory is empty');
      }
      
      // Check for common test file patterns
      const hasTestFiles = testFiles.some(f => 
        f.endsWith('.test.ts') || 
        f.endsWith('.spec.ts') ||
        f.endsWith('.test.php') ||
        f.endsWith('Test.php') ||
        f.endsWith('_test.py') ||
        f.endsWith('test_*.py')
      );
      
      if (!hasTestFiles) {
        result.warnings.push('No recognizable test files found in tests/');
      }
    }
  } catch {
    result.errors.push('Missing tests/ directory');
  }
  
  // Check for package management files (language-specific)
  const packageJsonPath = path.join(challengePath, 'package.json');
  const composerJsonPath = path.join(challengePath, 'composer.json');
  const requirementsPath = path.join(challengePath, 'requirements.txt');
  
  const hasPackageFile = await fileExists(packageJsonPath) || 
                         await fileExists(composerJsonPath) || 
                         await fileExists(requirementsPath);
  
  if (!hasPackageFile) {
    result.warnings.push('No package.json, composer.json, or requirements.txt found');
  }
  
  result.valid = result.errors.length === 0;
  return result;
}

async function fileExists(filePath: string): Promise<boolean> {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}
