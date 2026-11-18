"use client";

/**
 * Last-resort boundary if the root layout itself fails.
 * Uses inline styles because globals.css may not have loaded.
 */
export default function GlobalError({ reset }: { error: Error; reset: () => void }) {
  return (
    <html lang="en">
      <body
        style={{
          fontFamily: "system-ui, sans-serif",
          padding: "3rem 1rem",
          textAlign: "center",
          background: "#f6f7fb",
          color: "#0f172a",
        }}
      >
        <h1>CampusBridge is temporarily unavailable</h1>
        <p>Please try again in a moment.</p>
        <button
          onClick={reset}
          style={{
            padding: "0.75rem 1.25rem",
            borderRadius: 12,
            border: 0,
            background: "#2747bd",
            color: "white",
            fontWeight: 600,
          }}
        >
          Try again
        </button>
      </body>
    </html>
  );
}
