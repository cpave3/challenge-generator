/**
 * List Command
 * 
 * Lists all generated challenges, optionally filtered by language.
 */

import { Command } from 'commander';
import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

interface Challenge {
  number: number;
  slug: string;
  name: string;
  path: string;
}

interface LanguageChallenges {
  language: string;
  challenges: Challenge[];
}

export function createListCommand(): Command {
  const command = new Command('list');
  
  command
    .description('List all generated challenges')
    .option('-l, --language <lang>', 'Filter by language (typescript, php, python)')
    .action(async (options) => {
      try {
        const basePath = path.join(__dirname, '..', '..', 'challenges');
        const languages = options.language 
          ? [options.language.toLowerCase()]
          : ['typescript', 'php', 'python'];
        
        const results: LanguageChallenges[] = [];
        
        for (const language of languages) {
          const challenges = await listChallengesForLanguage(basePath, language);
          if (challenges.length > 0) {
            results.push({ language, challenges });
          }
        }
        
        if (results.length === 0) {
          console.log('📭 No challenges found.');
          console.log('');
          console.log('Generate your first challenge with:');
          console.log('  challenge-gen generate -l typescript -d 2');
          return;
        }
        
        console.log('📚 Code Challenges');
        console.log('==================\n');
        
        for (const { language, challenges } of results) {
          console.log(`${getLanguageEmoji(language)} ${language.toUpperCase()} (${challenges.length} challenges)`);
          console.log('─'.repeat(50));
          
          for (const challenge of challenges.sort((a, b) => a.number - b.number)) {
            console.log(`  ${String(challenge.number).padStart(3, '0')}. ${challenge.name || challenge.slug}`);
          }
          
          console.log('');
        }
        
        console.log(`Total: ${results.reduce((sum, r) => sum + r.challenges.length, 0)} challenges`);
        
      } catch (error) {
        console.error('❌ Error:', error instanceof Error ? error.message : String(error));
        process.exit(1);
      }
    });
  
  return command;
}

async function listChallengesForLanguage(basePath: string, language: string): Promise<Challenge[]> {
  const languagePath = path.join(basePath, language);
  
  try {
    const entries = await fs.readdir(languagePath, { withFileTypes: true });
    const challenges: Challenge[] = [];
    
    for (const entry of entries) {
      if (!entry.isDirectory()) continue;
      
      const match = entry.name.match(/^(\d{3})-(.+)$/);
      if (!match) continue;
      
      const number = parseInt(match[1], 10);
      const slug = match[2];
      
      // Try to read the challenge name from README
      let name = slug;
      try {
        const readmePath = path.join(languagePath, entry.name, 'README.md');
        const readme = await fs.readFile(readmePath, 'utf-8');
        const titleMatch = readme.match(/^#\s+(.+)$/m);
        if (titleMatch) {
          name = titleMatch[1];
        }
      } catch {
        // Ignore errors reading README
      }
      
      challenges.push({
        number,
        slug,
        name,
        path: path.join(languagePath, entry.name),
      });
    }
    
    return challenges;
  } catch (error) {
    // Directory doesn't exist
    return [];
  }
}

function getLanguageEmoji(language: string): string {
  const emojis: Record<string, string> = {
    typescript: '🔷',
    php: '🐘',
    python: '🐍',
  };
  return emojis[language] || '📝';
}
