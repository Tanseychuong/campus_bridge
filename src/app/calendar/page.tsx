import type { Metadata } from "next";
import { catalogRepository } from "@/services";
import { CalendarView } from "@/components/features/calendar-view";
import { PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = { title: "My calendar" };
export const revalidate = 3600;

export default async function CalendarPage() {
  const catalog = await catalogRepository.getCatalog();
  return (
    <div className="container-page">
      <PageHeader
        eyebrow="My campus"
        title="My calendar"
        description="Your saved events and deadlines in one view, with automatic clash detection. Times are shown in Accra time (GMT)."
      />
      <CalendarView catalog={catalog} />
    </div>
  );
}
