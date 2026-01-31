# Safe JSON Parser with Schema Validation

**Difficulty:** Level 1 (Junior)  
**Estimated Time:** 30-60 minutes  
**Skills:** TypeScript type narrowing, Error handling patterns, JSON parsing with validation

## Context

Parsing JSON from external sources (APIs, user input, files) is one of the most common operations in modern applications—and one of the most error-prone. Runtime type safety is critical: a malformed payload shouldn't crash your application or propagate `any` types through your codebase.

In this challenge, you'll build a robust JSON parser that validates structure against a schema and provides strongly-typed results with comprehensive error information.

## Requirements

### Functional Requirements

1. **Parse Function**: Create a `safeParseJson` function that accepts:
   - A JSON string
   - A schema validator function that acts as a type predicate
   - Returns a discriminated union: `{ success: true, data: T } | { success: false, error: ParseError }`

2. **Error Types**: Support three distinct error categories:
   - `SyntaxError`: Invalid JSON syntax (malformed JSON)
   - `ValidationError`: Valid JSON that fails schema validation
   - `TypeError`: Non-string input provided

3. **Type Safety**: The returned data must be properly typed based on the validator's return type

### Non-Functional Requirements

- Zero `any` types in the implementation
- No exceptions thrown to callers—all errors captured in return type
- Immutable error objects with detailed context
- Pure functions with no side effects

## Definition of Done

- [ ] `safeParseJson` handles all three error cases with distinct error types
- [ ] Return type correctly narrows based on validator predicate
- [ ] All tests pass with comprehensive coverage
- [ ] No `any` types used
- [ ] Error objects include context for debugging (input preview, specific failure reason)

## Acceptance Tests

The test suite verifies:
1. Successful parsing with valid JSON and matching schema
2. SyntaxError for malformed JSON strings
3. ValidationError for JSON that parses but fails schema check
4. TypeError for non-string inputs (null, undefined, objects, numbers)
5. Type narrowing: success path gives typed data, failure path gives ParseError
6. Error context includes helpful debugging information

## Hints

- Use TypeScript's type predicates: `value is T`
- Consider a discriminated union for the result type
- `JSON.parse` throws—wrap it carefully
- The validator function should have signature: `(unknown) => value is T`

## Extensions (Optional)

- Add a `safeParseJsonAsync` variant for streams/promises
- Support JSON reviver functions
- Add error code enums for programmatic error handling