import Link from "next/link";
import { LogoMark } from "./logo";

export function SiteFooter() {
  return (
    <footer className="border-line mt-20 border-t bg-white">
      <div className="container-page grid gap-8 py-10 md:grid-cols-[2fr_1fr_1fr]">
        <div>
          <div className="flex items-center gap-2">
            <LogoMark className="size-7" />
            <span className="font-bold">CampusBridge</span>
          </div>
          <p className="text-muted mt-3 max-w-md text-sm">
            An independent student project (CS341 Web Technologies). CampusBridge is not affiliated
            with or endorsed by any university. All organizations, opportunities and events shown
            are <strong className="text-ink font-semibold">fictional sample data</strong>.
          </p>
        </div>
        <nav aria-label="Footer: discover">
          <p className="text-sm font-semibold">Discover</p>
          <ul className="text-muted mt-3 space-y-2 text-sm">
            <li>
              <Link className="hover:text-ink" href="/explore?type=opportunity">
                Opportunities
              </Link>
            </li>
            <li>
              <Link className="hover:text-ink" href="/events">
                Events
              </Link>
            </li>
            <li>
              <Link className="hover:text-ink" href="/organizations">
                Organizations
              </Link>
            </li>
          </ul>
        </nav>
        <nav aria-label="Footer: about">
          <p className="text-sm font-semibold">About</p>
          <ul className="text-muted mt-3 space-y-2 text-sm">
            <li>
              <Link className="hover:text-ink" href="/about">
                About CampusBridge
              </Link>
            </li>
            <li>
              <Link className="hover:text-ink" href="/about#recommendations">
                How recommendations work
              </Link>
            </li>
            <li>
              <Link className="hover:text-ink" href="/about#privacy">
                Privacy &amp; data
              </Link>
            </li>
          </ul>
        </nav>
      </div>
    </footer>
  );
}
