import Link from "next/link";

/** Original CampusBridge mark: an arch (bridge) joining two pillars. */
export function LogoMark({ className = "size-8" }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden>
      <rect width="32" height="32" rx="9" fill="#2747bd" />
      <path
        d="M6 22c2.5-7 6-10.5 10-10.5S23.5 15 26 22"
        fill="none"
        stroke="#efb23c"
        strokeWidth="2.6"
        strokeLinecap="round"
      />
      <path d="M6 22h20" stroke="white" strokeWidth="2.2" strokeLinecap="round" />
      <path
        d="M11 16.5V22M16 11.5V22M21 16.5V22"
        stroke="white"
        strokeWidth="1.6"
        strokeLinecap="round"
        opacity=".85"
      />
    </svg>
  );
}

export function Logo() {
  return (
    <Link href="/" className="flex items-center gap-2 rounded-lg" aria-label="CampusBridge home">
      <LogoMark />
      <span className="text-ink text-lg font-bold tracking-tight">
        Campus<span className="text-brand-600">Bridge</span>
      </span>
    </Link>
  );
}
