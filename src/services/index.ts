/**
 * The single place where the app chooses its data source.
 * To connect a real backend later, replace MockCatalogRepository here with an
 * implementation of CatalogRepository that calls the secure API.
 */
import "server-only";
import type { CatalogRepository } from "./catalog-repository";
import { MockCatalogRepository } from "./mock/mock-catalog-repository";

export const catalogRepository: CatalogRepository = new MockCatalogRepository();
