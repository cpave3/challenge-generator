/**
 * Error types for JSON parsing failures
 */

export type ParseError = never; // TODO: Define discriminated union of error types

export type ParseErrorType = never; // TODO: Define error type discriminator

/**
 * Result type for safe JSON parsing
 */
export type ParseResult<T> = never; // TODO: Define success/failure discriminated union

/**
 * Schema validator type predicate
 */
export type Validator<T> = (value: unknown) => value is T;
