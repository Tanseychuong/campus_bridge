"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import * as Dialog from "@radix-ui/react-dialog";
import {
  Bookmark,
  Building2,
  CalendarDays,
  Compass,
  LayoutDashboard,
  Menu,
  PartyPopper,
  Settings2,
  ShieldCheck,
  Users,
  X,
} from "lucide-react";
import { cn } from "@/lib/cn";
import { useUserData } from "@/state/hooks";
import { DEMO_ROLES, DEMO_ROLE_LABELS, type DemoRole } from "@/state/user-data";
import { Logo } from "./logo";

const DISCOVER = [
  { href: "/explore", label: "Explore", icon: Compass },
  { href: "/events", label: "Events", icon: PartyPopper },
  { href: "/organizations", label: "Organizations", icon: Users },
];

const PERSONAL = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/saved", label: "Saved", icon: Bookmark },
  { href: "/calendar", label: "Calendar", icon: CalendarDays },
];

const ROLE_LINKS: Partial<Record<DemoRole, { href: string; label: string; icon: typeof Compass }>> =
  {
    "org-rep": { href: "/manage", label: "Manage", icon: Building2 },
    admin: { href: "/admin", label: "Admin", icon: ShieldCheck },
  };

function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

function NavLink({
  href,
  label,
  icon: Icon,
  onNavigate,
  mobile,
}: {
  href: string;
  label: string;
  icon: typeof Compass;
  onNavigate?: () => void;
  mobile?: boolean;
}) {
  const pathname = usePathname();
  const active = isActive(pathname, href);
  return (
    <Link
      href={href}
      onClick={onNavigate}
      aria-current={active ? "page" : undefined}
      className={cn(
        "flex items-center gap-2 rounded-lg font-semibold transition-colors",
        mobile ? "px-3 py-3 text-base" : "px-3 py-2 text-sm",
        active ? "bg-brand-50 text-brand-700" : "hover:text-ink text-slate-700 hover:bg-slate-100",
      )}
    >
      <Icon className={mobile ? "size-4" : "hidden size-4 2xl:block"} aria-hidden />
      {label}
    </Link>
  );
}

export function RoleSwitcher({ id }: { id: string }) {
  const { data, ready, setRole } = useUserData();
  return (
    <div className="flex items-center gap-2">
      <label htmlFor={id} className="text-muted text-xs font-semibold">
        Demo role
      </label>
      <select
        id={id}
        value={data.role}
        disabled={!ready}
        onChange={(e) => setRole(e.target.value as DemoRole)}
        className="border-line h-9 rounded-lg border bg-white px-2 text-sm font-medium"
      >
        {DEMO_ROLES.map((r) => (
          <option key={r} value={r}>
            {DEMO_ROLE_LABELS[r]}
          </option>
        ))}
      </select>
    </div>
  );
}

export function SiteHeader() {
  const [open, setOpen] = useState(false);
  const { data } = useUserData();
  const roleLink = ROLE_LINKS[data.role];
  const close = () => setOpen(false);

  return (
    <header className="border-line sticky top-0 z-40 border-b bg-white/90 backdrop-blur">
      <div className="container-page flex h-16 items-center gap-4">
        <Logo />

        <nav aria-label="Main" className="ml-2 hidden items-center gap-0.5 xl:flex">
          {DISCOVER.map((item) => (
            <NavLink key={item.href} {...item} />
          ))}
          <span className="bg-line mx-2 h-6 w-px" aria-hidden />
          {PERSONAL.map((item) => (
            <NavLink key={item.href} {...item} />
          ))}
          {roleLink && <NavLink {...roleLink} />}
        </nav>

        <div className="ml-auto hidden items-center gap-2 xl:flex">
          <RoleSwitcher id="role-desktop" />
          <Link
            href="/preferences"
            className="hover:text-ink rounded-lg p-2 text-slate-600 hover:bg-slate-100"
            aria-label="Preferences"
          >
            <Settings2 className="size-5" aria-hidden />
          </Link>
        </div>

        <Dialog.Root open={open} onOpenChange={setOpen}>
          <Dialog.Trigger
            className="text-ink ml-auto inline-flex size-11 items-center justify-center rounded-lg hover:bg-slate-100 xl:hidden"
            aria-label="Open menu"
          >
            <Menu className="size-6" aria-hidden />
          </Dialog.Trigger>
          <Dialog.Portal>
            <Dialog.Overlay className="fixed inset-0 z-50 bg-slate-900/40" />
            <Dialog.Content className="fixed inset-y-0 right-0 z-50 flex w-[min(20rem,100vw)] flex-col overflow-y-auto bg-white p-4 shadow-xl">
              <div className="mb-4 flex items-center justify-between">
                <Dialog.Title className="text-base font-bold">Menu</Dialog.Title>
                <Dialog.Close
                  className="inline-flex size-11 items-center justify-center rounded-lg hover:bg-slate-100"
                  aria-label="Close menu"
                >
                  <X className="size-5" aria-hidden />
                </Dialog.Close>
              </div>
              <Dialog.Description className="sr-only">Site navigation</Dialog.Description>
              <nav aria-label="Mobile" className="flex flex-col gap-1">
                <p className="text-muted px-3 pt-2 pb-1 text-xs font-semibold uppercase">
                  Discover
                </p>
                {DISCOVER.map((item) => (
                  <NavLink key={item.href} {...item} mobile onNavigate={close} />
                ))}
                <p className="text-muted px-3 pt-4 pb-1 text-xs font-semibold uppercase">
                  My campus
                </p>
                {PERSONAL.map((item) => (
                  <NavLink key={item.href} {...item} mobile onNavigate={close} />
                ))}
                <NavLink
                  href="/preferences"
                  label="Preferences"
                  icon={Settings2}
                  mobile
                  onNavigate={close}
                />
                {roleLink && <NavLink {...roleLink} mobile onNavigate={close} />}
              </nav>
              <div className="border-line mt-6 border-t pt-4">
                <RoleSwitcher id="role-mobile" />
                <p className="text-muted mt-2 text-xs">
                  Roles are a labelled demonstration only — not secure sign-in.
                </p>
              </div>
            </Dialog.Content>
          </Dialog.Portal>
        </Dialog.Root>
      </div>
    </header>
  );
}
