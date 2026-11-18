/**
 * Text processing for search: normalisation, tokenisation and a bounded
 * edit-distance check used for small typo tolerance.
 */

const STOP_WORDS = new Set([
  "a",
  "an",
  "and",
  "are",
  "as",
  "at",
  "be",
  "by",
  "for",
  "from",
  "in",
  "is",
  "it",
  "of",
  "on",
  "or",
  "the",
  "to",
  "with",
  "your",
  "you",
  "our",
  "we",
  "this",
  "that",
]);

/** Lower-case, strip accents (é -> e) and replace punctuation with spaces. */
export function normalize(text: string): string {
  return text
    .normalize("NFKD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, " ")
    .trim();
}

/** Split text into searchable tokens, dropping stop words and 1-letter tokens. */
export function tokenize(text: string): string[] {
  return normalize(text)
    .split(" ")
    .filter((t) => t.length > 1 && !STOP_WORDS.has(t));
}

/**
 * True when `a` and `b` differ by at most one edit (insert, delete or
 * substitute a character). Runs in O(n) instead of computing a full
 * Levenshtein matrix, because we only ever need the "≤ 1" answer.
 */
export function withinOneEdit(a: string, b: string): boolean {
  if (a === b) return true;
  if (Math.abs(a.length - b.length) > 1) return false;
  const [short, long] = a.length <= b.length ? [a, b] : [b, a];
  let i = 0;
  let j = 0;
  let edits = 0;
  while (i < short.length && j < long.length) {
    if (short[i] === long[j]) {
      i++;
      j++;
      continue;
    }
    if (++edits > 1) return false;
    if (short.length === long.length) i++; // substitution
    j++; // insertion into the shorter string
  }
  return edits + (long.length - j) <= 1;
}
