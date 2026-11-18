/**
 * External links are only rendered as clickable when they use https.
 * This blocks `javascript:` and `data:` URLs even if bad data slips through.
 */
export function isSafeExternalUrl(value: string): boolean {
  try {
    return new URL(value).protocol === "https:";
  } catch {
    return false;
  }
}

export function hostnameOf(value: string): string {
  try {
    return new URL(value).hostname.replace(/^www\./, "");
  } catch {
    return "";
  }
}
