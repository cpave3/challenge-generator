import { describe, it, expect } from "vitest";
import { safeParseJson } from "../src/parser.ts";
import { ParseResult, ParseError } from "../src/types.ts";

// Test fixtures
interface User {
  readonly id: number;
  readonly name: string;
  readonly email: string;
}

function isUser(value: unknown): value is User {
  return (
    typeof value === "object" &&
    value !== null &&
    "id" in value &&
    "name" in value &&
    "email" in value &&
    typeof (value as Record<string, unknown>).id === "number" &&
    typeof (value as Record<string, unknown>).name === "string" &&
    typeof (value as Record<string, unknown>).email === "string"
  );
}

function isStringArray(value: unknown): value is string[] {
  return (
    Array.isArray(value) && value.every((item) => typeof item === "string")
  );
}

describe("safeParseJson", () => {
  describe("success cases", () => {
    it("should parse valid JSON matching schema", () => {
      const json = '{"id":1,"name":"Alice","email":"alice@example.com"}';
      const result = safeParseJson(json, isUser);

      expect(result.success).toBe(true);
      if (result.success) {
        expect(result.data).toEqual({
          id: 1,
          name: "Alice",
          email: "alice@example.com",
        });
      }
    });

    it("should parse arrays with matching schema", () => {
      const json = '["apple", "banana", "cherry"]';
      const result = safeParseJson(json, isStringArray);

      expect(result.success).toBe(true);
      if (result.success) {
        expect(result.data).toEqual(["apple", "banana", "cherry"]);
      }
    });

    it("should parse nested structures", () => {
      interface Team {
        readonly name: string;
        readonly members: readonly User[];
      }

      function isTeam(value: unknown): value is Team {
        return (
          typeof value === "object" &&
          value !== null &&
          "name" in value &&
          "members" in value &&
          typeof (value as Record<string, unknown>).name === "string" &&
          Array.isArray((value as Record<string, unknown>).members) &&
          (value as Record<string, unknown>).members.every(isUser)
        );
      }

      const json = JSON.stringify({
        name: "Engineering",
        members: [{ id: 1, name: "Alice", email: "alice@example.com" }],
      });

      const result = safeParseJson(json, isTeam);
      expect(result.success).toBe(true);
    });
  });

  describe("syntax errors", () => {
    it("should return SyntaxError for malformed JSON", () => {
      const json = '{"unclosed": "string';
      const result = safeParseJson(json, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("syntax");
        expect(result.error.message).toContain("Invalid JSON");
        expect(result.error.input).toBeDefined();
        expect(result.error.cause).toBeInstanceOf(Error);
      }
    });

    it("should return SyntaxError for empty string", () => {
      const result = safeParseJson("", isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("syntax");
      }
    });

    it("should return SyntaxError for invalid JSON primitives", () => {
      const result = safeParseJson("undefined", isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("syntax");
      }
    });

    it("should truncate long input in error", () => {
      const longJson = '{"data": "' + "x".repeat(200) + '"' + "}";
      const result = safeParseJson(longJson.slice(0, -1), isUser); // Remove closing brace

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.input!.length).toBeLessThanOrEqual(105); // 100 + '...'
        expect(result.error.input).toMatch(/\.\.\.$/);
      }
    });
  });

  describe("validation errors", () => {
    it("should return ValidationError when schema fails", () => {
      const json = '{"id": "not-a-number", "name": 123, "email": true}';
      const result = safeParseJson(json, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("validation");
        expect(result.error.message).toContain("schema");
        expect(result.error).toHaveProperty("received");
      }
    });

    it("should return ValidationError for valid JSON with wrong structure", () => {
      const json = '{"completely": "different", "keys": true}';
      const result = safeParseJson(json, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("validation");
      }
    });

    it("should return ValidationError for valid primitive not matching array schema", () => {
      const json = '"just a string"';
      const result = safeParseJson(json, isStringArray);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("validation");
      }
    });
  });

  describe("type errors", () => {
    it("should return TypeError for null input", () => {
      const result = safeParseJson(null, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("type");
        expect(result.error.message).toContain("string");
        expect(result.error.receivedType).toBe("object");
      }
    });

    it("should return TypeError for undefined input", () => {
      const result = safeParseJson(undefined, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("type");
        expect(result.error.receivedType).toBe("undefined");
      }
    });

    it("should return TypeError for number input", () => {
      const result = safeParseJson(42, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("type");
        expect(result.error.receivedType).toBe("number");
      }
    });

    it("should return TypeError for object input", () => {
      const result = safeParseJson({ already: "parsed" }, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("type");
        expect(result.error.receivedType).toBe("object");
      }
    });

    it("should return TypeError for boolean input", () => {
      const result = safeParseJson(true, isUser);

      expect(result.success).toBe(false);
      if (!result.success) {
        expect(result.error.type).toBe("type");
        expect(result.error.receivedType).toBe("boolean");
      }
    });
  });

  describe("type narrowing", () => {
    it("should narrow success branch to typed data", () => {
      const json = '{"id":1,"name":"Test","email":"test@example.com"}';
      const result = safeParseJson(json, isUser);

      if (result.success) {
        // TypeScript should know result.data is User here
        const user: User = result.data;
        expect(typeof user.id).toBe("number");
        expect(typeof user.name).toBe("string");
        expect(typeof user.email).toBe("string");
      }
    });

    it("should narrow failure branch to ParseError", () => {
      const result = safeParseJson("invalid", isUser);

      if (!result.success) {
        // TypeScript should know result.error is ParseError here
        const error: ParseError = result.error;
        expect(["syntax", "validation", "type"]).toContain(error.type);
      }
    });
  });

  describe("edge cases", () => {
    it("should handle JSON with whitespace", () => {
      const json = '  \n\t{"id":1,"name":"Test","email":"t@e.com"}  \n  ';
      const result = safeParseJson(json, isUser);

      expect(result.success).toBe(true);
    });

    it("should handle valid JSON null with nullable schema", () => {
      function isNullableUser(value: unknown): value is User | null {
        return value === null || isUser(value);
      }

      const result = safeParseJson("null", isNullableUser);
      expect(result.success).toBe(true);
      if (result.success) {
        expect(result.data).toBeNull();
      }
    });

    it("should handle valid JSON boolean with boolean schema", () => {
      function isBoolean(value: unknown): value is boolean {
        return typeof value === "boolean";
      }

      const result = safeParseJson("true", isBoolean);
      expect(result.success).toBe(true);
      if (result.success) {
        expect(result.data).toBe(true);
      }
    });

    it("should handle valid JSON number with number schema", () => {
      function isNumber(value: unknown): value is number {
        return typeof value === "number" && !Number.isNaN(value);
      }

      const result = safeParseJson("42.5", isNumber);
      expect(result.success).toBe(true);
      if (result.success) {
        expect(result.data).toBe(42.5);
      }
    });
  });
});
