# Code Kata Generator

A CLI tool for generating practical, real-world coding challenges using AI.

## Features

- 🤖 AI-powered challenge generation using Vercel AI SDK
- 📝 Multi-language support (TypeScript, PHP, Python)
- 🎯 Configurable difficulty levels (1-4)
- 📁 Automatic scaffolding of challenge structure
- ✅ Built-in validation for challenge completeness
- 🔌 Easy provider switching (OpenAI, Anthropic)

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd code-kata-generator

# Install dependencies
npm install

# Build the project
npm run build

# Link the CLI globally (optional)
npm link
```

## Configuration

1. Copy the environment file:
```bash
cp .env.example .env
```

2. Add your API key to `.env`:
```bash
OPENAI_API_KEY=your_key_here
```

## Usage

### Generate a Challenge

```bash
# Generate a Level 2 TypeScript challenge
challenge-gen generate -l typescript -d 2

# Generate with a specific topic
challenge-gen generate -l typescript -d 3 -t "async patterns"

# Generate a PHP challenge
challenge-gen generate -l php -d 1

# Generate a Python challenge
challenge-gen generate -l python -d 2 -t "decorators"
```

Options:
- `-l, --language <lang>`: Programming language (typescript, php, python) - default: typescript
- `-d, --difficulty <level>`: Difficulty level 1-4 - default: 2
- `-t, --topic <topic>`: Optional topic or skill to focus on

### List Challenges

```bash
# List all challenges
challenge-gen list

# List challenges for a specific language
challenge-gen list -l typescript
```

### Validate a Challenge

```bash
# Validate a challenge directory
challenge-gen validate ./challenges/typescript/001-example-challenge
```

## Challenge Structure

Each generated challenge includes:

```
challenges/
└── {language}/
    └── {NNN}-{challenge-slug}/
        ├── README.md           # Requirements, DOD, and context
        ├── starter/            # Boilerplate to get started
        ├── solution/           # Reference implementation
        └── tests/              # Acceptance tests
```

### Difficulty Levels

- **Level 1 (Junior)**: Single concept, clear path to solution
- **Level 2 (Mid)**: Multiple concepts, requires design decisions  
- **Level 3 (Senior)**: Architectural patterns, performance considerations
- **Level 4 (Principal)**: System design, trade-off analysis

## Switching AI Providers

To switch between AI providers, edit `src/lib/models.ts`:

```typescript
// Change this line to switch providers
export const ACTIVE_PROVIDER: AIProvider = 'openai'; // or 'anthropic'
```

Available providers are defined in the `MODELS` object.

## Development

```bash
# Run type checking
npm run typecheck

# Build the project
npm run build

# Watch mode for development
npm run dev
```

## Environment Variables

- `OPENAI_API_KEY`: Required for OpenAI provider
- `ANTHROPIC_API_KEY`: Required if using Anthropic provider

## License

MIT
