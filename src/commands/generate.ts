/**
 * Generate Command
 *
 * Generates a new coding challenge using AI.
 */

import { Command } from "commander";
import path from "path";
import { fileURLToPath } from "url";
import { generateChallenge } from "../lib/ai-service.js";
import {
  CHALLENGE_GENERATOR_SYSTEM_PROMPT,
  buildUserPrompt,
} from "../lib/prompts.js";
import { scaffoldChallenge, getNextChallengeNumber } from "../lib/scaffold.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export function createGenerateCommand(): Command {
  const command = new Command("generate");

  command
    .description("Generate a new coding challenge using AI")
    .option(
      "-l, --language <lang>",
      "Programming language (typescript, php, python)",
      "typescript",
    )
    .option("-d, --difficulty <level>", "Difficulty level (1-4)", "2")
    .option("-t, --topic <topic>", "Topic or skill to focus on")
    .action(async (options) => {
      try {
        // Validate options
        const language = options.language.toLowerCase();
        const validLanguages = ["typescript", "php", "python"];
        if (!validLanguages.includes(language)) {
          console.error(
            `Error: Invalid language "${language}". Must be one of: ${validLanguages.join(", ")}`,
          );
          process.exit(1);
        }

        const difficulty = parseInt(options.difficulty, 10);
        if (isNaN(difficulty) || difficulty < 1 || difficulty > 4) {
          console.error("Error: Difficulty must be a number between 1 and 4");
          process.exit(1);
        }

        // Check for required API key
        // if (!process.env.OPENAI_API_KEY) {
        //   console.error('Error: OPENAI_API_KEY environment variable is required');
        //   console.error('Set it with: export OPENAI_API_KEY=your_key_here');
        //   process.exit(1);
        // }

        console.log(
          `🎯 Generating Level ${difficulty} ${language} challenge...`,
        );
        if (options.topic) {
          console.log(`📋 Topic: ${options.topic}`);
        }
        console.log("");

        // Get next challenge number
        const basePath = path.join(__dirname, "..", "..");
        const challengeNumber = await getNextChallengeNumber(
          language,
          basePath,
        );

        // Build prompts
        const userPrompt = buildUserPrompt({
          language,
          difficulty,
          topic: options.topic,
          challengeNumber,
        });

        // Generate challenge
        console.log("🤖 Asking AI to generate challenge...");
        const challengeData = await generateChallenge(
          CHALLENGE_GENERATOR_SYSTEM_PROMPT,
          userPrompt,
        );

        console.log(`✨ Generated: ${challengeData.challengeName}`);
        console.log(`   Skills: ${challengeData.skills.join(", ")}`);
        console.log(`   Estimated time: ${challengeData.estimatedTime}`);
        console.log("");

        // Scaffold the challenge
        console.log("📁 Creating challenge structure...");
        const challengePath = await scaffoldChallenge({
          language,
          challengeNumber,
          challengeData,
          basePath,
        });

        console.log("✅ Challenge created successfully!");
        console.log("");
        console.log(`📂 Location: ${challengePath}`);
        console.log("");
        console.log("Next steps:");
        console.log(`  1. Review the generated challenge at: ${challengePath}`);
        console.log(
          `  2. Start with the starter code: cd ${path.join(challengePath, "starter")}`,
        );
        console.log(`  3. Run tests to see them fail`);
        console.log(`  4. Implement your solution`);
        console.log(`  5. Compare with the reference solution if needed`);
      } catch (error) {
        console.error(
          "❌ Error:",
          error instanceof Error ? error.message : String(error),
        );
        process.exit(1);
      }
    });

  return command;
}
