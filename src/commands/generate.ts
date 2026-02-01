/**
 * Generate Command
 *
 * Generates a new coding challenge using AI.
 */

import { Command } from "commander";
import path from "path";
import { fileURLToPath } from "url";
import readline from "readline";
import { generateChallenge, generateChallengeBrief } from "../lib/ai-service.js";
import {
  CHALLENGE_GENERATOR_SYSTEM_PROMPT,
  BRIEF_GENERATOR_SYSTEM_PROMPT,
  buildUserPrompt,
  buildBriefUserPrompt,
} from "../lib/prompts.js";
import { scaffoldChallenge, getNextChallengeNumber } from "../lib/scaffold.js";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function askQuestion(rl: readline.Interface, question: string): Promise<string> {
  return new Promise((resolve) => {
    rl.question(question, (answer) => {
      resolve(answer.trim().toLowerCase());
    });
  });
}

function validateTime(time: string | undefined): string | undefined {
  if (!time) return undefined;
  
  const match = time.match(/^\d+[mhdw]$/);
  if (!match) {
    throw new Error(
      `Invalid time format: "${time}". Use format like 30m, 1h, 2h, 2d, 1w (number + m/h/d/w)`
    );
  }
  return time;
}

async function generateAndConfirmBrief(
  rl: readline.Interface,
  language: string,
  difficulty: number,
  topic?: string,
  time?: string
): Promise<string> {
  let brief: string;
  let accepted = false;

  while (!accepted) {
    console.log("\n🤖 Generating challenge brief...\n");

    const briefUserPrompt = buildBriefUserPrompt({
      language,
      difficulty,
      topic,
      time,
    });

    brief = await generateChallengeBrief(BRIEF_GENERATOR_SYSTEM_PROMPT, briefUserPrompt);

    console.log("─".repeat(70));
    console.log(brief);
    console.log("─".repeat(70));
    console.log("");

    const answer = await askQuestion(rl, "[A]ccept this brief or [R]egenerate? (A/R): ");

    if (answer === "a" || answer === "accept") {
      accepted = true;
      console.log("\n✅ Brief accepted! Generating full challenge...\n");
    } else if (answer === "r" || answer === "regenerate") {
      console.log("\n🔄 Regenerating brief with a completely different concept...\n");
    } else {
      console.log("\n⚠️  Invalid input. Please enter 'A' to accept or 'R' to regenerate.\n");
    }
  }

  return brief!;
}

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
    .option("--time <duration>", "Estimated time (e.g., 30m, 1h, 2h, 2d, 1w)")
    .action(async (options) => {
      const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
      });

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

        // Validate time format if provided
        const time = validateTime(options.time);

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
        if (time) {
          console.log(`⏱️  Time estimate: ${time}`);
        }
        console.log("");

        // Get next challenge number
        const basePath = path.join(__dirname, "..", "..");
        const challengeNumber = await getNextChallengeNumber(
          language,
          basePath,
        );

        // PHASE 1: Generate and confirm brief
        const approvedBrief = await generateAndConfirmBrief(
          rl,
          language,
          difficulty,
          options.topic,
          time
        );

        // PHASE 2: Generate full challenge with approved brief
        console.log("🤖 Generating full challenge based on approved brief...");
        const userPrompt = buildUserPrompt({
          language,
          difficulty,
          topic: options.topic,
          challengeNumber,
          brief: approvedBrief,
          time,
        });

        const challengeData = await generateChallenge(
          CHALLENGE_GENERATOR_SYSTEM_PROMPT,
          userPrompt,
        );

        console.log(`\n✨ Generated: ${challengeData.challengeName}`);
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
      } finally {
        rl.close();
      }
    });

  return command;
}
