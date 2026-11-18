import type { Metadata } from "next";
import { catalogRepository } from "@/services";
import { DashboardView } from "@/components/features/dashboard-view";
import { PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = { title: "My dashboard" };
export const revalidate = 3600;

export default async function DashboardPage() {
  const catalog = await catalogRepository.getCatalog();
  return (
    <div className="container-page">
      <PageHeader
        eyebrow="My campus"
        title="Your dashboard"
        description="Recommendations, deadlines and events that matter to you — personalised in this browser."
      />
      <DashboardView catalog={catalog} />
    </div>
  );
}
