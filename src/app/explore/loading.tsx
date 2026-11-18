import { Skeleton, SkeletonList } from "@/components/ui/primitives";

export default function ExploreLoading() {
  return (
    <div className="container-page pt-10">
      <Skeleton className="h-10 w-48" />
      <Skeleton className="mt-6 h-11 w-full" />
      <div className="mt-8">
        <SkeletonList count={4} label="Loading results" />
      </div>
    </div>
  );
}
