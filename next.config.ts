import type { NextConfig } from "next";

/**
 * Next.js configuration.
 *
 * We deliberately use the classic App Router caching model (cacheComponents is
 * off) because it is simpler to reason about for a course project. Pages that
 * read searchParams render per request; other pages are prerendered and
 * revalidated (see `export const revalidate` in each page).
 */
const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
];

const nextConfig: NextConfig = {
  poweredByHeader: false,
  reactStrictMode: true,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
};

export default nextConfig;
