import { ParseError, ParseResult, Validator } from "./types.js";

/**
 * Safely parse a JSON string with schema validation.
 *
 * @param input - The JSON string to parse
 * @param validator - Type predicate function to validate the parsed structure
 * @returns ParseResult with either typed data or detailed error
 */
export function safeParseJson<T>(
  input: string,
  validator: Validator<T>,
): ParseResult<T> {
  // TODO: Implement safe parsing with comprehensive error handling
  // 1. Check if input is a string (TypeError if not)
  // 2. Parse JSON (SyntaxError if JSON.parse throws)
  // 3. Validate with type predicate (ValidationError if fails)
  // 4. Return success result with typed data

  if (typeof input !== "string") {
    return {
      success: false,
      error: {
        input,
        message: "Input must be a JSON string",
        type: "type",
        receivedType: typeof input,
      },
    };
  }

  let parsedJson: T | undefined = undefined;
  try {
    parsedJson = JSON.parse(input);
  } catch (error) {
    return {
      success: false,
      error: {
        cause: error as Error,
        input: truncateInput(input),
        type: "syntax",
        message: "Invalid JSON string",
      },
    };
  }

  const success = validator(parsedJson);

  if (success) {
    return {
      success: true,
      data: parsedJson as T,
    };
  } else {
    return {
      success: false,
      error: {
        type: "validation",
        message: "Parsed JSON does not match the expected schema",
        received: parsedJson,
      } as ParseError,
    };
  }
}

function truncateInput(input: string): string {
  return input.length > 100 ? input.slice(0, 100) + "..." : input;
}
