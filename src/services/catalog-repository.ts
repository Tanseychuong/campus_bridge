/**
 * Data-access contract for the public catalogue.
 *
 * UI code depends only on this interface, never on where the data comes from.
 * Today it is implemented by `MockCatalogRepository` (fictional fixtures).
 * Later, an `ApiCatalogRepository` backed by a secure server and database can
 * implement the same interface without changing any page or component.
 *
 * Methods are async on purpose: a real backend will be asynchronous, so the
 * pages are already written that way.
 */
import type { Catalog, CampusEvent, Opportunity, Organization } from "@/schemas/domain";

export interface CatalogRepository {
  /** All published, validated content. */
  getCatalog(): Promise<Catalog>;
  /** Includes pending/rejected items — for the admin review demo only. */
  getAllForReview(): Promise<Catalog>;
  getOpportunity(id: string): Promise<Opportunity | null>;
  getEvent(id: string): Promise<CampusEvent | null>;
  getOrganization(id: string): Promise<Organization | null>;
}
