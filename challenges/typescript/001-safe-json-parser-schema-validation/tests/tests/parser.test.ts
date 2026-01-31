import { describe, it } from 'node:test';
import assert from 'node:assert';
import { safeParseJson } from '../src/parser.js';
import { ParseResult, ParseError } from '../src/types.js';

// Test fixtures
interface User {
  readonly id: number;
  readonly name: string;
  readonly email: string;
}

function isUser(value: unknown): value is User {
  return (
    typeof value === 'object' &&
    value !== null &&
    'id' in value &&
    'name' in value &&
    'email' in value &&
    typeof (value as Record<string, unknown>).id === 'number' &&
    typeof (value as Record<string, unknown>).name === 'string' &&
    typeof (value as Record<string, unknown>).email === 'string'
  );
}

function isStringArray(value: unknown): value is string[] {
  return Array.isArray(value) && value.every((item) => typeof item === 'string');
}

describe('safeParseJson', () => {
  describe('success cases', () => {
    it('should parse valid JSON matching schema', () => {
      const json = '{"id":1,"name":"Alice","email":"alice@example.com"}';
      const result = safeParseJson(json, isUser);

      assert.strictEqual(result.success, true);
      if (result.success) {
        assert.deepStrictEqual(result.data, {
          id: 1,
          name: 'Alice',
          email: 'alice@example.com',
        });
      }
    });

    it('should parse arrays with matching schema', () => {
      const json = '["apple", "banana", "cherry"]';
      const result = safeParseJson(json, isStringArray);

      assert.strictEqual(result.success, true);
      if (result.success) {
        assert.deepStrictEqual(result.data, ['apple', 'banana', 'cherry']);
      }
    });

    it('should parse nested structures', () => {
      interface Team {
        readonly name: string;
        readonly members: readonly User[];
      }

      function isTeam(value: unknown): value is Team {
        return (
          typeof value === 'object' &&
          value !== null &&
          'name' in value &&
          'members' in value &&
          typeof (value as Record<string, unknown>).name === 'string' &&
          Array.isArray((value as Record<string, unknown>).members) &&
          (value as Record<string, unknown>).members.every(isUser)
        );
      }

      const json = JSON.stringify({
        name: 'Engineering',
        members: [{ id: 1, name: 'Alice', email: 'alice@example.com' }],
      });

      const result = safeParseJson(json, isTeam);
      assert.strictEqual(result.success, true);
    });
  });

  describe('syntax errors', () => {
    it('should return SyntaxError for malformed JSON', () => {
      const json = '{"unclosed": "string';
      const result = safeParseJson(json, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'syntax');
        assert.ok(result.error.message.includes('Invalid JSON'));
        assert.ok(result.error.input !== undefined);
        assert.ok(result.error.cause instanceof Error);
      }
    });

    it('should return SyntaxError for empty string', () => {
      const result = safeParseJson('', isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'syntax');
      }
    });

    it('should return SyntaxError for invalid JSON primitives', () => {
      const result = safeParseJson('undefined', isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'syntax');
      }
    });

    it('should truncate long input in error', () => {
      const longJson = '{"data": "' + 'x'.repeat(200) + '"' + '}';
      const result = safeParseJson(longJson.slice(0, -1), isUser); // Remove closing brace

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.ok(result.error.input!.length <= 105); // 100 + '...'
        assert.ok(result.error.input!.endsWith('...'));
      }
    });
  });

  describe('validation errors', () => {
    it('should return ValidationError when schema fails', () => {
      const json = '{"id": "not-a-number", "name": 123, "email": true}';
      const result = safeParseJson(json, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'validation');
        assert.ok(result.error.message.includes('schema'));
        assert.ok('received' in result.error);
      }
    });

    it('should return ValidationError for valid JSON with wrong structure', () => {
      const json = '{"completely": "different", "keys": true}';
      const result = safeParseJson(json, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'validation');
      }
    });

    it('should return ValidationError for valid primitive not matching array schema', () => {
      const json = '"just a string"';
      const result = safeParseJson(json, isStringArray);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'validation');
      }
    });
  });

  describe('type errors', () => {
    it('should return TypeError for null input', () => {
      const result = safeParseJson(null, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'type');
        assert.ok(result.error.message.includes('string'));
        assert.strictEqual(result.error.receivedType, 'object');
      }
    });

    it('should return TypeError for undefined input', () => {
      const result = safeParseJson(undefined, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'type');
        assert.strictEqual(result.error.receivedType, 'undefined');
      }
    });

    it('should return TypeError for number input', () => {
      const result = safeParseJson(42, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'type');
        assert.strictEqual(result.error.receivedType, 'number');
      }
    });

    it('should return TypeError for object input', () => {
      const result = safeParseJson({ already: 'parsed' }, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'type');
        assert.strictEqual(result.error.receivedType, 'object');
      }
    });

    it('should return TypeError for boolean input', () => {
      const result = safeParseJson(true, isUser);

      assert.strictEqual(result.success, false);
      if (!result.success) {
        assert.strictEqual(result.error.type, 'type');
        assert.strictEqual(result.error.receivedType, 'boolean');
      }
    });
  });

  describe('type narrowing', () => {
    it('should narrow success branch to typed data', () => {
      const json = '{"id":1,"name":"Test","email":"test@example.com"}';
      const result = safeParseJson(json, isUser);

      if (result.success) {
        // TypeScript should know result.data is User here
        const user: User = result.data;
        assert.strictEqual(typeof user.id, 'number');
        assert.strictEqual(typeof user.name, 'string');
        assert.strictEqual(typeof user.email, 'string');
      }
    });

    it('should narrow failure branch to ParseError', () => {
      const result = safeParseJson('invalid', isUser);

      if (!result.success) {
        // TypeScript should know result.error is ParseError here
        const error: ParseError = result.error;
        assert.ok(['syntax', 'validation', 'type'].includes(error.type));
      }
    });
  });

  describe('edge cases', () => {
    it('should handle JSON with whitespace', () => {
      const json = '  \n\t{"id":1,"name":"Test","email":"t@e.com"}  \n  ';
      const result = safeParseJson(json, isUser);

      assert.strictEqual(result.success, true);
    });

    it('should handle valid JSON null with nullable schema', () => {
      function isNullableUser(value: unknown): value is User | null {
        return value === null || isUser(value);
      }

      const result = safeParseJson('null', isNullableUser);
      assert.strictEqual(result.success, true);
      if (result.success) {
        assert.strictEqual(result.data, null);
      }
    });

    it('should handle valid JSON boolean with boolean schema', () => {
      function isBoolean(value: unknown): value is boolean {
        return typeof value === 'boolean';
      }

      const result = safeParseJson('true', isBoolean);
      assert.strictEqual(result.success, true);
      if (result.success) {
        assert.strictEqual(result.data, true);
      }
    });

    it('should handle valid JSON number with number schema', () => {
      function isNumber(value: unknown): value is number {
        return typeof value === 'number' && !Number.isNaN(value);
      }

      const result = safeParseJson('42.5', isNumber);
      assert.strictEqual(result.success, true);
      if (result.success) {
        assert.strictEqual(result.data, 42.5);
      }
    });
  });
});
