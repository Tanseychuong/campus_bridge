import type { Metadata } from "next";
import { catalogRepository } from "@/services";
import { SavedView } from "@/components/features/saved-view";
import { PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = { title: "Saved & deadlines" };
export const revalidate = 3600;

export default async function SavedPage() {
  const catalog = await catalogRepository.getCatalog();
  return (
    <div className="container-page">
      <PageHeader
        eyebrow="My campus"
        title="Saved & deadlines"
        description="Everything you have saved, with deadlines grouped by urgency."
      />
      <SavedView catalog={catalog} />
    </div>
  );
}
