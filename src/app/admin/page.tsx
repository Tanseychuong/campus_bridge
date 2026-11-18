import type { Metadata } from "next";
import { catalogRepository } from "@/services";
import { AdminView } from "@/components/features/admin-view";
import { PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = { title: "Administration (demo)", robots: { index: false } };
export const revalidate = 3600;

export default async function AdminPage() {
  const [allContent, publicCatalog] = await Promise.all([
    catalogRepository.getAllForReview(),
    catalogRepository.getCatalog(),
  ]);
  return (
    <div className="container-page">
      <PageHeader
        eyebrow="Administration · demo"
        title="Review & analytics"
        description="Moderate new listings and understand the content on the platform."
      />
      <AdminView allContent={allContent} publicCatalog={publicCatalog} />
    </div>
  );
}
