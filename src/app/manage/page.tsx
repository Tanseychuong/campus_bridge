import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { catalogRepository } from "@/services";
import { DEMO_ORGANIZATION_ID } from "@/state/demo-data";
import { ManageView } from "@/components/features/manage-view";
import { PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = { title: "Manage organization (demo)", robots: { index: false } };
export const revalidate = 3600;

export default async function ManagePage() {
  const organization = await catalogRepository.getOrganization(DEMO_ORGANIZATION_ID);
  if (!organization) notFound();
  const { events } = await catalogRepository.getCatalog();
  const publishedEvents = events
    .filter((e) => e.organizationId === organization.id)
    .sort((a, b) => Date.parse(a.start) - Date.parse(b.start));
  return (
    <div className="container-page">
      <PageHeader
        eyebrow="Organization area · demo"
        title="Manage your organization"
        description="Create and update events. New and edited events go to an administrator for review."
      />
      <ManageView organization={organization} publishedEvents={publishedEvents} />
    </div>
  );
}
