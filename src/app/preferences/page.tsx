import type { Metadata } from "next";
import { catalogRepository } from "@/services";
import { PreferencesView } from "@/components/features/preferences-view";
import { PageHeader } from "@/components/ui/primitives";

export const metadata: Metadata = { title: "Preferences" };
export const revalidate = 3600;

export default async function PreferencesPage() {
  const catalog = await catalogRepository.getCatalog();
  return (
    <div className="container-page">
      <PageHeader
        eyebrow="My campus"
        title="Preferences"
        description="Control what CampusBridge recommends to you. Every change takes effect immediately."
      />
      <PreferencesView catalog={catalog} />
    </div>
  );
}
