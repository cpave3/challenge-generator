#!/usr/bin/env node

import 'dotenv/config';

/**
 * Code Kata Generator CLI
 * 
 * A CLI tool for generating coding challenges using AI.
 * 
 * Usage:
 *   challenge-gen generate -l typescript -d 2 -t "async patterns"
 *   challenge-gen list -l typescript
 *   challenge-gen validate ./challenges/typescript/001-example
 */

import { Command } from 'commander';
import { createGenerateCommand } from './commands/generate.js';
import { createListCommand } from './commands/list.js';
import { createValidateCommand } from './commands/validate.js';
import { model } from './lib/models.js';

const program = new Command();

program
  .name('challenge-gen')
  .description('CLI tool for generating coding challenges using AI')
  .version('1.0.0');

// Add commands
program.addCommand(createGenerateCommand());
program.addCommand(createListCommand());
program.addCommand(createValidateCommand());

// Global options
program
  .option('-v, --verbose', 'Enable verbose output')
  .hook('preAction', (thisCommand) => {
    if (thisCommand.opts().verbose) {
      console.log('Using AI model: hf:moonshotai/Kimi-K2.5');
      console.log('');
    }
  });

// Parse command line arguments
program.parse();

// Show help if no command provided
if (!process.argv.slice(2).length) {
  program.outputHelp();
}
