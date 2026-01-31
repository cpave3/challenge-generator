import { 
  ParseResult, 
  Validator, 
  SyntaxError, 
  ValidationError, 
  TypeError 
} from './types.js';

function createSyntaxError(input: string, cause: globalThis.Error): SyntaxError {
  return {
    type: 'syntax',
    message: `Invalid JSON syntax: ${cause.message}`,
    input: input.length > 100 ? `${input.slice(0, 100)}...` : input,
    cause,
  };
}

function createValidationError(received: unknown): ValidationError {
  return {
    type: 'validation',
    message: 'Parsed JSON failed schema validation',
    input: undefined,
    received,
  };
}

function createTypeError(received: unknown): TypeError {
  return {
    type: 'type',
    message: `Expected string input, received ${typeof received}`,
    input: undefined,
    receivedType: typeof received,
  };
}

/**
 * Safely parse a JSON string with schema validation.
 * 
 * @param input - The JSON string to parse
 * @param validator - Type predicate function to validate the parsed structure
 * @returns ParseResult with either typed data or detailed error
 */
export function safeParseJson<T>(
  input: unknown,
  validator: Validator<T>
): ParseResult<T> {
  // Type guard: input must be string
  if (typeof input !== 'string') {
    return {
      success: false,
      error: createTypeError(input),
    };
  }

  // Attempt JSON parsing
  let parsed: unknown;
  try {
    parsed = JSON.parse(input);
  } catch (cause) {
    if (cause instanceof globalThis.Error) {
      return {
        success: false,
        error: createSyntaxError(input, cause),
      };
    }
    // Handle non-Error throws (extremely rare)
    return {
      success: false,
      error: createSyntaxError(input, new globalThis.Error(String(cause))),
    };
  }

  // Schema validation
  if (!validator(parsed)) {
    return {
      success: false,
      error: createValidationError(parsed),
    };
  }

  // Success path: parsed is now narrowed to T
  return {
    success: true,
    data: parsed,
  };
}
