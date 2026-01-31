/**
 * Error types for JSON parsing failures
 */

export type ParseErrorType = 'syntax' | 'validation' | 'type';

interface BaseParseError {
  readonly type: ParseErrorType;
  readonly message: string;
  readonly input: string | undefined;
}

export interface SyntaxError extends BaseParseError {
  readonly type: 'syntax';
  readonly cause: globalThis.Error;
}

export interface ValidationError extends BaseParseError {
  readonly type: 'validation';
  readonly received: unknown;
}

export interface TypeError extends BaseParseError {
  readonly type: 'type';
  readonly receivedType: string;
}

export type ParseError = SyntaxError | ValidationError | TypeError;

/**
 * Result type for safe JSON parsing
 */
export type ParseResult<T> =
  | { readonly success: true; readonly data: T }
  | { readonly success: false; readonly error: ParseError };

/**
 * Schema validator type predicate
 */
export type Validator<T> = (value: unknown) => value is T;
